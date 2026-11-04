<?php

declare(strict_types=1);

namespace Modules\MasterData\Providers;

use App\Modules\ModuleServiceProvider;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Notifications\NotificationKind;
use Modules\Core\Application\Notifications\NotificationKinds;
use Modules\MasterData\Application\Consumables\ReorderAlert;
use Modules\MasterData\Application\Legacy\LegacyConsumableImport;
use Modules\MasterData\Application\Legacy\LegacyEmployeeImport;
use Modules\MasterData\Application\Legacy\LegacyMachineImport;
use Modules\MasterData\Application\Legacy\LegacyPartnerImporter;
use Modules\MasterData\Application\Legacy\LegacyStockImport;
use Modules\MasterData\Models\Consumable;
use Modules\MasterData\Models\ConsumableStockMovement;

final class MasterDataServiceProvider extends ModuleServiceProvider
{
    protected string $slug = 'master-data';

    protected function registerModule(): void
    {
        // 移行 (legacy:import): 02 従業員マスタ, 03 機械マスタ, 01 取引先マスタ, 19 消耗品マスタ (+ the legacy 13 在庫管理)
        $this->app->tag([
            LegacyEmployeeImport::class,
            LegacyMachineImport::class,
            LegacyPartnerImporter::class,
            LegacyConsumableImport::class,
            LegacyStockImport::class,
        ], LegacyImporter::TAG);
    }

    protected function bootModule(): void
    {
        // 要発注 of 19. 消耗品マスタ → administrators (BR-MD-05): when the stock or the 発注点 changes, and daily
        $this->app->make(NotificationKinds::class)->register(new NotificationKind(
            key: ReorderAlert::NOTIFICATION,
            label: '要発注',
            appCode: 'consumables-master',
            forAdministrators: true,
            icon: Heroicon::OutlinedArchiveBox,
            color: 'warning',
            check: ReorderAlert::class,
        ));
        ConsumableStockMovement::saved(function (ConsumableStockMovement $movement): void {
            $alert = $this->app->make(ReorderAlert::class);
            $alert->refresh($movement->consumable_id);
            if ($movement->wasChanged('consumable_id')) {
                $alert->refresh((int) $movement->getPrevious()['consumable_id']);
            }
        });
        ConsumableStockMovement::deleted(fn (ConsumableStockMovement $movement) => $this->app->make(ReorderAlert::class)->refresh($movement->consumable_id));
        Consumable::saved(function (Consumable $consumable): void {
            if ($consumable->wasRecentlyCreated || $consumable->wasChanged('reorder_point')) {
                $this->app->make(ReorderAlert::class)->refresh($consumable->id);
            }
        });
    }
}
