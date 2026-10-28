<?php

declare(strict_types=1);

namespace Modules\Orders\Providers;

use App\Modules\ModuleServiceProvider;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsRenderHook;
use Modules\Core\Application\Notifications\NotificationKind;
use Modules\Core\Application\Notifications\NotificationKinds;
use Modules\Orders\Application\FaxImport\ClaudeFaxOrderReader;
use Modules\Orders\Application\FaxImport\FaxOrderReader;
use Modules\Orders\Application\Orders\OrderDueCheck;
use Modules\Orders\Filament\Resources\Orders\Pages\ListOrders;

final class OrdersServiceProvider extends ModuleServiceProvider
{
    protected string $slug = 'orders';

    protected function registerModule(): void
    {
        // 受注書を読み込む: the Claude API reader (tests bind a fake)
        $this->app->bind(FaxOrderReader::class, fn (): FaxOrderReader => new ClaudeFaxOrderReader((array) config('orders.fax_import')));
    }

    protected function bootModule(): void
    {
        // 納期 of the jobs coming up → their 担当者, every morning
        $this->app->make(NotificationKinds::class)->register(new NotificationKind(
            key: OrderDueCheck::NOTIFICATION,
            label: '納期が近い受注',
            appCode: 'order-entry',
            icon: Heroicon::OutlinedCalendarDays,
            color: 'warning',
            check: OrderDueCheck::class,
        ));

        // look of the app's own parts (sub-tables, 集計サマリー, 過去の受注履歴検索, 受注書 preview)
        FilamentView::registerRenderHook(PanelsRenderHook::HEAD_END, fn (): string => view('orders::filament.styles')->render());
        // 集計サマリー under the search row of the 04. 受注入力 list
        FilamentView::registerRenderHook(TablesRenderHook::TOOLBAR_AFTER, fn (): string => ListOrders::summaryBar());
    }
}
