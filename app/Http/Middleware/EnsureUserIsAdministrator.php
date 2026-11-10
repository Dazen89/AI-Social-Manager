<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdministrator
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, Response::HTTP_FORBIDDEN);

        $guard = Auth::guard();
        $version = (int) $user->session_version;

        if ($guard->viaRemember()) {
            $request->session()->put(User::SESSION_VERSION_KEY, $version);
        }

        if ($request->session()->get(User::SESSION_VERSION_KEY, 0) !== $version) {
            $guard->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->guest(route('login'));
        }

        abort_unless($user->is_admin, Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
