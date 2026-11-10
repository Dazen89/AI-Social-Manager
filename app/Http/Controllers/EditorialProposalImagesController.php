<?php

namespace App\Http\Controllers;

use App\Exceptions\EditorialSourceVerificationException;
use App\Models\EditorialProposal;
use App\Models\Site;
use App\Services\EditorialProposalImages;
use App\Services\FoOlSlideSourceVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EditorialProposalImagesController extends Controller
{
    private const MAX_IMAGES = 4;

    public function __construct(private readonly EditorialProposalImages $selection) {}

    public function preview(Site $site, EditorialProposal $proposal, FoOlSlideSourceVerifier $reader): View
    {
        $this->ensureEditable($site, $proposal);

        $suggestions = [];

        if ($proposal->image_attachments === null) {
            try {
                $suggestions = $this->selection->suggestions($proposal, $reader->chapterImages($proposal->content));
            } catch (EditorialSourceVerificationException) {
                // A chapter without accessible pages has no image preview.
            }
        }

        return view('sites.partials.editorial-image-preview', [
            'attachments' => $suggestions,
            'source' => $proposal->content->source,
            'suggested' => true,
        ]);
    }

    public function edit(Site $site, EditorialProposal $proposal, FoOlSlideSourceVerifier $reader): View
    {
        $this->ensureEditable($site, $proposal);

        $images = [];
        $error = null;

        try {
            $images = $reader->chapterImages($proposal->content);
        } catch (EditorialSourceVerificationException $exception) {
            $error = $exception->getMessage();
        }

        $selectedIds = array_column($proposal->image_attachments ?? $this->selection->suggestions($proposal, $images), 'id');

        return view('sites.editorial-images', [
            'site' => $site,
            'proposal' => $proposal,
            'images' => $images,
            'imageError' => $error,
            'selectedIds' => array_slice((array) old('image_ids', $selectedIds), 0, self::MAX_IMAGES),
            'maxImages' => self::MAX_IMAGES,
        ]);
    }

    public function update(Request $request, Site $site, EditorialProposal $proposal, FoOlSlideSourceVerifier $reader): RedirectResponse
    {
        $this->ensureEditable($site, $proposal);

        $values = $request->validate([
            'image_ids' => ['sometimes', 'array', 'max:'.self::MAX_IMAGES],
            'image_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ], [
            'image_ids.max' => 'Puoi selezionare al massimo :max immagini.',
        ]);

        $ids = $values['image_ids'] ?? [];
        $attachments = [];
        $content = $proposal->content;

        if ($ids !== []) {
            $request->validate(['images_confirmed' => ['accepted']], [
                'images_confirmed.accepted' => 'Conferma che le pagine selezionate sono idonee alla promozione su X.',
            ]);

            try {
                $images = $reader->chapterImages($content);

                foreach ($ids as $id) {
                    $image = $images[$id] ?? null;

                    if (! $image) {
                        throw ValidationException::withMessages(['image_ids' => 'Una delle immagini selezionate non appartiene alle pagine disponibili del capitolo.']);
                    }

                    $attachments[] = $this->selection->capture($content, $image);
                }
            } catch (EditorialSourceVerificationException $exception) {
                throw ValidationException::withMessages(['image_ids' => $exception->getMessage()]);
            }
        }

        DB::transaction(function () use ($site, $proposal, $content, $attachments): void {
            $current = EditorialProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            $this->ensureEditable($site, $current);

            if ($current->content->public_url !== $content->public_url || ! $current->content->source->is_active) {
                throw ValidationException::withMessages(['image_ids' => 'La fonte o il capitolo è cambiato. Riapri la selezione delle immagini.']);
            }

            $current->update([
                'image_attachments' => $attachments,
                'status' => 'draft',
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
                'delivery_error' => null,
            ]);
        });

        return redirect()->route('sites.editorial-plan.show', $site)
            ->withFragment('proposal-'.$proposal->id)
            ->with('status', 'Selezione salvata. Puoi ora approvare la proposta.');
    }

    private function ensureEditable(Site $site, EditorialProposal $proposal): void
    {
        abort_unless($proposal->site_id === $site->id && $proposal->platform === 'x', 404);
        abort_unless(in_array($proposal->status, EditorialProposal::EDITABLE_STATUSES, true), 409);
    }
}
