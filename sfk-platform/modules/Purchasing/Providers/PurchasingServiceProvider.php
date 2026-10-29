<?php

declare(strict_types=1);

namespace Modules\Purchasing\Providers;

use App\Modules\ModuleServiceProvider;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\View\TablesRenderHook;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Notifications\NotificationKind;
use Modules\Core\Application\Notifications\NotificationKinds;
use Modules\Core\Filament\Records\RelatedLists;
use Modules\Purchasing\Application\ConsumableOrders\ConsumableOrderWorkflow;
use Modules\Purchasing\Application\ConsumableOrders\ConsumableReceipt;
use Modules\Purchasing\Application\Legacy\LegacyConsumableOrderImport;
use Modules\Purchasing\Application\Legacy\LegacyFreightImport;
use Modules\Purchasing\Application\Legacy\LegacyPayableImport;
use Modules\Purchasing\Application\Legacy\LegacyPaymentImport;
use Modules\Purchasing\Application\Legacy\LegacyPurchaseImport;
use Modules\Purchasing\Application\Purchases\OverrunAlert;
use Modules\Purchasing\Application\PurchasingObserver;
use Modules\Purchasing\Filament\Resources\Payables\Pages\ListPayables;
use Modules\Purchasing\Filament\Resources\Purchases\PurchaseResource;
use Modules\Purchasing\Filament\Resources\Purchases\PurchaseRows;
use Modules\Purchasing\Models\ConsumableOrderLine;
use Modules\Purchasing\Models\PayableMonth;
use Modules\Purchasing\Models\PaymentLine;
use Modules\Purchasing\Models\Purchase;

final class PurchasingServiceProvider extends ModuleServiceProvider
{
    protected string $slug = 'purchasing';

    protected function registerModule(): void
    {
        // 移行 (legacy:import): 05 納品(仕入)入力, 15 運送請求データ取込, 20 消耗品発注, 07 支払入力, 23 買掛管理
        $this->app->tag([
            LegacyPurchaseImport::class,
            LegacyFreightImport::class,
            LegacyConsumableOrderImport::class,
            LegacyPaymentImport::class,
            LegacyPayableImport::class,
        ], LegacyImporter::TAG);
    }

    protected function bootModule(): void
    {
        // 仕入原価超過 → 通知先 (BR-PU-05); 消耗品発注 承認依頼 / 承認する → whom it goes to next (BR-PU-09)
        $kinds = $this->app->make(NotificationKinds::class);
        $kinds->register(new NotificationKind(
            key: OverrunAlert::NOTIFICATION,
            label: '仕入原価超過',
            appCode: 'purchase-entry',
            icon: Heroicon::OutlinedExclamationTriangle,
            color: 'warning',
        ));
        $kinds->register(new NotificationKind(
            key: ConsumableOrderWorkflow::NOTIFICATION,
            label: 'ステータスの変更',
            appCode: 'consumable-order',
            icon: Heroicon::OutlinedClipboardDocumentCheck,
        ));

        // job costs, 支払金額合計 and payables follow the source records
        foreach ([Purchase::class, PaymentLine::class, PayableMonth::class] as $model) {
            $model::observe(PurchasingObserver::class);
        }

        // 納品日 of a 20. 消耗品発注 row → 入庫 in the stock of 19
        ConsumableOrderLine::saved(fn (ConsumableOrderLine $line) => $this->app->make(ConsumableReceipt::class)->sync($line));
        ConsumableOrderLine::deleted(fn (ConsumableOrderLine $line) => $this->app->make(ConsumableReceipt::class)->clear($line));

        // 仕入履歴 on a 04. 受注入力 record: its purchases, newest first (legacy related list, 5 rows)
        $this->app->make(RelatedLists::class)->provide('orders.purchase_history', fn (Model $order): array => PurchaseRows::of(
            PurchaseResource::onlyVisible(Purchase::query())->with('supplier')->where('order_id', $order->getKey())->orderByDesc('id')->limit(5)->get(),
            ['purchased_on', 'purchase_number', 'supplier_id', 'product_name', 'unit_price', 'quantity', 'amount', 'cutting_amount', 'plate_amount',
                'stored_plate_amount', 'spot_color_amount'],
            PurchaseResource::class,
        ));

        // 合計 under the search row of the 23. 買掛管理 list
        FilamentView::registerRenderHook(TablesRenderHook::TOOLBAR_AFTER, fn (): string => ListPayables::totalsBar());
    }
}
