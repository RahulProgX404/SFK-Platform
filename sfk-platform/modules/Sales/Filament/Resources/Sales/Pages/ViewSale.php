<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Sales\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\Sales\Application\Documents\DeliveryNoteIssuer;
use Modules\Sales\Application\Documents\SaleDocumentRefresh;
use Modules\Sales\Application\SalesActionNotAllowed;
use Modules\Sales\Domain\Sales\SaleFields;
use Modules\Sales\Filament\Resources\DeliveryNotes\DeliveryNoteResource;
use Modules\Sales\Filament\Resources\Sales\SaleResource;
use Modules\Sales\Filament\Resources\Sales\Schemas\SaleForm;
use Modules\Sales\Models\Sale;

/**
 * A sale of 06. 売上入力 with the legacy record buttons for administrators (menuActions()) and their 機能説明 (page 22):
 *
 * - 納品書発行あり (Customine 06 page 5): a delivery note of this sale alone when it has none yet, then the
 *   note's print view in a new tab;
 * - 納品レコード更新 / 請求レコード更新 (pages 14 / 15): the sale's row on its delivery note / invoice written again from the
 *   sale (SaleDocumentRefresh); a sale whose row is gone is set back to 未作成.
 *
 * @property Sale $record
 */
final class ViewSale extends AppViewRecord
{
    protected static string $resource = SaleResource::class;

    public function getTitle(): string
    {
        return (string) $this->record->sales_number;
    }

    /** The legacy record buttons, for administrators (Customine 06 pages 5 / 14 / 15). */
    protected function menuActions(): array
    {
        return [$this->issueDeliveryNoteAction(), $this->refreshDeliveryNoteAction(), $this->refreshInvoiceAction()];
    }

    /**
     * 機能説明 of the buttons, for administrators (Customine 06 page 22).
     *
     * @return array<string, string>
     */
    public function menuHelp(): array
    {
        return SaleForm::isAdministrator() ? [
            '納品書発行あり' => 'このレコードのみの納品書を発行します。（納品レコードが作成されてない場合はレコードの作成まで行います。※納品レコードのデータ更新は行われません）',
            '納品レコード更新' => 'この売上データが入力された納品レコードの更新を行います。',
            '請求レコード更新' => 'この売上データが入力された請求レコードの更新を行います。',
        ] : [];
    }

    private function issueDeliveryNoteAction(): Action
    {
        return Action::make('issueDeliveryNote')
            ->label('納品書発行あり')
            ->icon(Heroicon::OutlinedPrinter)
            ->visible(fn (): bool => SaleForm::isAdministrator())
            ->requiresConfirmation()
            ->modalHeading('この売上データのみの納品書を発行しますか？')
            ->modalSubmitActionLabel('発行する')
            ->action(function (): void {
                $note = $this->record->deliveryNote;
                if ($note === null) {
                    try {
                        $note = app(DeliveryNoteIssuer::class)->issue([$this->record])[0];
                    } catch (SalesActionNotAllowed $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();

                        return;
                    }
                    Notification::make()->success()->title('納品書レコード未作成だったため新規作成しました。')
                        ->body('納品書番号：'.$note->number)->send();
                    $this->record->refresh();
                }

                $this->js('window.open('.json_encode(DeliveryNoteResource::getUrl('view', ['record' => $note, 'print' => 1])).', "_blank")');
            });
    }

    private function refreshDeliveryNoteAction(): Action
    {
        return Action::make('refreshDeliveryNote')
            ->label('納品レコード更新')
            ->icon(Heroicon::OutlinedDocumentText)
            ->visible(fn (): bool => SaleForm::isAdministrator())
            ->requiresConfirmation()
            ->modalHeading(fn (): string => $this->record->deliveryNote === null
                ? 'この売上データの納品レコードを更新しますか？'
                : '納品書番号：'.$this->record->deliveryNote->number.' の納品レコードを更新しますか？')
            ->modalSubmitActionLabel('更新する')
            ->action(fn (): bool => $this->refreshDocument('納品書番号', $this->record->deliveryNote?->number, '納品',
                fn (SaleDocumentRefresh $refresh): string => $refresh->deliveryNote($this->record)));
    }

    private function refreshInvoiceAction(): Action
    {
        return Action::make('refreshInvoice')
            ->label('請求レコード更新')
            ->icon(Heroicon::OutlinedDocumentCurrencyYen)
            ->visible(fn (): bool => SaleForm::isAdministrator())
            ->requiresConfirmation()
            ->modalHeading(fn (): string => $this->record->invoice === null
                ? 'この売上データの請求レコードを更新しますか？'
                : '請求書番号：'.$this->record->invoice->number.' の請求レコードを更新しますか？')
            ->modalSubmitActionLabel('更新する')
            ->action(fn (): bool => $this->refreshDocument('請求書番号', $this->record->invoice?->number, '請求',
                fn (SaleDocumentRefresh $refresh): string => $refresh->invoice($this->record)));
    }

    /** @param  callable(SaleDocumentRefresh): string  $refresh */
    private function refreshDocument(string $numberLabel, ?string $number, string $kind, callable $refresh): bool
    {
        try {
            $result = $refresh(app(SaleDocumentRefresh::class));
        } catch (SalesActionNotAllowed $exception) {
            Notification::make()->warning()->title($exception->getMessage())->send();

            return false;
        }

        if ($result === SaleDocumentRefresh::UNLINKED) {
            Notification::make()->warning()->title($numberLabel.'：'.$number.' が見つかりません。')
                ->body($kind.'レコード未作成として更新しました。')->persistent()->send();
        } else {
            Notification::make()->success()->title($numberLabel.'：'.$number.' を更新しました。')->send();
        }
        $this->record->refresh();

        return true;
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return SaleFields::LABELS;
    }
}
