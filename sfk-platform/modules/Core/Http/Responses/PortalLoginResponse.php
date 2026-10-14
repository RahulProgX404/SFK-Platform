<?php

declare(strict_types=1);

namespace Modules\Core\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Illuminate\Http\RedirectResponse;

/**
 * After signing in on any panel's login page, open the dashboard selection portal (or the page the
 * user originally asked for).
 */
final class PortalLoginResponse implements LoginResponse
{
    public function toResponse($request): RedirectResponse
    {
        return redirect()->intended(route('core.portal'));
    }
}
