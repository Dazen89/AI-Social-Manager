<?php

namespace App\Services;

use App\Exceptions\EditorialSourceVerificationException;
use App\Exceptions\XApiException;
use App\Models\EditorialProposal;
use App\Models\XConnection;
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
        if (! config('services.x.auto_publish_allowed')
            || ! (app()->environment('production') || (app()->environment('testing') && config('services.x.test_fake_publish')))) {
            return 0;
        }

        $connection = XConnection::query()->where('status', 'active')->first();

        if (! $connection || ! in_array('tweet.write', $connection->scopes ?? [], true)) {
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
            $claimed = EditorialProposal::query()->whereKey($id)->where('status', 'approved')
                ->whereNull('delivery_attempted_at')->update([
                    'status' => 'delivering',
                    'delivery_attempted_at' => now(),
                ]);

            if ($claimed !== 1) {
                continue;
            }

            $proposal = EditorialProposal::query()->with('site.editorialSchedule', 'content.source')->findOrFail($id);

            if (! $this->canPublish($proposal)) {
                $this->returnToDraft($proposal, 'Pubblicazione bloccata. Verifica fonte, idoneità, URL e approvazione.');

                continue;
            }

            try {
                $mediaIds = $proposal->image_attachments ? $this->uploadAttachments($proposal, $connection) : [];
            } catch (EditorialSourceVerificationException|XApiException $exception) {
                $this->returnToDraft($proposal, $exception->getMessage());

                continue;
            } catch (Throwable) {
                $this->returnToDraft($proposal, 'Caricamento delle immagini non riuscito. Verifica gli allegati e approva di nuovo la proposta.');

                continue;
            }

            if ($mediaIds !== [] && ! $this->canPublish($proposal->refresh())) {
                $this->returnToDraft($proposal, 'La fonte o l’idoneità è cambiata durante il caricamento. Verifica e approva di nuovo la proposta.');

                continue;
            }

            try {
                $xPostId = $this->x->createPost($connection, $proposal->post_text, $mediaIds);
                $proposal->update(['status' => 'published', 'x_post_id' => $xPostId,
                    'published_at' => now(), 'delivery_error' => null]);
                $published++;
            } catch (Throwable $exception) {
                Log::warning('X post delivery uncertain', ['proposal_id' => $id, 'exception' => $exception::class]);
                $proposal->update(['status' => 'delivery_uncertain',
                    'delivery_error' => 'X non ha confermato la pubblicazione. Controlla l’account prima di intervenire: il sistema non ritenta automaticamente per evitare duplicati.']);
            }
        }

        return $published;
    }

    private function canPublish(EditorialProposal $proposal): bool
    {
        $content = $proposal->content;
        $source = $content->source;

        return $proposal->status === 'delivering' && $proposal->site->is_active
            && $proposal->site->editorialSchedule?->enabled && $proposal->site->editorialSchedule?->x_autopublish_enabled
            && $source->is_active && $source->verification_status === 'verified' && $source->promotion_authorized_at
            && $content->eligibility_status === 'eligible'
            && $source->containsPublicUrl($content->public_url) && str_starts_with($content->public_url, 'https://')
            && strtolower((string) parse_url($content->public_url, PHP_URL_HOST)) === strtolower($proposal->site->host)
            && str_ends_with(trim($proposal->post_text), $content->public_url) && mb_strlen($proposal->post_text) <= 280
            && (! $proposal->image_attachments || $proposal->reviewed_by_user_id !== null);
    }

    /** @return list<string> */
    private function uploadAttachments(EditorialProposal $proposal, XConnection $connection): array
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

        return array_map(fn (string $image): string => $this->x->uploadImage($connection, $image), $images);
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
