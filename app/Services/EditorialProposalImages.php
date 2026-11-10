<?php

namespace App\Services;

use App\Models\EditorialContent;
use App\Models\EditorialProposal;

class EditorialProposalImages
{
    public function __construct(private readonly FoOlSlideSourceVerifier $reader) {}

    public function suggestions(EditorialProposal $proposal, array $pages): array
    {
        // Keep the selection stable across the overview, editor and publishing preview.
        return collect($pages)
            ->sortBy(fn (array $page) => hash('sha256', $proposal->id.':'.$page['id']))
            ->take(3)
            ->all();
    }

    public function capture(EditorialContent $content, array $page): array
    {
        $bytes = $this->reader->readChapterImage($content->source, $page['url']);

        return [...$page, 'sha256' => hash('sha256', $bytes)];
    }
}
