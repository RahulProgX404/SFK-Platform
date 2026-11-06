<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\BagSchedules\Pages\Concerns;

use Illuminate\Validation\ValidationException;
use Modules\Orders\Models\Order;
use Modules\Production\Filament\Resources\BagSchedules\BagScheduleLayout;

/**
 * Saving the form of 12. 製袋予定管理 as the 製袋予定表 does: with a 注番, 担当者 / 品名 / 部数 are those of 06. 売上入力
 * (taken again when saved; no sale of the job → not saved); 備考 are kept one per line.
 */
trait TakesSalesValues
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function takeSalesValues(array $data): array
    {
        if (array_key_exists('notes', $data)) {
            $notes = array_filter(array_map(trim(...), preg_split('/\r\n|\r|\n/', (string) $data['notes']) ?: []), fn (string $note): bool => $note !== '');
            $data['notes'] = $notes !== [] ? implode("\n", $notes) : null;
        }

        if (! is_numeric($data['order_id'] ?? null)) {
            return $data;
        }

        $sale = BagScheduleLayout::saleOf((int) $data['order_id']);
        if ($sale === null) {
            $number = Order::query()->whereKey((int) $data['order_id'])->value('order_number');

            throw ValidationException::withMessages(['data.order_id' => "注番「{$number}」に一致するレコードが06. 売上入力に見つかりません。"]);
        }

        $data['person_initial'] = $sale['person'] !== '' ? $sale['person'] : null;
        $data['product_name'] = $sale['product'] !== '' ? $sale['product'] : null;
        $data['copies'] = $sale['quantity'];

        return $data;
    }
}
