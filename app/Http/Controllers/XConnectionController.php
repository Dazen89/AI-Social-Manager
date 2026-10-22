<?php

namespace App\Http\Controllers;

use App\Exceptions\XApiException;
use App\Models\XConnection;
use App\Services\XService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class XConnectionController extends Controller
{
    public function connect(Request $request, XService $x): RedirectResponse
    {
        if (! $x->credentialsAreConfigured()) {
            return redirect()
                ->route('settings.x.edit')
                ->with('integration_error', 'Prima di collegare un account X configura le credenziali OAuth dell’app.');
        }

        $state = Str::random(64);
        $codeVerifier = Str::random(96);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $request->session()->put('x_oauth', [
            'state_hash' => hash('sha256', $state),
            'code_verifier' => $codeVerifier,
            'user_id' => $request->user()->id,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        return redirect()->away($x->authorizationUrl($state, $codeChallenge));
    }

    public function callback(Request $request, XService $x): RedirectResponse
    {
        $context = $request->session()->pull('x_oauth');

        if (! $this->validState($context, (string) $request->query('state'), (int) $request->user()->id)) {
            return redirect()
                ->route('settings.x.edit')
                ->with('integration_error', 'La richiesta di collegamento X è scaduta o non è valida. Riprova.');
        }

        if ($request->filled('error')) {
            return redirect()
                ->route('settings.x.edit')
                ->with('integration_error', 'Il collegamento a X è stato annullato o non autorizzato.');
        }

        $code = (string) $request->query('code');

        if ($code === '') {
            return redirect()
                ->route('settings.x.edit')
                ->with('integration_error', 'X non ha restituito il codice necessario al collegamento.');
        }

        try {
            $tokens = $x->exchangeAuthorizationCode($code, (string) $context['code_verifier']);
            $account = $x->authenticatedAccount($tokens['access_token']);
            $connection = XConnection::query()->first() ?? new XConnection;

            $connection->fill([
                ...$account,
                'authorized_by_user_id' => $request->user()->id,
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'token_expires_at' => now()->addSeconds($tokens['expires_in']),
                'scopes' => preg_split('/\s+/', trim($tokens['scope'])) ?: [],
                'status' => 'active',
                'last_verified_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (XApiException $exception) {
            return redirect()
                ->route('settings.x.edit')
                ->with('integration_error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('settings.x.edit')
                ->with('integration_error', 'Non è stato possibile completare il collegamento con X. Riprova.');
        }

        return redirect()
            ->route('settings.x.edit')
            ->with('status', 'Account @'.$connection->username.' collegato e verificato.');
    }

    public function destroy(XService $x): RedirectResponse
    {
        $connection = XConnection::query()->first();

        if (! $connection) {
            return redirect()->route('settings.x.edit');
        }

        try {
            $revoked = $x->revoke($connection);
        } catch (Throwable $exception) {
            report($exception);
            $revoked = false;
        }

        $connection->delete();

        return redirect()
            ->route('settings.x.edit')
            ->with(
                'status',
                $revoked
                    ? 'Account X scollegato e autorizzazione revocata.'
                    : 'Account X scollegato localmente. Se necessario, revoca anche l’app dalle impostazioni di sicurezza di X.',
            );
    }

    /**
     * @param  mixed  $context
     */
    private function validState($context, string $state, int $userId): bool
    {
        return is_array($context)
            && filled($state)
            && filled($context['code_verifier'] ?? null)
            && hash_equals((string) ($context['state_hash'] ?? ''), hash('sha256', $state))
            && (int) ($context['user_id'] ?? 0) === $userId
            && (int) ($context['expires_at'] ?? 0) >= now()->timestamp;
    }
}
