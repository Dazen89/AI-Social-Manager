<?php

namespace App\Services;

use App\Exceptions\EditorialSourceVerificationException;
use App\Exceptions\XApiException;
use App\Exceptions\XPostRejectedException;
use App\Models\EditorialProposal;
use App\Models\XConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class XPostPublisher
{
    public function __construct(
        private readonly XService $x,
        private readonly FoOlSlideSourceVerifier $reader,
    ) {}

    public function runDue(): int
    {
        if (! $this->deliveryEnvironmentAllowed()) {
            return 0;
        }

        $connection = $this->writingConnection();

        if (! $connection?->canPublish(automatic: true)) {
            return 0;
        }

        $ids = EditorialProposal::query()->where('platform', 'x')->where('status', 'approved')
            ->whereNull('delivery_attempted_at')->where('proposed_for', '<=', now())
            ->whereHas('site.editorialSchedule', fn ($query) => $query->where('enabled', true)->where('x_autopublish_enabled', true))
            ->whereHas('content.source', fn ($query) => $query->where('is_active', true)->where('verification_status', 'verified')
                ->whereNotNull('promotion_authorized_at'))
            ->orderBy('proposed_for')->limit(5)->pluck('id');

        $published = 0;

        foreach ($ids as $id) {
            if (! $this->currentPublishingConnection($connection, automatic: true)) {
                break;
            }

            $claimed = EditorialProposal::query()->whereKey($id)->where('status', 'approved')
                ->whereNull('delivery_attempted_at')->where('proposed_for', '<=', now())->update([
                    'status' => 'delivering',
                    'delivery_attempted_at' => now(),
                ]);

            if ($claimed !== 1) {
                continue;
            }

            $proposal = EditorialProposal::query()->with('site.editorialSchedule', 'content.source')->findOrFail($id);

            if ($this->deliver($proposal, $connection, automatic: true)) {
                $published++;
            }
        }

        return $published;
    }

    public function writingConnection(): ?XConnection
    {
        $connection = XConnection::query()->where('status', 'active')->first();

        return $connection?->hasWriteAccess() ? $connection : null;
    }

    public function manualPublicationProblem(EditorialProposal $proposal, ?XConnection $connection, array $attachments): ?string
    {
        if (! $this->deliveryEnvironmentAllowed()) {
            return 'L’invio reale su X è disponibile in produzione.';
        }

        if (! $connection) {
            return 'Collega un account X con il permesso di pubblicare.';
        }

        if (! $connection->canPublish(automatic: false)) {
            return 'Abilita la pubblicazione manuale dell’account X nella pagina Social.';
        }

        if (! in_array($proposal->status, EditorialProposal::EDITABLE_STATUSES, true)
            || $proposal->delivery_attempted_at || $proposal->x_post_id) {
            return 'La proposta è già stata presa in carico. Controlla lo stato nel piano editoriale.';
        }

        if (! $this->hasEligibleContent($proposal)) {
            return 'Verifica che il sito e la fonte siano attivi, che il capitolo sia idoneo e che il testo termini con il suo link pubblico.';
        }

        if ($attachments && ! in_array('media.write', $connection->scopes ?? [], true)) {
            return 'Ricollega l’account X con il permesso media.write per pubblicare le immagini.';
        }

        return null;
    }

    public function manualRevision(EditorialProposal $proposal): string
    {
        return hash('sha256', json_encode([
            $proposal->id, $proposal->content->id, $proposal->content->editorial_source_id, $proposal->content->external_id,
            $proposal->content->public_url, $proposal->post_text, $proposal->image_attachments, (int) $proposal->delivery_revision,
        ], JSON_THROW_ON_ERROR));
    }

    public function publishNow(EditorialProposal $proposal, XConnection $connection, int $reviewerId, string $revision, array $attachments): EditorialProposal
    {
        $claimed = DB::transaction(function () use ($proposal, $connection, $reviewerId, $revision, $attachments): EditorialProposal {
            $current = EditorialProposal::query()->lockForUpdate()->findOrFail($proposal->id);

            if ($problem = $this->manualPublicationProblem($current, $connection, $attachments)) {
                throw new XApiException($problem);
            }

            if (! hash_equals($this->manualRevision($current), $revision)) {
                throw new XApiException('La proposta è cambiata. Riapri l’anteprima prima di pubblicare.');
            }

            $current->update([
                'image_attachments' => $attachments,
                'status' => 'delivering',
                'delivery_attempted_at' => now(),
                'reviewed_by_user_id' => $reviewerId,
                'reviewed_at' => now(),
                'delivery_error' => null,
            ]);

            return $current;
        });

        $this->deliver($claimed, $connection, automatic: false);

        return $claimed->refresh();
    }

    private function deliveryEnvironmentAllowed(): bool
    {
        return app()->environment('production') || (app()->environment('testing') && config('services.x.test_fake_publish'));
    }

    private function currentPublishingConnection(XConnection $expected, bool $automatic): ?XConnection
    {
        $connection = XConnection::query()->find($expected->id);

        return $connection?->canPublish($automatic) && $connection->x_user_id === $expected->x_user_id ? $connection : null;
    }

    private function deliver(EditorialProposal $proposal, XConnection $connection, bool $automatic): bool
    {
        if (! $this->canDeliver($proposal, $automatic)) {
            $this->returnToDraft($proposal, 'Pubblicazione bloccata. Verifica fonte, idoneità, URL e approvazione.');

            return false;
        }

        try {
            $mediaIds = $proposal->image_attachments ? $this->uploadAttachments($proposal, $connection, $automatic) : [];
        } catch (EditorialSourceVerificationException|XApiException $exception) {
            $this->returnToDraft($proposal, $exception->getMessage());

            return false;
        } catch (Throwable) {
            $this->returnToDraft($proposal, 'Caricamento delle immagini non riuscito. Verifica gli allegati e conferma di nuovo la proposta.');

            return false;
        }

        if (! $this->canDeliver($proposal->refresh(), $automatic)) {
            $this->returnToDraft($proposal, 'La fonte o l’idoneità è cambiata durante l’invio. Verifica e conferma di nuovo la proposta.');

            return false;
        }

        $activeConnection = $this->currentPublishingConnection($connection, $automatic);

        if (! $activeConnection) {
            $this->returnToDraft($proposal, 'L’account X è cambiato o la pubblicazione è stata sospesa durante l’invio.');

            return false;
        }

        try {
            $xPostId = $this->x->createPost($activeConnection, $proposal->post_text, $mediaIds);
            $proposal->update(['status' => 'published', 'x_post_id' => $xPostId,
                'published_at' => now(), 'delivery_error' => null]);

            return true;
        } catch (XPostRejectedException $exception) {
            $this->returnToDraft($proposal, $exception->getMessage());

            return false;
        } catch (Throwable $exception) {
            $status = $exception instanceof XApiException ? $exception->getCode() : 0;
            $reason = match (true) {
                $status !== 0 => "X ha risposto con HTTP {$status} senza confermare la pubblicazione.",
                $exception instanceof ConnectionException => 'La connessione con X si è interrotta prima della conferma.',
                default => 'X non ha confermato la pubblicazione.',
            };

            Log::warning('X post delivery uncertain', [
                'proposal_id' => $proposal->id, 'exception' => $exception::class, 'http_status' => $status ?: null,
            ]);
            $proposal->update(['status' => 'delivery_uncertain',
                'delivery_error' => $reason.' Controlla il profilo X prima di intervenire. La proposta resta in verifica per evitare duplicati.']);

            return false;
        }
    }

    private function canDeliver(EditorialProposal $proposal, bool $automatic): bool
    {
        return $proposal->status === 'delivering' && $this->hasEligibleContent($proposal)
            && (! $proposal->image_attachments || $proposal->reviewed_by_user_id !== null)
            && (! $automatic || ($proposal->site->editorialSchedule?->enabled && $proposal->site->editorialSchedule?->x_autopublish_enabled
                && $proposal->content->source->promotion_authorized_at));
    }

    private function hasEligibleContent(EditorialProposal $proposal): bool
    {
        $content = $proposal->content;
        $source = $content->source;

        return $proposal->platform === 'x' && $proposal->site->is_active
            && $content->site_id === $proposal->site_id && $source->site_id === $proposal->site_id
            && $source->is_active && $source->verification_status === 'verified'
            && $content->eligibility_status === 'eligible'
            && $source->containsPublicUrl($content->public_url) && str_starts_with($content->public_url, 'https://')
            && strtolower((string) parse_url($content->public_url, PHP_URL_HOST)) === strtolower($proposal->site->host)
            && str_ends_with(trim($proposal->post_text), $content->public_url) && mb_strlen($proposal->post_text) <= 280;
    }

    /** @return list<string> */
    private function uploadAttachments(EditorialProposal $proposal, XConnection $connection, bool $automatic): array
    {
        if (! in_array('media.write', $connection->scopes ?? [], true)) {
            throw new XApiException('Ricollega l’account X con il permesso media.write, poi approva di nuovo la proposta.');
        }

        $content = $proposal->content;
        $pages = $this->reader->chapterImages($content);
        $images = [];

        foreach ($proposal->image_attachments as $attachment) {
            $page = $pages[$attachment['id']] ?? null;

            if (! $page || $page['url'] !== $attachment['url']) {
                throw new EditorialSourceVerificationException('Una pagina selezionata è cambiata. Seleziona e approva di nuovo le immagini.');
            }

            $image = $this->reader->readChapterImage($content->source, $page['url']);

            if (! hash_equals($attachment['sha256'], hash('sha256', $image))) {
                throw new EditorialSourceVerificationException('Un’immagine selezionata è cambiata. Seleziona e approva di nuovo le immagini.');
            }

            $images[] = $image;
        }

        $mediaIds = [];

        foreach ($images as $image) {
            $activeConnection = $this->currentPublishingConnection($connection, $automatic);

            if (! $activeConnection) {
                throw new XApiException('L’account X è cambiato o la pubblicazione è stata sospesa durante il caricamento.');
            }

            if (! in_array('media.write', $activeConnection->scopes ?? [], true)) {
                throw new XApiException('Ricollega l’account X con il permesso media.write, poi approva di nuovo la proposta.');
            }

            $mediaIds[] = $this->x->uploadImage($activeConnection, $image);
        }

        return $mediaIds;
    }

    private function returnToDraft(EditorialProposal $proposal, string $message): void
    {
        EditorialProposal::query()->whereKey($proposal->id)->where('status', 'delivering')->update([
            'status' => 'draft',
            'delivery_attempted_at' => null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'delivery_error' => $message,
        ]);
    }
}
