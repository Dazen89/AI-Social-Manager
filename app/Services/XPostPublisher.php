<?php

namespace App\Services;

use App\Models\EditorialProposal;
use App\Models\XConnection;
use Illuminate\Support\Facades\Log;
use Throwable;

class XPostPublisher
{
    public function __construct(private readonly XService $x) {}

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
            $content = $proposal->content;
            $source = $content->source;

            if (! $proposal->site->is_active || ! $proposal->site->editorialSchedule?->enabled
                || ! $proposal->site->editorialSchedule?->x_autopublish_enabled
                || ! $source->is_active || $source->verification_status !== 'verified'
                || ! $source->promotion_authorized_at || $content->eligibility_status !== 'eligible'
                || ! $source->containsPublicUrl($content->public_url)
                || ! str_starts_with($content->public_url, 'https://')
                || strtolower((string) parse_url($content->public_url, PHP_URL_HOST)) !== strtolower($proposal->site->host)
                || ! str_ends_with(trim($proposal->post_text), $content->public_url)
                || mb_strlen($proposal->post_text) > 280) {
                $proposal->update(['status' => 'draft', 'delivery_attempted_at' => null,
                    'delivery_error' => 'Pubblicazione bloccata: verifica fonte, idoneità, URL e testo.']);

                continue;
            }

            try {
                $xPostId = $this->x->createPost($connection, $proposal->post_text);
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
}
