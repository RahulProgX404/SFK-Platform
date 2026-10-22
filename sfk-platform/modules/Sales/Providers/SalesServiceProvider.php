<?php

declare(strict_types=1);

namespace Modules\Sales\Providers;

use App\Modules\ModuleServiceProvider;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Modules\Sales\Application\SalesObserver;
use Modules\Sales\Filament\Resources\Receivables\Pages\ListReceivableMonths;
use Modules\Sales\Filament\Resources\Sales\Pages\ListSales;
use Modules\Sales\Models\DeliveryNoteLine;
use Modules\Sales\Models\InvoiceLine;
use Modules\Sales\Models\Receipt;
use Modules\Sales\Models\ReceiptLine;
use Modules\Sales\Models\ReceivableMonth;
use Modules\Sales\Models\Sale;

final class SalesServiceProvider extends ModuleServiceProvider
{
    protected string $slug = 'sales';

    protected function registerModule(): void
    {
        // Bind module services / repositories here.
    }

    protected function bootModule(): void
    {
        // job totals, document totals, 入金済 and receivables follow the source records
        foreach ([Sale::class, DeliveryNoteLine::class, InvoiceLine::class, Receipt::class, ReceiptLine::class, ReceivableMonth::class] as $model) {
            $model::observe(SalesObserver::class);
        }

        // 集計サマリー under the search row of the 06. 売上入力 list
        FilamentView::registerRenderHook(TablesRenderHook::TOOLBAR_AFTER, fn (): string => ListSales::summaryBar());

        // 合計 under the search row of the 23. 売掛管理 / 23-2. 売掛管理(締日別) lists
        FilamentView::registerRenderHook(TablesRenderHook::TOOLBAR_AFTER, fn (): string => ListReceivableMonths::totalsBar());
    }
}
