<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveEditorialSourceRequest;
use App\Models\AiIntegration;
use App\Models\EditorialProposal;
use App\Models\EditorialSource;
use App\Models\Site;
use App\Models\XConnection;
use App\Services\FoOlSlideSourceVerifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SiteEditorialPlanController extends Controller
{
    public function show(Request $request, Site $site): View
    {
        $schedule = $site->editorialSchedule;
        $timezone = $schedule?->timezone ?? (string) config('app.timezone');
        $requestedMonth = $request->query('month');
        $month = is_string($requestedMonth) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $requestedMonth)
            ? CarbonImmutable::createFromFormat('!Y-m-d', $requestedMonth.'-01', $timezone)
            : false;
        $month = $month ?: CarbonImmutable::now($timezone)->startOfMonth();

        $days = array_fill(0, $month->dayOfWeekIso - 1, null);

        for ($day = 1; $day <= $month->daysInMonth; $day++) {
            $days[] = $day;
        }

        while (count($days) % 7 !== 0) {
            $days[] = null;
        }

        return view('sites.editorial-overview', [
            'site' => $site,
            'month' => $month,
            'weeks' => array_chunk($days, 7),
            'today' => CarbonImmutable::today($timezone)->format('Y-m-d'),
            'schedule' => $schedule,
            'xConnection' => XConnection::query()->where('status', 'active')->first(),
            'cadence' => $site->editorialCadenceRecommendations()->latest('generated_at')->first(),
            'timezone' => $timezone,
            'sourcesCount' => $site->editorialSources()->count(),
            'recentContents' => $site->editorialContents()->with(['source', 'proposal'])->orderByDesc('released_at')->orderByDesc('id')->paginate(30, ['*'], 'chapters')->withQueryString(),
            'candidateCount' => $site->editorialContents()->whereIn('eligibility_status', ['pending', 'eligible'])
                ->whereHas('source', fn ($query) => $query->where('is_active', true)->where('verification_status', 'verified'))
                ->whereDoesntHave('proposal')->count(),
            'aiReady' => AiIntegration::query()->where('active_marker', 'active')->where('status', 'verified')->exists(),
            'proposals' => $site->editorialProposals()->with('content')->orderByDesc('created_at')->limit(50)->get(),
            'calendarProposals' => $site->editorialProposals()->with('content')->whereIn('status', ['draft', 'approved', 'published', 'delivery_uncertain'])
                ->whereBetween('proposed_for', [
                    $month->startOfMonth()->setTimezone('UTC'),
                    $month->endOfMonth()->setTimezone('UTC'),
                ])
                ->get()->groupBy(fn ($proposal) => $proposal->proposed_for->setTimezone($timezone)->format('Y-m-d')),
        ]);
    }

    public function contents(Site $site): View
    {
        return view('sites.editorial-contents', [
            'site' => $site,
            'contents' => $site->editorialContents()->with(['source', 'proposal'])->orderByDesc('released_at')->orderByDesc('id')->paginate(30),
        ]);
    }

    public function sources(Site $site): View
    {
        return view('sites.editorial-plan', [
            'site' => $site,
            'sources' => $site->editorialSources()->withCount('contents')->orderBy('name')->get(),
        ]);
    }

    public function store(SaveEditorialSourceRequest $request, Site $site): RedirectResponse
    {
        $site->editorialSources()->create([
            ...$request->safe()->only(['provider', 'name', 'base_url']),
            'is_active' => true,
        ]);

        return redirect()->route('sites.editorial-plan.sources.index', $site)
            ->with('status', 'Fonte FoOlSlide aggiunta. Verifica l’API, poi importa le uscite.');
    }

    public function toggle(Site $site, EditorialSource $source): RedirectResponse
    {
        abort_unless($source->site_id === $site->id, 404);

        $source->update(['is_active' => ! $source->is_active]);

        if ($source->is_active && $source->verification_status === 'verified' && $site->editorialSchedule?->enabled) {
            $site->editorialSchedule->update(['next_run_at' => now()]);
        }

        return redirect()->route('sites.editorial-plan.sources.index', $site)
            ->with('status', $source->is_active ? 'Fonte attivata.' : 'Fonte disattivata.');
    }

    public function promotionAuthorization(Request $request, Site $site, EditorialSource $source): RedirectResponse
    {
        abort_unless($source->site_id === $site->id, 404);

        $values = $request->validate(['authorized' => ['required', 'boolean']]);

        if ($values['authorized'] && (! $source->is_active || $source->verification_status !== 'verified')) {
            return back()->with('verification_error', 'Attiva e verifica la fonte prima di autorizzarne la promozione automatica.');
        }

        $source->update([
            'promotion_authorized_at' => $values['authorized'] ? now() : null,
            'promotion_authorized_by_user_id' => $values['authorized'] ? $request->user()->id : null,
        ]);

        if ($values['authorized']) {
            $source->contents()->where('eligibility_status', 'pending')->update([
                'eligibility_status' => 'eligible',
                'eligibility_confirmed_by_user_id' => $request->user()->id,
            ]);
        }

        if (! $values['authorized']) {
            $source->contents()->whereHas('proposal', fn ($query) => $query->where('status', 'approved'))
                ->get()->each(fn ($content) => $content->proposal->update(['status' => 'draft']));
        }

        return back()->with('status', $values['authorized']
            ? 'Promozione automatica autorizzata per questa fonte. I post verranno inviati solo se attivi anche la pubblicazione X del sito in produzione.'
            : 'Autorizzazione alla promozione automatica revocata. I post non ancora inviati tornano bozze.');
    }

    public function update(SaveEditorialSourceRequest $request, Site $site, EditorialSource $source): RedirectResponse
    {
        abort_unless($source->site_id === $site->id, 404);

        $values = $request->safe()->only(['provider', 'name', 'base_url']);

        if ($source->base_url !== $values['base_url']) {
            $values += [
                'verification_status' => 'not_checked',
                'verification_message' => null,
                'last_checked_at' => null,
                'last_verified_at' => null,
                'promotion_authorized_at' => null,
                'promotion_authorized_by_user_id' => null,
            ];
        }

        DB::transaction(function () use ($source, $values): void {
            $source->update($values);

            if (array_key_exists('verification_status', $values)) {
                $source->contents()->update([
                    'eligibility_status' => 'pending',
                    'eligibility_confirmed_by_user_id' => null,
                ]);
                EditorialProposal::query()->whereIn('editorial_content_id', $source->contents()->select('id'))
                    ->where('status', 'approved')->update([
                        'status' => 'draft',
                        'reviewed_by_user_id' => null,
                        'reviewed_at' => null,
                    ]);
            }
        });

        return redirect()->route('sites.editorial-plan.sources.index', $site)
            ->with('status', 'Fonte aggiornata. Se hai cambiato l’URL, verifica nuovamente l’API.');
    }

    public function verify(Site $site, EditorialSource $source, FoOlSlideSourceVerifier $verifier): RedirectResponse
    {
        abort_unless($source->site_id === $site->id, 404);

        if (! $source->is_active) {
            return redirect()->route('sites.editorial-plan.sources.index', $site)
                ->with('verification_error', 'Riattiva la fonte prima di verificarla.');
        }

        $verified = $verifier->verify($source);

        if ($verified && $source->site->editorialSchedule?->enabled) {
            $source->site->editorialSchedule->update(['next_run_at' => now()]);
        }

        return redirect()->route('sites.editorial-plan.sources.index', $site)
            ->with($verified ? 'status' : 'verification_error', $source->verification_message);
    }
}
