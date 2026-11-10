<?php

namespace App\Http\Controllers;

use App\Exceptions\EditorialSourceVerificationException;
use App\Exceptions\XApiException;
use App\Models\EditorialProposal;
use App\Models\Site;
use App\Services\EditorialProposalImages;
use App\Services\FoOlSlideSourceVerifier;
use App\Services\XPostPublisher;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

class EditorialProposalPublishController extends Controller
{
    public function __construct(private readonly XPostPublisher $publisher) {}

    public function confirm(Request $request, Site $site, EditorialProposal $proposal, FoOlSlideSourceVerifier $reader, EditorialProposalImages $selection): View
    {
        $this->ensureSite($site, $proposal);
        abort_unless(in_array($proposal->status, EditorialProposal::EDITABLE_STATUSES, true), 409);

        $connection = $this->publisher->writingConnection();
        $suggested = $proposal->image_attachments === null;
        $attachments = $proposal->image_attachments ?? [];
        $problem = $this->publisher->manualPublicationProblem($proposal, $connection, $attachments);
        $confirmation = null;

        try {
            if ($suggested) {
                $attachments = array_values($selection->suggestions($proposal, $reader->chapterImages($proposal->content)));
                $problem ??= $this->publisher->manualPublicationProblem($proposal, $connection, $attachments);
            }

            if ($problem === null) {
                if ($suggested) {
                    $attachments = array_map(fn (array $page) => $selection->capture($proposal->content, $page), $attachments);
                }

                $confirmation = Crypt::encrypt([
                    'proposal' => $proposal->id,
                    'user' => $request->user()->id,
                    'account' => $connection->x_user_id,
                    'expires' => now()->addMinutes(15)->timestamp,
                    'revision' => $this->publisher->manualRevision($proposal),
                    'attachments' => $attachments,
                ]);
            }
        } catch (EditorialSourceVerificationException $exception) {
            $problem ??= $exception->getMessage();
        }

        return view('sites.editorial-publish', compact('site', 'proposal', 'connection', 'suggested', 'attachments', 'confirmation', 'problem'));
    }

    public function store(Request $request, Site $site, EditorialProposal $proposal): RedirectResponse
    {
        $this->ensureSite($site, $proposal);

        $validated = $request->validate(['confirmation' => ['required', 'string']]);
        $overview = redirect()->route('sites.editorial-plan.show', $site);

        try {
            $preview = Crypt::decrypt($validated['confirmation']);
        } catch (DecryptException) {
            return $overview->with('workflow_error', 'La conferma è scaduta o non valida. Riapri «Pubblica ora».');
        }

        $connection = $this->publisher->writingConnection();

        if (! $connection || ($preview['proposal'] ?? null) !== $proposal->id || $preview['user'] !== $request->user()->id
            || $preview['account'] !== $connection->x_user_id || $preview['expires'] <= now()->timestamp) {
            return $overview->with('workflow_error', 'La conferma è scaduta o l’account X è cambiato. Riapri «Pubblica ora».');
        }

        if ($preview['attachments']) {
            $request->validate(['images_confirmed' => ['accepted']], [
                'images_confirmed.accepted' => 'Conferma che puoi usare le immagini per la promozione su X.',
            ]);
        }

        try {
            $published = $this->publisher->publishNow($proposal, $connection, $request->user()->id, $preview['revision'], $preview['attachments']);
        } catch (XApiException $exception) {
            return $overview->with('workflow_error', $exception->getMessage());
        }

        return $published->status === 'published'
            ? $overview->withFragment('proposal-'.$proposal->id)->with('status', 'Post pubblicato su X.')
            : $overview->withFragment('proposal-'.$proposal->id)->with('workflow_error', $published->delivery_error);
    }

    private function ensureSite(Site $site, EditorialProposal $proposal): void
    {
        abort_unless($proposal->site_id === $site->id && $proposal->platform === 'x', 404);
    }
}
