<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveEditorialSourceRequest;
use App\Models\EditorialSource;
use App\Models\Site;
use App\Services\FoOlSlideSourceVerifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteEditorialPlanController extends Controller
{
    public function show(Request $request, Site $site): View
    {
        $requestedMonth = $request->query('month');
        $month = is_string($requestedMonth) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $requestedMonth)
            ? CarbonImmutable::createFromFormat('!Y-m-d', $requestedMonth.'-01')
            : false;
        $month = $month ?: CarbonImmutable::now()->startOfMonth();

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
            'today' => CarbonImmutable::today()->format('Y-m-d'),
            'sourcesCount' => $site->editorialSources()->count(),
        ]);
    }

    public function sources(Site $site): View
    {
        return view('sites.editorial-plan', [
            'site' => $site,
            'sources' => $site->editorialSources()->orderBy('name')->get(),
        ]);
    }

    public function store(SaveEditorialSourceRequest $request, Site $site): RedirectResponse
    {
        $site->editorialSources()->create([
            ...$request->safe()->only(['provider', 'name', 'base_url']),
            'is_active' => true,
        ]);

        return redirect()->route('sites.editorial-plan.sources.index', $site)
            ->with('status', 'Fonte FoOlSlide aggiunta. La sincronizzazione non è ancora attiva.');
    }

    public function toggle(Site $site, EditorialSource $source): RedirectResponse
    {
        abort_unless($source->site_id === $site->id, 404);

        $source->update(['is_active' => ! $source->is_active]);

        return redirect()->route('sites.editorial-plan.sources.index', $site)
            ->with('status', $source->is_active ? 'Fonte attivata.' : 'Fonte disattivata.');
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
            ];
        }

        $source->update($values);

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

        return redirect()->route('sites.editorial-plan.sources.index', $site)
            ->with($verified ? 'status' : 'verification_error', $source->verification_message);
    }
}
