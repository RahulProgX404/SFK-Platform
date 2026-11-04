<?php

declare(strict_types=1);

namespace Modules\Orders\Providers;

use App\Modules\ModuleServiceProvider;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Notifications\NotificationKind;
use Modules\Core\Application\Notifications\NotificationKinds;
use Modules\Core\Filament\Records\RecordTabs;
use Modules\MasterData\Filament\Resources\Partners\Pages\ViewPartner;
use Modules\Orders\Application\FaxImport\ClaudeFaxOrderReader;
use Modules\Orders\Application\FaxImport\FaxOrderReader;
use Modules\Orders\Application\Legacy\LegacyOrderImport;
use Modules\Orders\Application\Legacy\LegacyQuotationImport;
use Modules\Orders\Application\Orders\OrderDueCheck;
use Modules\Orders\Filament\PartnerTabs;

final class OrdersServiceProvider extends ModuleServiceProvider
{
    protected string $slug = 'orders';

    protected function registerModule(): void
    {
        // 受注書を読み込む: the Claude API reader (tests bind a fake)
        $this->app->bind(FaxOrderReader::class, fn (): FaxOrderReader => new ClaudeFaxOrderReader((array) config('orders.fax_import')));

        // 移行 (legacy:import): 09 見積書管理, 04 受注入力
        $this->app->tag([LegacyQuotationImport::class, LegacyOrderImport::class], LegacyImporter::TAG);
    }

    protected function bootModule(): void
    {
        // the tabs and cards of a 取引先 on its 01. 取引先マスタ record (design_ref/customer-*.png)
        PartnerTabs::register($this->app->make(RecordTabs::class), ViewPartner::TABS);

        // 納期 of the jobs coming up → their 担当者, every morning
        $this->app->make(NotificationKinds::class)->register(new NotificationKind(
            key: OrderDueCheck::NOTIFICATION,
            label: '納期が近い受注',
            appCode: 'order-entry',
            icon: Heroicon::OutlinedCalendarDays,
            color: 'warning',
            check: OrderDueCheck::class,
        ));

        // look of the app's own parts (sub-tables, 過去の受注履歴検索, 受注書 preview)
        FilamentView::registerRenderHook(PanelsRenderHook::HEAD_END, fn (): string => view('orders::filament.styles')->render());
    }
}
