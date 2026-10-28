<?php

declare(strict_types=1);

namespace Modules\HumanResources\Providers;

use App\Models\User;
use App\Modules\ModuleServiceProvider;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Application\Notifications\NotificationKind;
use Modules\Core\Application\Notifications\NotificationKinds;
use Modules\HumanResources\Application\AlcoholChecks\AlcoholAlert;
use Modules\HumanResources\Application\LeaveRequests\LeaveRequestWorkflow;
use Modules\HumanResources\Models\AlcoholCheck;

final class HumanResourcesServiceProvider extends ModuleServiceProvider
{
    protected string $slug = 'hr';

    protected function registerModule(): void
    {
        // Bind module services / repositories here.
    }

    protected function bootModule(): void
    {
        // 申請する / 承認済 / 差戻し → whom the request goes to next (BR-HR-01); 酒気帯び 有 → administrators (BR-HR-04)
        $kinds = $this->app->make(NotificationKinds::class);
        $kinds->register(new NotificationKind(
            key: LeaveRequestWorkflow::NOTIFICATION,
            label: 'ステータスの変更',
            appCode: 'leave-request',
            icon: Heroicon::OutlinedClipboardDocumentCheck,
        ));
        $kinds->register(new NotificationKind(
            key: AlcoholAlert::NOTIFICATION,
            label: '酒気帯び運転の有無: 有',
            appCode: 'alcohol-check',
            forAdministrators: true,
            icon: Heroicon::OutlinedExclamationTriangle,
            color: 'danger',
        ));

        $alert = function (AlcoholCheck $check, bool $created): void {
            $actor = auth()->user();
            $this->app->make(AlcoholAlert::class)->saved($check, $actor instanceof User ? $actor : null, $created);
        };
        AlcoholCheck::created(fn (AlcoholCheck $check) => $alert($check, true));
        AlcoholCheck::updated(fn (AlcoholCheck $check) => $alert($check, false));
    }
}
