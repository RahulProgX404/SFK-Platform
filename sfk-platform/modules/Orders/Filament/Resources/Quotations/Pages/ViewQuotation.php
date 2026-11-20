<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\Quotations\Pages;

use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\Orders\Domain\Quotations\QuotationFields;
use Modules\Orders\Filament\Resources\Quotations\QuotationResource;
use Modules\Orders\Models\Quotation;

/**
 * A record of 09. 見積書管理.
 *
 * @property Quotation $record
 */
final class ViewQuotation extends AppViewRecord
{
    /** 得意先 besides 作成者 / 更新者 (one query for them, one for 得意先). */
    protected function batchedRelations(): array
    {
        return [...parent::batchedRelations(), 'customer'];
    }

    protected static string $resource = QuotationResource::class;

    public function getTitle(): string
    {
        return QuotationResource::titleOf($this->record);
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return QuotationFields::LABELS;
    }
}
