<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSiteRequest;
use App\Models\Site;
use App\Services\GoogleAnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(): View
    {
        return view('sites.index', [
            'sites' => Site::query()
                ->with('googleAnalyticsProperty.connection')
                ->orderBy('name')
                ->paginate(12),
        ]);
    }

    public function create(): View
    {
        return view('sites.create', [
            'site' => new Site,
        ]);
    }

    public function store(SaveSiteRequest $request): RedirectResponse
    {
        $site = Site::query()->create(Arr::only($request->validated(), [
            'name',
            'base_url',
            'host',
            'is_active',
        ]));

        return redirect()
            ->route('sites.edit', $site)
            ->with('status', 'Sito aggiunto. Ora puoi scegliere quali servizi collegare.');
    }

    public function edit(Site $site, GoogleAnalyticsService $analytics): View
    {
        return view('sites.edit', [
            'site' => $site->load('googleAnalyticsProperty.connection'),
            'googleAnalyticsOAuthConfigured' => $analytics->credentialsAreConfigured(),
        ]);
    }

    public function update(SaveSiteRequest $request, Site $site): RedirectResponse
    {
        $site->update(Arr::only($request->validated(), [
            'name',
            'base_url',
            'host',
            'is_active',
        ]));

        return redirect()
            ->route('sites.edit', $site)
            ->with('status', 'Dati del sito aggiornati correttamente.');
    }
}
