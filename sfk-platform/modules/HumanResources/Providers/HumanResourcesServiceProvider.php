<?php

declare(strict_types=1);

namespace Modules\HumanResources\Providers;

use App\Models\User;
use App\Modules\ModuleServiceProvider;
use Filament\Support\Icons\Heroicon;
use Livewire\Livewire;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Notifications\NotificationKind;
use Modules\Core\Application\Notifications\NotificationKinds;
use Modules\HumanResources\Application\AlcoholChecks\AlcoholAlert;
use Modules\HumanResources\Application\LeaveRequests\LeaveRequestWorkflow;
use Modules\HumanResources\Application\Legacy\LegacyAlcoholCheckImport;
use Modules\HumanResources\Application\Legacy\LegacyLeaveRequestImport;
use Modules\HumanResources\Application\Legacy\LegacySafetyCheckImport;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\QuickEntry\QuickAlcoholCheck;
use Modules\HumanResources\Filament\Resources\LeaveRequests\QuickEntry\QuickLeaveRequest;
use Modules\HumanResources\Filament\Resources\SafetyChecks\QuickEntry\QuickSafetyCheck;
use Modules\HumanResources\Models\AlcoholCheck;

final class HumanResourcesServiceProvider extends ModuleServiceProvider
{
    protected string $slug = 'hr';

    protected function registerModule(): void
    {
        // 移行 (legacy:import): 16 申請管理, 17 安全衛生チェック, 18 アルコールチェック
        $this->app->tag([LegacyLeaveRequestImport::class, LegacySafetyCheckImport::class, LegacyAlcoholCheckImport::class], LegacyImporter::TAG);
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

        // 新規 of 16 / 17 / 18: their quick entries over the lists (Core QuickEntry)
        Livewire::component('hr.quick-leave-request', QuickLeaveRequest::class);
        Livewire::component('hr.quick-safety-check', QuickSafetyCheck::class);
        Livewire::component('hr.quick-alcohol-check', QuickAlcoholCheck::class);
    }
}
