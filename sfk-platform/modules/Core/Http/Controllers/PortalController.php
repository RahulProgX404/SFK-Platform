<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Http\Portal\DashboardDirectory;

/**
 * Entry point after sign-in: lists the dashboards the user may open. A user with exactly one
 * dashboard goes straight to it.
 */
final class PortalController
{
    public function __invoke(Request $request, DashboardDirectory $dashboards): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->guest(route('login'));
        }

        $entries = $dashboards->for($user);

        if (count($entries) === 1 && ! $request->session()->has('status')) {
            return redirect()->to($entries[0]->url);
        }

        return view('core::portal', [
            'user' => $user,
            'dashboards' => $entries,
        ]);
    }
}
