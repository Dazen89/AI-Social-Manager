<?php

namespace App\Http\Controllers;

use App\Exceptions\GoogleSearchConsoleException;
use App\Models\GoogleSearchConsoleConnection;
use App\Models\Site;
use App\Services\GoogleSearchConsoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class GoogleSearchConsoleConnectionController extends Controller
{
    public function connect(Request $request, Site $site, GoogleSearchConsoleService $searchConsole): RedirectResponse
    {
        if (! $searchConsole->credentialsAreConfigured()) {
            return redirect()
                ->route('sites.edit', $site)
                ->with('integration_error', 'Prima di collegare Search Console configura le credenziali OAuth dell’installazione.');
        }

        $this->discardPendingSelection($request, $site, $searchConsole);

        $state = Str::random(64);

        $request->session()->put('google_search_console_oauth', [
            'state_hash' => hash('sha256', $state),
            'site_id' => $site->id,
            'user_id' => $request->user()->id,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        return redirect()->away($searchConsole->authorizationUrl($state));
    }

    public function callback(Request $request, GoogleSearchConsoleService $searchConsole): RedirectResponse
    {
        $context = $request->session()->pull('google_search_console_oauth');
        $site = is_array($context) ? Site::query()->find($context['site_id'] ?? null) : null;

        if (! $site || ! $this->validState($context, (string) $request->query('state'))) {
            return redirect()
                ->route('sites.index')
                ->with('integration_error', 'La richiesta di collegamento Search Console è scaduta o non è valida. Riprova dal sito.');
        }

        if ($request->filled('error')) {
            return redirect()
                ->route('sites.edit', $site)
                ->with('integration_error', 'Il collegamento Search Console è stato annullato o non autorizzato.');
        }

        $code = (string) $request->query('code');

        if ($code === '') {
            return redirect()
                ->route('sites.edit', $site)
                ->with('integration_error', 'Google non ha restituito il codice necessario al collegamento.');
        }

        try {
            $tokens = $searchConsole->exchangeAuthorizationCode($code);

            $connection = GoogleSearchConsoleConnection::query()->create([
                'authorized_by_user_id' => $request->user()->id,
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'token_expires_at' => now()->addSeconds($tokens['expires_in']),
                'scopes' => filled($tokens['scope'] ?? null)
                    ? explode(' ', $tokens['scope'])
                    : [config('services.google_search_console.scope')],
                'status' => 'active',
            ]);
        } catch (GoogleSearchConsoleException $exception) {
            return redirect()
                ->route('sites.edit', $site)
                ->with('integration_error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('sites.edit', $site)
                ->with('integration_error', 'Non è stato possibile completare il collegamento con Search Console. Riprova.');
        }

        $request->session()->put('google_search_console_selection', [
            'site_id' => $site->id,
            'connection_id' => $connection->id,
        ]);

        return redirect()->route('google-search-console.properties', $site);
    }

    public function properties(Request $request, Site $site, GoogleSearchConsoleService $searchConsole): View|RedirectResponse
    {
        $connection = $this->selectionConnection($request, $site);

        if (! $connection) {
            return redirect()
                ->route('sites.edit', $site)
                ->with('integration_error', 'Avvia nuovamente il collegamento per scegliere una proprietà Search Console.');
        }

        try {
            $properties = $searchConsole->sites($connection, $site->host);
        } catch (GoogleSearchConsoleException $exception) {
            return redirect()
                ->route('sites.edit', $site)
                ->with('integration_error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('sites.edit', $site)
                ->with('integration_error', 'Search Console non è raggiungibile. Riprova più tardi.');
        }

        return view('sites.integrations.google-search-console.properties', [
            'site' => $site,
            'properties' => $properties,
        ]);
    }

    public function store(Request $request, Site $site, GoogleSearchConsoleService $searchConsole): RedirectResponse
    {
        $connection = $this->selectionConnection($request, $site);

        if (! $connection) {
            return redirect()
                ->route('sites.edit', $site)
                ->with('integration_error', 'La sessione di collegamento è scaduta. Riprova.');
        }

        $validated = $request->validate([
            'property' => ['required', 'string', 'max:2048'],
        ], [
            'property.required' => 'Scegli una proprietà Search Console.',
            'property.max' => 'La proprietà selezionata non è valida.',
        ]);

        try {
            $properties = $searchConsole->sites($connection, $site->host);
            $property = collect($properties)->firstWhere('site_url', $validated['property']);

            if (! is_array($property)) {
                return back()->withErrors([
                    'property' => 'La proprietà non è accessibile con l’account Google autorizzato.',
                ]);
            }

            $previousConnectionId = $site->googleSearchConsoleProperty?->google_search_console_connection_id;

            DB::transaction(function () use ($site, $connection, $property): void {
                $site->googleSearchConsoleProperty()->updateOrCreate([], [
                    'google_search_console_connection_id' => $connection->id,
                    'property_url' => $property['site_url'],
                    'property_type' => $property['property_type'],
                    'permission_level' => $property['permission_level'],
                    'last_verified_at' => now(),
                ]);
            });

            $this->removeUnusedConnection($previousConnectionId, $connection->id, $searchConsole);
        } catch (GoogleSearchConsoleException $exception) {
            return back()->with('integration_error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('integration_error', 'Search Console non è raggiungibile. Riprova più tardi.');
        }

        $request->session()->forget('google_search_console_selection');

        $message = $property['matches_host']
            ? 'Google Search Console collegata e proprietà verificata per il dominio del sito.'
            : 'Google Search Console collegata. La proprietà scelta non coincide esattamente con il dominio registrato: controlla la selezione.';

        return redirect()->route('sites.edit', $site)->with('status', $message);
    }

    public function cancel(Request $request, Site $site, GoogleSearchConsoleService $searchConsole): RedirectResponse
    {
        $this->discardPendingSelection($request, $site, $searchConsole);

        return redirect()
            ->route('sites.edit', $site)
            ->with('status', 'Collegamento Search Console annullato. Nessun token provvisorio è stato conservato.');
    }

    public function destroy(Site $site, GoogleSearchConsoleService $searchConsole): RedirectResponse
    {
        $property = $site->googleSearchConsoleProperty;

        if (! $property) {
            return redirect()->route('sites.edit', $site);
        }

        $connection = $property->connection;
        $property->delete();

        if ($connection && ! $connection->properties()->exists()) {
            try {
                $searchConsole->revoke($connection);
            } catch (Throwable $exception) {
                report($exception);
            }

            $connection->delete();
        }

        return redirect()
            ->route('sites.edit', $site)
            ->with('status', 'Google Search Console scollegata. I token locali sono stati rimossi.');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function validState(array $context, string $state): bool
    {
        return $state !== ''
            && isset($context['state_hash'], $context['user_id'], $context['expires_at'])
            && hash_equals((string) $context['state_hash'], hash('sha256', $state))
            && (int) $context['user_id'] === (int) auth()->id()
            && (int) $context['expires_at'] >= now()->timestamp;
    }

    private function selectionConnection(Request $request, Site $site): ?GoogleSearchConsoleConnection
    {
        $selection = $request->session()->get('google_search_console_selection');

        if (! is_array($selection) || (int) ($selection['site_id'] ?? 0) !== $site->id) {
            return null;
        }

        return GoogleSearchConsoleConnection::query()
            ->whereKey($selection['connection_id'] ?? null)
            ->where('authorized_by_user_id', $request->user()->id)
            ->first();
    }

    private function removeUnusedConnection(?int $previousConnectionId, int $currentConnectionId, GoogleSearchConsoleService $searchConsole): void
    {
        if (! $previousConnectionId || $previousConnectionId === $currentConnectionId) {
            return;
        }

        $connection = GoogleSearchConsoleConnection::query()->find($previousConnectionId);

        if (! $connection || $connection->properties()->exists()) {
            return;
        }

        try {
            $searchConsole->revoke($connection);
        } catch (Throwable $exception) {
            report($exception);
        }

        $connection->delete();
    }

    private function discardPendingSelection(Request $request, Site $site, GoogleSearchConsoleService $searchConsole): void
    {
        $connection = $this->selectionConnection($request, $site);
        $request->session()->forget('google_search_console_selection');

        if (! $connection || $connection->properties()->exists()) {
            return;
        }

        try {
            $searchConsole->revoke($connection);
        } catch (Throwable $exception) {
            report($exception);
        }

        $connection->delete();
    }
}
