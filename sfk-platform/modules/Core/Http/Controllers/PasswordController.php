<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Modules\Core\Application\Users\UserAccountService;

/**
 * Forced password change after the first sign-in and after every password set by an administrator (see
 * EnsurePasswordIsChanged). The user just signed in with the temporary password, so only the new password
 * is asked for — or they keep the temporary one. Voluntary changes happen on the profile page.
 */
final class PasswordController
{
    public function edit(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->must_change_password) {
            return redirect()->route('core.portal');
        }

        return view('core::password', ['user' => $user]);
    }

    public function update(Request $request, UserAccountService $accounts): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->must_change_password) {
            return redirect()->route('core.portal');
        }

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $accounts->changeOwnPassword($user, $data['password']);
        $this->keepSessionValid($request, $user);

        return redirect()->route('core.portal')->with('status', 'パスワードを変更しました。');
    }

    /** 仮パスワードをそのまま使う: keep the temporary password and stop asking. */
    public function keep(Request $request, UserAccountService $accounts): RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User && $user->must_change_password) {
            $accounts->keepTemporaryPassword($user);
        }

        return redirect()->route('core.portal');
    }

    /** AuthenticateSession compares the stored password hash; refresh it after a change. */
    private function keepSessionValid(Request $request, User $user): void
    {
        $guard = Auth::guard('web');
        $hash = method_exists($guard, 'hashPasswordForCookie')
            ? $guard->hashPasswordForCookie($user->getAuthPassword())
            : $user->getAuthPassword();
        $request->session()->put('password_hash_'.Auth::getDefaultDriver(), $hash);
    }
}
