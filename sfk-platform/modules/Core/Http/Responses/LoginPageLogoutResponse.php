<?php

declare(strict_types=1);

namespace Modules\Core\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Illuminate\Http\RedirectResponse;

/** Logging out of any dashboard returns to the single login page of the system (/login). */
final class LoginPageLogoutResponse implements LogoutResponse
{
    public function toResponse($request): RedirectResponse
    {
        return redirect()->route('login');
    }
}
