<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends users whose password was set by an administrator (first sign-in or reset) to the password
 * change page before they can use the portal or any panel. Logging out stays possible.
 */
final class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user instanceof User
            && $user->must_change_password
            && ! $request->routeIs('core.password.*', 'filament.*.auth.logout')
        ) {
            if ($request->expectsJson()) {
                abort(403, 'パスワードの変更が必要です。');
            }

            return redirect()->route('core.password.edit');
        }

        return $next($request);
    }
}
