<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Modules\Core\Application\Users\UserAccountService;

/**
 * The signed-in user changes their own password. Required after the first sign-in and after every
 * password set by an administrator (see EnsurePasswordIsChanged).
 */
final class PasswordController
{
    public function edit(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->guest((string) Filament::getDefaultPanel()->getLoginUrl());
        }

        return view('core::password', [
            'user' => $user,
            'forced' => $user->must_change_password,
            'logoutUrl' => Filament::getDefaultPanel()->getLogoutUrl(),
        ]);
    }

    public function update(Request $request, UserAccountService $accounts): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->guest((string) Filament::getDefaultPanel()->getLoginUrl());
        }

        if ($user->managed_by_env) {
            return back()->withErrors(['password' => 'このアカウントのパスワードは環境変数で管理されています。']);
        }

        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ]);

        $accounts->changeOwnPassword($user, $data['password']);

        // Keep this session valid for AuthenticateSession, which compares the stored password hash.
        $guard = Auth::guard('web');
        $hash = method_exists($guard, 'hashPasswordForCookie')
            ? $guard->hashPasswordForCookie($user->getAuthPassword())
            : $user->getAuthPassword();
        $request->session()->put('password_hash_'.Auth::getDefaultDriver(), $hash);

        return redirect()->route('core.portal')->with('status', 'パスワードを変更しました。');
    }
}
