<?php

declare(strict_types=1);

namespace Modules\Sales\Providers;

use App\Modules\ModuleServiceProvider;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Records\RecordCards;
use Modules\Core\Filament\Records\RecordTabs;
use Modules\MasterData\Filament\Resources\Partners\Pages\ViewPartner;
use Modules\Sales\Application\Legacy\LegacyCycleReceivableImport;
use Modules\Sales\Application\Legacy\LegacyDeliveryNoteImport;
use Modules\Sales\Application\Legacy\LegacyInvoiceImport;
use Modules\Sales\Application\Legacy\LegacyReceiptImport;
use Modules\Sales\Application\Legacy\LegacyReceivableImport;
use Modules\Sales\Application\Legacy\LegacySaleImport;
use Modules\Sales\Application\SalesObserver;
use Modules\Sales\Filament\PartnerTabs;
use Modules\Sales\Models\DeliveryNote;
use Modules\Sales\Models\DeliveryNoteLine;
use Modules\Sales\Models\Invoice;
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
        // 移行 (legacy:import): 06 売上入力, 10 納品書管理, 11 請求書管理, 21 売入金管理, 23 売掛管理, 23-2 売掛管理(締日別)
        $this->app->tag([
            LegacySaleImport::class,
            LegacyDeliveryNoteImport::class,
            LegacyInvoiceImport::class,
            LegacyReceiptImport::class,
            LegacyReceivableImport::class,
            LegacyCycleReceivableImport::class,
        ], LegacyImporter::TAG);
    }

    protected function bootModule(): void
    {
        // 社内メッセージ: records shared in a message are drawn as cards (Core RecordCards, the viewer's rights apply)
        RecordCards::register('sales-entry', fn (Sale $sale): array => [
            ['field' => 'sales_number', 'label' => '売上番号', 'value' => $sale->sales_number],
            ['field' => 'customer_id', 'label' => '得意先', 'value' => $sale->customer->name],
            ['field' => 'product_name', 'label' => '品名', 'value' => $sale->product_name],
            ['field' => 'sold_on', 'label' => '売上日', 'value' => $sale->sold_on->format('Y/m/d')],
            ['field' => 'amount', 'label' => '金額', 'value' => '¥'.number_format((int) $sale->amount)],
        ]);
        RecordCards::register('invoice', fn (Invoice $invoice): array => [
            ['field' => 'number', 'label' => '請求書番号', 'value' => (string) $invoice->getAttribute('number')],
            ['field' => 'invoiced_on', 'label' => '請求日', 'value' => $invoice->invoiced_on?->format('Y/m/d')],
            ['field' => 'billed_total', 'label' => '今回請求額', 'value' => '¥'.number_format((int) $invoice->billed_total)],
            ['field' => 'send_status', 'label' => '送付状況', 'value' => $invoice->send_status],
        ]);
        RecordCards::register('delivery-note', fn (DeliveryNote $note): array => [
            ['field' => 'number', 'label' => '納品書番号', 'value' => (string) $note->getAttribute('number')],
            ['field' => 'sold_on', 'label' => '売上日', 'value' => $note->sold_on?->format('Y/m/d')],
            ['field' => 'send_status', 'label' => '送付状況', 'value' => $note->send_status],
        ]);

        // the tabs and cards of a 取引先 on its 01. 取引先マスタ record (design_ref/customer-*.png)
        PartnerTabs::register($this->app->make(RecordTabs::class), ViewPartner::TABS);

        // job totals, document totals, 入金済 and receivables follow the source records
        foreach ([Sale::class, DeliveryNoteLine::class, InvoiceLine::class, Receipt::class, ReceiptLine::class, ReceivableMonth::class] as $model) {
            $model::observe(SalesObserver::class);
        }
    }
}
