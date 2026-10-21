<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\Quotations\Pages;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\AppPages\AppCreateRecord;
use Modules\Orders\Filament\Resources\Quotations\QuotationResource;
use Modules\Orders\Models\Quotation;
use Modules\Orders\Models\QuotationLine;

final class CreateQuotation extends AppCreateRecord
{
    protected static string $resource = QuotationResource::class;

    protected static ?string $title = '見積書を追加';

    /**
     * レコードを再利用する: 見積書番号 (unique), the PDF, the legacy id and the derived total are not copied.
     *
     * @return list<string>
     */
    protected function duplicateExcludedFields(): array
    {
        return ['quote_number', 'pdf_files', 'legacy_id', 'total'];
    }

    /** 担当者 and the quantity tiers go with the copy. */
    protected function mutateDuplicateData(array $data, Model $source): array
    {
        if ($source instanceof Quotation) {
            $data['assignees'] = $source->assignees()->pluck('users.id')->map(fn (mixed $id): string => (string) $id)->all();
            $data['lines'] = $source->lines->map(fn (QuotationLine $row): array => array_intersect_key($row->attributesToArray(), array_flip(QuotationLine::COLUMNS)))->all();
        }

        return $data;
    }

    /** Like the legacy system, show the saved record. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
