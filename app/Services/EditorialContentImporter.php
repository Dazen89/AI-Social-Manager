<?php

namespace App\Services;

use App\Exceptions\EditorialSourceVerificationException;
use App\Models\EditorialSource;
use Carbon\CarbonImmutable;
use Throwable;

class EditorialContentImporter
{
    public function __construct(private readonly FoOlSlideSourceVerifier $reader) {}

    /** @return array{seen: int, added: int} */
    public function import(EditorialSource $source): array
    {
        if (! $source->is_active || $source->verification_status !== 'verified') {
            throw new EditorialSourceVerificationException('Attiva e verifica la fonte prima di importare le uscite.');
        }

        $seen = 0;
        $added = 0;

        // Check both bounded pages: a filtered FoOlSlide page can contain fewer
        // than 20 entries even when another page exists.
        for ($page = 1; $page <= 2; $page++) {
            $chapters = $this->reader->chapterPage($source, $page);

            if ($chapters === []) {
                break;
            }

            foreach ($chapters as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $content = $this->normalized($source, $entry);

                if ($content === null) {
                    continue;
                }

                $seen++;
                $item = $source->contents()->firstOrCreate(
                    ['external_id' => $content['external_id']],
                    [
                        ...$content, 'site_id' => $source->site_id, 'last_seen_at' => now(),
                        'eligibility_status' => $source->promotion_authorized_at ? 'eligible' : 'pending',
                        'eligibility_confirmed_by_user_id' => $source->promotion_authorized_by_user_id,
                    ],
                );

                if ($item->wasRecentlyCreated) {
                    $added++;
                } else {
                    $urlChanged = $item->public_url !== $content['public_url'];
                    $item->update([
                        'series_title' => $content['series_title'],
                        'title' => $content['title'],
                        'public_url' => $content['public_url'],
                        'released_at' => $content['released_at'],
                        'last_seen_at' => now(),
                        ...($urlChanged ? [
                            'eligibility_status' => $source->promotion_authorized_at ? 'eligible' : 'pending',
                            'eligibility_confirmed_by_user_id' => $source->promotion_authorized_by_user_id,
                        ] : []),
                    ]);

                    if ($urlChanged && $item->proposal?->status === 'approved') {
                        $item->proposal->update(['status' => 'draft', 'reviewed_by_user_id' => null, 'reviewed_at' => null]);
                    }
                }
            }

        }

        return compact('seen', 'added');
    }

    /** @param array<string, mixed> $entry
     * @return array<string, mixed>|null
     */
    private function normalized(EditorialSource $source, array $entry): ?array
    {
        $comic = $entry['comic'] ?? null;
        $chapter = $entry['chapter'] ?? null;

        if (! is_array($comic) || ! is_array($chapter)
            || (int) ($comic['hidden'] ?? 0) !== 0
            || (int) ($chapter['hidden'] ?? 0) !== 0) {
            return null;
        }

        $id = filter_var($chapter['id'] ?? null, FILTER_VALIDATE_INT);
        $series = trim(strip_tags((string) ($comic['name'] ?? '')));
        $title = trim(strip_tags((string) ($chapter['title'] ?? $chapter['name'] ?? '')));
        $url = (string) ($chapter['href'] ?? '');

        if (! $id || $series === '' || $title === '' || ! $source->containsPublicUrl($url)) {
            return null;
        }

        $releasedAt = null;

        if (filled($chapter['created'] ?? null)) {
            try {
                $releasedAt = CarbonImmutable::parse((string) $chapter['created']);
            } catch (Throwable) {
                // An invalid date is unknown, never a reason to fabricate freshness.
            }
        }

        return [
            'external_id' => (string) $id,
            'series_title' => mb_strimwidth($series, 0, 255, ''),
            'title' => mb_strimwidth($title, 0, 255, ''),
            'public_url' => $url,
            'released_at' => $releasedAt,
        ];
    }
}
