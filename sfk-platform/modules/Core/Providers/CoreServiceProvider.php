<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use App\Models\User;
use App\Modules\ModuleServiceProvider;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Modules\Core\Application\Numbering\DocumentNumberGenerator;
use Modules\Core\Console\SyncSuperAdminCommand;
use Modules\Core\Http\Responses\PortalLoginResponse;

final class CoreServiceProvider extends ModuleServiceProvider
{
    protected string $slug = 'core';

    protected array $commands = [
        SyncSuperAdminCommand::class,
    ];

    protected function registerModule(): void
    {
        $this->app->singleton(DocumentNumberGenerator::class);

        // Every panel's login page leads to the dashboard selection portal.
        $this->app->bind(LoginResponse::class, PortalLoginResponse::class);
    }

    protected function bootModule(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });
    }
}
