<?php

namespace App\Http\Controllers;

use App\Exceptions\GoogleAnalyticsException;
use App\Models\GoogleAnalyticsConnection;
use App\Models\Site;
use App\Services\GoogleAnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class GoogleAnalyticsConnectionController extends Controller
{
    public function connect(Request $request, Site $site, GoogleAnalyticsService $analytics): RedirectResponse
    {
        if (! $analytics->oauthClientCredentialsAreConfigured()) {
            return redirect()
                ->route('sites.edit', $site)
                ->with('integration_error', 'Prima di collegare Google Analytics configura le credenziali OAuth dell’installazione.');
        }

        $this->discardPendingSelection($request, $site, $analytics);

        $state = Str::random(64);

        session()->put('google_analytics_oauth', [
            'state_hash' => hash('sha256', $state),
            'site_id' => $site->id,
            'user_id' => auth()->id(),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        return redirect()->away($analytics->authorizationUrl($state));
    }

    public function callback(Request $request, GoogleAnalyticsService $analytics): RedirectResponse
    {
        $context = $request->session()->pull('google_analytics_oauth');
        $site = is_array($context) ? Site::query()->find($context['site_id'] ?? null) : null;

        if (! $site || ! $this->validState($context, (string) $request->query('state'))) {
            return redirect()
                ->route('sites.index')
                ->with('integration_error', 'La richiesta di collegamento Google è scaduta o non è valida. Riprova dal sito.');
        }

        if ($request->filled('error')) {
            return redirect()
                ->route('sites.edit', $site)
                ->with('integration_error', 'Il collegamento Google è stato annullato o non autorizzato.');
        }

        $code = (string) $request->query('code');

        if ($code === '') {
            return redirect()
                ->route('sites.edit', $site)
                ->with('integration_error', 'Google non ha restituito il codice necessario al collegamento.');
        }

        try {
            $tokens = $analytics->exchangeAuthorizationCode($code);

            $connection = GoogleAnalyticsConnection::query()->create([
                'authorized_by_user_id' => $request->user()->id,
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'token_expires_at' => now()->addSeconds($tokens['expires_in']),
                'scopes' => filled($tokens['scope'] ?? null) ? explode(' ', $tokens['scope']) : [config('services.google_analytics.scope')],
                'status' => 'active',
            ]);
        } catch (GoogleAnalyticsException $exception) {
            return redirect()
                ->route('sites.edit', $site)
                ->with('integration_error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('sites.edit', $site)
                ->with('integration_error', 'Non è stato possibile completare il collegamento con Google. Riprova.');
        }

        $request->session()->put('google_analytics_selection', [
            'site_id' => $site->id,
            'connection_id' => $connection->id,
        ]);

        return redirect()->route('google-analytics.properties', $site);
    }

    public function properties(Request $request, Site $site, GoogleAnalyticsService $analytics): View|RedirectResponse
    {
        $connection = $this->selectionConnection($request, $site);

        if (! $connection) {
            return redirect()
                ->route('sites.edit', $site)
                ->with('integration_error', 'Avvia nuovamente il collegamento per scegliere una proprietà Google Analytics.');
        }

        try {
            $accounts = $analytics->accountSummaries($connection);
        } catch (GoogleAnalyticsException $exception) {
            return redirect()
                ->route('sites.edit', $site)
                ->with('integration_error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('sites.edit', $site)
                ->with('integration_error', 'Google Analytics non è raggiungibile. Riprova più tardi.');
        }

        return view('sites.integrations.google-analytics.properties', [
            'site' => $site,
            'accounts' => $accounts,
        ]);
    }

    public function store(Request $request, Site $site, GoogleAnalyticsService $analytics): RedirectResponse
    {
        $connection = $this->selectionConnection($request, $site);

        if (! $connection) {
            return redirect()
                ->route('sites.edit', $site)
                ->with('integration_error', 'La sessione di collegamento è scaduta. Riprova.');
        }

        $validated = $request->validate([
            'property' => ['required', 'string', 'regex:/^\d+:\d+$/'],
        ], [
            'property.required' => 'Scegli una proprietà Google Analytics.',
            'property.regex' => 'La proprietà selezionata non è valida.',
        ]);

        [$accountId, $propertyId] = explode(':', $validated['property'], 2);

        try {
            $accounts = $analytics->accountSummaries($connection);
            $account = collect($accounts)->firstWhere('account_id', $accountId);
            $property = is_array($account)
                ? collect($account['properties'])->firstWhere('property_id', $propertyId)
                : null;

            if (! is_array($account) || ! is_array($property)) {
                return back()->withErrors([
                    'property' => 'La proprietà non è accessibile con l’account Google autorizzato.',
                ]);
            }

            $stream = $analytics->matchingWebStream($connection, $propertyId, $site->host);
            $previousConnectionId = $site->googleAnalyticsProperty?->google_analytics_connection_id;

            DB::transaction(function () use ($site, $connection, $account, $property, $stream): void {
                $site->googleAnalyticsProperty()->updateOrCreate([], [
                    'google_analytics_connection_id' => $connection->id,
                    'account_id' => $account['account_id'],
                    'account_name' => $account['account_name'],
                    'property_id' => $property['property_id'],
                    'property_name' => $property['property_name'],
                    'data_stream_id' => $stream['data_stream_id'] ?? null,
                    'measurement_id' => $stream['measurement_id'] ?? null,
                    'last_verified_at' => now(),
                ]);
            });

            $this->removeUnusedConnection($previousConnectionId, $connection->id, $analytics);
        } catch (GoogleAnalyticsException $exception) {
            return back()->with('integration_error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('integration_error', 'Google Analytics non è raggiungibile. Riprova più tardi.');
        }

        $request->session()->forget('google_analytics_selection');

        $message = $stream
            ? 'Google Analytics collegato e stream web verificato.'
            : 'Google Analytics collegato. Non è stato trovato uno stream web con lo stesso dominio del sito.';

        return redirect()->route('sites.edit', $site)->with('status', $message);
    }

    public function cancel(Request $request, Site $site, GoogleAnalyticsService $analytics): RedirectResponse
    {
        $this->discardPendingSelection($request, $site, $analytics);

        return redirect()
            ->route('sites.edit', $site)
            ->with('status', 'Collegamento Google Analytics annullato. Nessun token provvisorio è stato conservato.');
    }

    public function destroy(Site $site, GoogleAnalyticsService $analytics): RedirectResponse
    {
        $property = $site->googleAnalyticsProperty;

        if (! $property) {
            return redirect()->route('sites.edit', $site);
        }

        $connection = $property->connection;
        $property->delete();

        if ($connection && ! $connection->properties()->exists()) {
            try {
                $analytics->revoke($connection);
            } catch (Throwable $exception) {
                report($exception);
            }

            $connection->delete();
        }

        return redirect()
            ->route('sites.edit', $site)
            ->with('status', 'Google Analytics scollegato. I token locali sono stati rimossi.');
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

    private function selectionConnection(Request $request, Site $site): ?GoogleAnalyticsConnection
    {
        $selection = $request->session()->get('google_analytics_selection');

        if (! is_array($selection) || (int) ($selection['site_id'] ?? 0) !== $site->id) {
            return null;
        }

        return GoogleAnalyticsConnection::query()
            ->whereKey($selection['connection_id'] ?? null)
            ->where('authorized_by_user_id', $request->user()->id)
            ->first();
    }

    private function removeUnusedConnection(?int $previousConnectionId, int $currentConnectionId, GoogleAnalyticsService $analytics): void
    {
        if (! $previousConnectionId || $previousConnectionId === $currentConnectionId) {
            return;
        }

        $connection = GoogleAnalyticsConnection::query()->find($previousConnectionId);

        if (! $connection || $connection->properties()->exists()) {
            return;
        }

        try {
            $analytics->revoke($connection);
        } catch (Throwable $exception) {
            report($exception);
        }

        $connection->delete();
    }

    private function discardPendingSelection(Request $request, Site $site, GoogleAnalyticsService $analytics): void
    {
        $connection = $this->selectionConnection($request, $site);
        $request->session()->forget('google_analytics_selection');

        if (! $connection || $connection->properties()->exists()) {
            return;
        }

        try {
            $analytics->revoke($connection);
        } catch (Throwable $exception) {
            report($exception);
        }

        $connection->delete();
    }
}
