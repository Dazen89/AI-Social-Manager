<?php

namespace App\Http\Controllers;

use App\Exceptions\AiServiceException;
use App\Exceptions\EditorialSourceVerificationException;
use App\Models\EditorialContent;
use App\Models\EditorialProposal;
use App\Models\EditorialSource;
use App\Models\Site;
use App\Services\EditorialContentImporter;
use App\Services\EditorialProposalService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditorialWorkflowController extends Controller
{
    public function import(Site $site, EditorialSource $source, EditorialContentImporter $importer): RedirectResponse
    {
        abort_unless($source->site_id === $site->id, 404);

        try {
            $result = $importer->import($source);
        } catch (EditorialSourceVerificationException $exception) {
            return back()->with('verification_error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('verification_error', 'Importazione non riuscita. Controlla la fonte e riprova.');
        }

        return back()->with('status', "Uscite lette: {$result['seen']}. Nuove: {$result['added']}. Conferma l’idoneità prima di generare proposte.");
    }

    public function eligibility(Request $request, Site $site, EditorialContent $content): RedirectResponse
    {
        abort_unless($content->site_id === $site->id, 404);

        $values = $request->validate(['status' => ['required', Rule::in(['eligible', 'excluded'])]]);

        if ($values['status'] === 'eligible' && (! $content->source->is_active || $content->source->verification_status !== 'verified'
            || ! $content->source->containsPublicUrl($content->public_url))) {
            throw ValidationException::withMessages(['status' => 'La fonte deve essere attiva e verificata.']);
        }

        $content->update([
            'eligibility_status' => $values['status'],
            'eligibility_confirmed_by_user_id' => $request->user()->id,
        ]);

        if ($values['status'] === 'excluded' && $content->proposal?->status === 'approved') {
            $content->proposal->update(['status' => 'rejected', 'reviewed_by_user_id' => $request->user()->id, 'reviewed_at' => now()]);
        }

        return back()->with('status', 'Idoneità aggiornata.');
    }

    public function generate(Site $site, EditorialProposalService $generator): RedirectResponse
    {
        try {
            $count = $generator->generate($site);
        } catch (AiServiceException $exception) {
            return back()->with('workflow_error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('workflow_error', 'Generazione non riuscita. Nessuna proposta è stata pubblicata.');
        }

        return back()->with('status', "Create {$count} bozze. Rivedile prima di approvarle: nessun post viene pubblicato automaticamente.");
    }

    public function update(Request $request, Site $site, EditorialProposal $proposal): RedirectResponse
    {
        abort_unless($proposal->site_id === $site->id, 404);
        abort_if(in_array($proposal->status, ['published', 'delivering', 'delivery_uncertain'], true), 409);

        $values = $request->validate([
            'post_text' => ['required', 'string', 'max:280'],
            'proposed_for' => ['required', 'date_format:Y-m-d\TH:i'],
        ]);

        $timezone = $site->editorialSchedule?->timezone ?? (string) config('app.timezone');
        $proposedFor = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $values['proposed_for'], $timezone)->setTimezone('UTC');

        if ($proposedFor <= CarbonImmutable::now('UTC')) {
            throw ValidationException::withMessages(['proposed_for' => 'Scegli una data futura nel fuso orario del piano.']);
        }

        if (! str_ends_with(trim($values['post_text']), $proposal->content->public_url)) {
            throw ValidationException::withMessages(['post_text' => 'Il testo deve terminare con il link della fonte.']);
        }

        $proposal->update([
            'post_text' => trim($values['post_text']),
            'proposed_for' => $proposedFor,
            'status' => 'draft',
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'manual_override' => true,
        ]);

        return back()->with('status', 'Bozza aggiornata. È necessaria una nuova approvazione.');
    }

    public function review(Request $request, Site $site, EditorialProposal $proposal): RedirectResponse
    {
        abort_unless($proposal->site_id === $site->id, 404);
        abort_if(in_array($proposal->status, ['published', 'delivering', 'delivery_uncertain'], true), 409);

        $values = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])]]);

        if ($values['status'] === 'approved') {
            $content = $proposal->content;

            if ($content->eligibility_status !== 'eligible' || ! $content->source->is_active || $content->source->verification_status !== 'verified'
                || ! $content->source->containsPublicUrl($content->public_url)
                || ! str_ends_with(trim($proposal->post_text), $content->public_url)) {
                throw ValidationException::withMessages(['status' => 'L’uscita non è idonea, la fonte non è verificata o il link della bozza è cambiato.']);
            }
        }

        $proposal->update([
            'status' => $values['status'],
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('status', $values['status'] === 'approved'
            ? 'Proposta approvata. La pubblicazione su X non è ancora attiva.'
            : 'Proposta scartata.');
    }
}
