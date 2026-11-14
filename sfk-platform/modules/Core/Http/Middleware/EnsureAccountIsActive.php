<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out a user whose 在籍状況 no longer lets them sign in (security review 2026-11-14): the login page refuses them,
 * but a session that was open — or the 「ログインしたままにする」 cookie — kept working on the routes outside the
 * dashboards (帳票出力 /documents, /portal, Livewire updates). On every request of the `web` group and of every panel;
 * the user is the one the request loads anyway (no extra query on a signed-in page). Super admins (environment
 * accounts) always stay.
 */
final class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->isSuperAdmin() && ! $user->employment_status->canSignIn()) {
            // logout() also forgets the remember-me cookie and changes the user's remember token
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
                abort(401);
            }

            return redirect()->route('login')->withErrors(['email' => 'このアカウントではログインできません。']);
        }

        return $next($request);
    }
}
