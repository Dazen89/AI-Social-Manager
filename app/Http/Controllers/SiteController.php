<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSiteRequest;
use App\Models\Site;
use App\Services\GoogleAnalyticsService;
use App\Services\GoogleSearchConsoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(): View
    {
        return view('sites.index', [
            'sites' => Site::query()
                ->with([
                    'googleAnalyticsProperty.connection',
                    'googleSearchConsoleProperty.connection',
                ])
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

    public function edit(
        Site $site,
        GoogleAnalyticsService $analytics,
        GoogleSearchConsoleService $searchConsole,
    ): View {
        return view('sites.edit', [
            'site' => $site->load([
                'googleAnalyticsProperty.connection',
                'googleSearchConsoleProperty.connection',
            ]),
            'googleAnalyticsOAuthConfigured' => $analytics->credentialsAreConfigured(),
            'googleSearchConsoleOAuthConfigured' => $searchConsole->credentialsAreConfigured(),
        ]);
    }

    public function update(SaveSiteRequest $request, Site $site): RedirectResponse
    {
        $values = Arr::only($request->validated(), [
            'name',
            'base_url',
            'host',
            'is_active',
        ]);

        $hostChanged = $site->host !== $values['host'];

        if ($hostChanged) {
            $site->editorialSources()->update([
                'verification_status' => 'not_checked',
                'verification_message' => null,
                'last_checked_at' => null,
                'last_verified_at' => null,
            ]);
        }

        $site->update($values);

        return redirect()
            ->route('sites.edit', $site)
            ->with('status', $hostChanged
                ? 'Dominio aggiornato. Controlla l’URL della fonte editoriale e verifica nuovamente l’API.'
                : 'Dati del sito aggiornati correttamente.');
    }
}
