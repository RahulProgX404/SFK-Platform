<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The single login page of the whole system (/login). Dashboards have no login page of their own: after
 * signing in, users go to the dashboard selection portal (or the page they first asked for), so it no
 * longer matters which dashboard they logged out from.
 */
final class LoginController
{
    private const MAX_ATTEMPTS = 5;

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('core.portal');
        }

        return view('core::login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::lower((string) $credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors(['email' => "ログインの試行回数が多すぎます。{$seconds}秒後にもう一度お試しください。"])->onlyInput('email');
        }

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey);

            return back()->withErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。'])->onlyInput('email');
        }

        $user = Auth::guard('web')->user();

        if ($user instanceof User && ! $user->isSuperAdmin() && ! $user->employment_status->canSignIn()) {
            $this->signOut($request);

            return back()->withErrors(['email' => 'このアカウントではログインできません。'])->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->intended(route('core.portal'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->signOut($request);

        return redirect()->route('login');
    }

    private function signOut(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
