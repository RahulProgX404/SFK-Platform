<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\ConsumableOrders\Pages;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\AppPages\AppCreateRecord;
use Modules\Purchasing\Filament\Resources\ConsumableOrders\ConsumableOrderResource;
use Modules\Purchasing\Models\ConsumableOrder;
use Modules\Purchasing\Models\ConsumableOrderLine;

/** New order of 20. 消耗品発注; it starts 未処理 with its 作成者 as 作業者 (BR-PU-09). */
final class CreateConsumableOrder extends AppCreateRecord
{
    protected static string $resource = ConsumableOrderResource::class;

    protected static ?string $title = '発注を追加';

    /**
     * レコードを再利用する: the copy starts 未処理; the legacy id is not copied.
     *
     * @return list<string>
     */
    protected function duplicateExcludedFields(): array
    {
        return ['legacy_id', 'status'];
    }

    /** 発注者 and the rows go with the copy (without 納品日). */
    protected function mutateDuplicateData(array $data, Model $source): array
    {
        if ($source instanceof ConsumableOrder) {
            $data['orderers'] = $source->orderers()->pluck('users.id')->map(fn (mixed $id): string => (string) $id)->all();
            $data['lines'] = $source->lines->map(fn (ConsumableOrderLine $row): array => [
                ...array_intersect_key($row->attributesToArray(), array_flip(ConsumableOrderLine::COLUMNS)),
                'delivered_on' => null,
            ])->all();
        }

        return $data;
    }

    /** Like the legacy system, show the saved record. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
