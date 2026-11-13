<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveEditorialSourceRequest;
use App\Models\AiIntegration;
use App\Models\EditorialProposal;
use App\Models\EditorialSource;
use App\Models\Site;
use App\Models\XConnection;
use App\Models\XPublicationAttempt;
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
        $today = CarbonImmutable::today($timezone);
        $todayUtc = $today->setTimezone('UTC');
        $proposalDate = 'COALESCE(published_at, proposed_for)';
        $includePublished = $request->boolean('published');
        $focusedProposal = $request->integer('proposal');
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

        $visibleProposals = $site->editorialProposals()->with('content.source')
            ->where(fn ($query) => $query
                ->whereHas('content.source', fn ($source) => $source->where('is_active', true))
                ->orWhere('proposed_for', '<=', now())
                ->orWhereIn('status', ['published', 'delivery_uncertain', 'delivering', 'rejected']));
        $monthRange = [$month->startOfMonth()->setTimezone('UTC'), $month->endOfMonth()->setTimezone('UTC')];

        return view('sites.editorial-overview', [
            'site' => $site,
            'month' => $month,
            'weeks' => array_chunk($days, 7),
            'today' => $today->format('Y-m-d'),
            'schedule' => $schedule,
            'xConnection' => XConnection::query()->where('status', 'active')->first(),
            'cadence' => $site->editorialCadenceRecommendations()->latest('generated_at')->first(),
            'timezone' => $timezone,
            'sourcesCount' => $site->editorialSources()->count(),
            'recentContents' => $site->editorialContents()->with(['source', 'proposal'])->orderByDesc('released_at')->orderByDesc('id')
                ->paginate(6, ['*'], 'chapters')->withQueryString()->fragment('chapters'),
            'candidateCount' => $site->editorialContents()->whereIn('eligibility_status', ['pending', 'eligible'])
                ->whereHas('source', fn ($query) => $query->where('is_active', true)->where('verification_status', 'verified'))
                ->whereDoesntHave('proposal')->count(),
            'aiReady' => AiIntegration::query()->where('active_marker', 'active')->where('status', 'verified')->exists(),
            'includePublished' => $includePublished,
            'publicationAttempts' => XPublicationAttempt::query()
                ->whereHas('proposal', fn ($query) => $query->where('site_id', $site->id))
                ->with('proposal.content')->orderByDesc('started_at')->orderByDesc('id')
                ->paginate(10, ['*'], 'deliveries')->withQueryString()->fragment('publication-log'),
            'previousDeliveries' => $site->editorialProposals()->with('content')
                ->whereDoesntHave('publicationAttempts')
                ->where(fn ($query) => $query->whereNotNull('delivery_attempted_at')
                    ->orWhereNotNull('delivery_error')->orWhereNotNull('published_at'))
                ->orderByDesc('updated_at')->limit(10)->get(),
            'proposals' => (clone $visibleProposals)
                ->when(! $includePublished, fn ($query) => $query->where('status', '!=', 'published'))
                ->when($focusedProposal > 0, fn ($query) => $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$focusedProposal]))
                ->orderByRaw("CASE WHEN {$proposalDate} >= ? THEN 0 WHEN {$proposalDate} IS NULL THEN 2 ELSE 1 END", [$todayUtc])
                ->orderByRaw("CASE WHEN {$proposalDate} >= ? THEN {$proposalDate} END ASC", [$todayUtc])
                ->orderByRaw("{$proposalDate} DESC")
                ->orderByDesc('id')->paginate(50, ['*'], 'proposals')->withQueryString()->fragment('proposals'),
            'calendarProposals' => $visibleProposals->whereIn('status', ['draft', 'approved', 'published', 'delivery_uncertain', 'delivering', 'deferred', 'rejected'])
                ->where(fn ($query) => $query->whereBetween('published_at', $monthRange)
                    ->orWhere(fn ($planned) => $planned->whereNull('published_at')->whereBetween('proposed_for', $monthRange)))
                ->get()->groupBy(fn ($proposal) => ($proposal->published_at ?? $proposal->proposed_for)->setTimezone($timezone)->format('Y-m-d')),
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
            $site->editorialProposals()->where('status', 'approved')
                ->whereHas('content', fn ($query) => $query->where('editorial_source_id', $source->id))
                ->update(['status' => 'draft']);
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
