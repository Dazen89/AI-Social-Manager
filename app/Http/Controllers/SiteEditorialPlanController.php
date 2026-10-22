<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveEditorialSourceRequest;
use App\Models\EditorialSource;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SiteEditorialPlanController extends Controller
{
    public function show(Site $site): View
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

        return redirect()->route('sites.editorial-plan.show', $site)
            ->with('status', 'Fonte FoOlSlide aggiunta. La sincronizzazione non è ancora attiva.');
    }

    public function toggle(Site $site, EditorialSource $source): RedirectResponse
    {
        abort_unless($source->site_id === $site->id, 404);

        $source->update(['is_active' => ! $source->is_active]);

        return redirect()->route('sites.editorial-plan.show', $site)
            ->with('status', $source->is_active ? 'Fonte attivata.' : 'Fonte disattivata.');
    }

    public function update(SaveEditorialSourceRequest $request, Site $site, EditorialSource $source): RedirectResponse
    {
        abort_unless($source->site_id === $site->id, 404);

        $source->update($request->safe()->only(['provider', 'name', 'base_url']));

        return redirect()->route('sites.editorial-plan.show', $site)
            ->with('status', 'Fonte aggiornata. La sincronizzazione non è ancora attiva.');
    }
}
