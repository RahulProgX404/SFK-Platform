<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\DeliveryNotes\Pages;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\AppPages\AppCreateRecord;
use Modules\Sales\Filament\Resources\DeliveryNotes\DeliveryNoteResource;
use Modules\Sales\Models\DeliveryNote;

final class CreateDeliveryNote extends AppCreateRecord
{
    protected static string $resource = DeliveryNoteResource::class;

    protected static ?string $title = '納品書を追加';

    /**
     * レコードを再利用する: numbers, links, files and derived totals are not copied.
     *
     * @return list<string>
     */
    protected function duplicateExcludedFields(): array
    {
        return ['number', 'legacy_id', 'pdf_files'];
    }

    /** 担当者 and the rows go with the copy. */
    protected function mutateDuplicateData(array $data, Model $source): array
    {
        if ($source instanceof DeliveryNote) {
            $data['assignees'] = $source->assignees()->pluck('users.id')->map(fn (mixed $id): string => (string) $id)->all();
            $data['lines'] = $source->lines->map(fn (Model $row): array => array_intersect_key($row->attributesToArray(), array_flip($row::COLUMNS)))->all();
        }

        return $data;
    }

    /** Like the legacy system, show the saved record. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
