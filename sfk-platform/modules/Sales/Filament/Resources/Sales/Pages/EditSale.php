<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Sales\Pages;

use Filament\Actions\Action;
use Modules\Core\Filament\AppPages\AppEditRecord;
use Modules\Sales\Filament\Resources\Sales\SaleResource;
use Modules\Sales\Models\Sale;

/**
 * Editing a sale of 06. 売上入力 (save checks of ChecksSale). When 売上数量 / 売上単価 / 売上金額 of a sale already on a
 * delivery note or invoice change, 保存 asks first: the documents are not updated (legacy Customine 06 page 16, BR-SA-15).
 *
 * @property Sale $record
 */
final class EditSale extends AppEditRecord
{
    use ChecksSale;

    protected static string $resource = SaleResource::class;

    public function getTitle(): string
    {
        return (string) $this->record->sales_number;
    }

    protected function getHeaderActions(): array
    {
        return array_map(fn (Action $action): Action => $action->getName() === 'saveRecord'
            ? $action
                ->requiresConfirmation(fn (): bool => $this->changesIssuedAmounts())
                ->modalHeading(fn (): string => $this->issuedDocumentsHeading())
                ->modalSubmitActionLabel('保存する')
                ->modalCancelActionLabel('キャンセル')
            : $action, parent::getHeaderActions());
    }

    /** 「既に納品/請求レコードが作成されています。(納品書番号：…)…」 (legacy dialog of page 16). */
    private function issuedDocumentsHeading(): string
    {
        $note = $this->record->deliveryNote?->number;
        $invoice = $this->record->invoice?->number;
        $numbers = array_filter([$note === null ? null : '納品書番号：'.$note, $invoice === null ? null : '請求書番号：'.$invoice]);
        $documents = $invoice === null ? '納品' : ($note === null ? '請求' : '納品/請求');

        return '既に'.$documents.'レコードが作成されています（'.implode('、', $numbers).'）。売上データを変更してよろしいですか？'
            .'売上データを更新しても'.$documents.'レコードは更新されません。';
    }

    /** 売上数量 / 売上単価 / 売上金額 changed on a sale with a delivery note or invoice. */
    public function changesIssuedAmounts(): bool
    {
        if ($this->record->delivery_note_id === null && $this->record->invoice_id === null) {
            return false;
        }

        foreach (['quantity', 'unit_price', 'amount'] as $field) {
            $before = $this->record->getAttribute($field);
            $after = $this->data[$field] ?? null;
            $before = is_numeric($before) ? (float) $before : null;
            $after = is_numeric($after) ? (float) $after : null;
            if ($before !== $after) {
                return true;
            }
        }

        return false;
    }

    protected function beforeSave(): void
    {
        $this->checkSale($this->record->id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->withJobProductName($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
