<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'accounts' => User::query()->orderByDesc('is_admin')->orderBy('name')->orderBy('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('users.form', ['account' => new User]);
    }

    public function store(SaveUserRequest $request): RedirectResponse
    {
        $values = $request->validated();
        $values['password'] = Hash::make($values['password']);

        DB::transaction(function () use ($request, $values): void {
            $this->lockAccount($request);
            User::query()->create([...$values, 'is_admin' => true]);
        }, attempts: 3);

        return redirect()->route('users.index')->with('status', 'Account amministratore creato.');
    }

    public function edit(User $user): View
    {
        return view('users.form', ['account' => $user]);
    }

    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        $password = $request->validated('password');
        $passwordHash = $password === null ? null : Hash::make($password);

        $account = DB::transaction(function () use ($request, $user, $passwordHash): User {
            $account = $this->lockAccount($request, $user);
            $account->fill($request->safe()->except('password'));

            if ($account->isDirty('email')) {
                $account->email_verified_at = null;
            }

            if ($passwordHash !== null) {
                $account->password = $passwordHash;
                $account->invalidateSessions();
            }

            $account->save();

            return $account;
        }, attempts: 3);

        if ($passwordHash !== null && $account->is($request->user())) {
            $request->session()->put(User::SESSION_VERSION_KEY, $account->session_version);
            $request->session()->regenerate(true);
        }

        return redirect()->route('users.index')->with('status', 'Account aggiornato.');
    }

    public function updateAccess(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403);
        $values = $request->validate(['enabled' => ['required', 'boolean']]);
        $enabled = (bool) $values['enabled'];

        DB::transaction(function () use ($request, $user, $enabled): void {
            $account = $this->lockAccount($request, $user);

            if ($account->is_admin !== $enabled) {
                $account->is_admin = $enabled;
                $account->invalidateSessions();
                $account->save();
            }
        }, attempts: 3);

        return redirect()->route('users.index')->with('status', $enabled ? 'Accesso riattivato.' : 'Accesso sospeso.');
    }

    private function lockAccount(Request $request, ?User $account = null): User
    {
        $actor = $request->user();
        $targetId = $account?->id ?? $actor->id;
        // Lock both accounts in the same order if administrators act on each other.
        $users = User::query()->whereKey([$actor->id, $targetId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $administrator = $users->get($actor->id);

        abort_unless($administrator?->is_admin && (int) $administrator->session_version === (int) $actor->session_version, 403);

        return $users->get($targetId) ?? abort(404);
    }
}
