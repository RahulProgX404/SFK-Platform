<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receivables\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\Sales\Application\Receivables\InvoiceBalances;
use Modules\Sales\Application\Receivables\ReceivableLedger;
use Modules\Sales\Application\SalesActionNotAllowed;
use Modules\Sales\Domain\Documents\DocumentFields;
use Modules\Sales\Domain\Receivables\ReceivableBasis;
use Modules\Sales\Filament\Resources\Invoices\InvoiceResource;
use Modules\Sales\Filament\Resources\Receivables\ReceivableMonthResource;
use Modules\Sales\Filament\SalesFormat;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\ReceivableMonth;

/**
 * A month of 23. 売掛管理 / 23-2. 売掛管理(締日別). 「…」 has 再集計 (legacy detail button: the customer's months
 * recalculated now, e.g. after its 端数区分 changed) and, on 23-2 for administrators, 請求書更新 (legacy detail button,
 * Customine 23-2 page 9): the balances go into the chosen invoice of the 月度 (InvoiceBalances).
 *
 * @property ReceivableMonth $record
 */
abstract class ViewReceivableMonth extends AppViewRecord
{
    public function getTitle(): string
    {
        return ReceivableMonthResource::titleOf($this->record);
    }

    protected function getHeaderActions(): array
    {
        return array_map(function (Action|ActionGroup $action): Action|ActionGroup {
            if ($action instanceof ActionGroup) {
                $action->actions([$this->recalculateAction(), $this->updateInvoiceAction(), ...$action->getActions()]);
            }

            return $action;
        }, parent::getHeaderActions());
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return DocumentFields::RECEIVABLE;
    }

    private function recalculateAction(): Action
    {
        return Action::make('recalculate')
            ->label('再集計')
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (): bool => $this->historyVersion === null && static::getResource()::canEdit($this->record))
            ->action(function (): void {
                app(ReceivableLedger::class)->refresh($this->record->customer_id);
                Notification::make()->success()->title('再集計しました')->send();

                $fresh = ReceivableMonth::query()->find($this->record->getKey());
                $this->redirect($fresh !== null ? static::getResource()::getUrl('view', ['record' => $fresh]) : static::getResource()::getUrl('index'));
            });
    }

    private function updateInvoiceAction(): Action
    {
        return Action::make('updateInvoice')
            ->label('請求書更新')
            ->icon(Heroicon::OutlinedDocumentCurrencyYen)
            ->visible(fn (): bool => $this->historyVersion === null && $this->record->basis === ReceivableBasis::Cycle
                && auth()->user() instanceof User && auth()->user()->user_type->isAdministrator())
            ->modalHeading('請求書を更新しますか？')
            ->modalSubmitActionLabel('更新する')
            ->modalCancelActionLabel('キャンセル')
            ->schema([
                Select::make('invoice_id')
                    ->label('請求書番号')
                    ->native()
                    ->placeholder('-----')
                    ->options(fn (): array => app(InvoiceBalances::class)->invoicesFor($this->record)
                        ->mapWithKeys(fn (Invoice $invoice): array => [(string) $invoice->id => $invoice->number.'（'.SalesFormat::date($invoice->invoiced_on).'）'])
                        ->all())
                    ->default(fn (): ?string => ($id = app(InvoiceBalances::class)->invoicesFor($this->record)->first()?->id) === null ? null : (string) $id)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $invoice = Invoice::query()->find((int) ($data['invoice_id'] ?? 0));
                if ($invoice === null) {
                    return;
                }

                try {
                    app(InvoiceBalances::class)->apply($this->record, $invoice);
                } catch (SalesActionNotAllowed $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('請求書を更新しました')->send();
                if (InvoiceResource::canView($invoice)) {
                    $this->redirect(InvoiceResource::getUrl('view', ['record' => $invoice]));
                }
            });
    }
}
