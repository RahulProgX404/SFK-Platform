<?php

declare(strict_types=1);

namespace Modules\Production\Application\Schedules;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Orders\Models\Order;
use Modules\Sales\Domain\Sales\SaleChoices;
use Modules\Sales\Filament\Resources\Sales\SaleResource;
use Modules\Sales\Models\Sale;

/**
 * 注番 of the 製袋予定表 (12. 製袋予定管理, user 2026-11-06): 担当者 / 品名 / 部数 come from 06. 売上入力 — the latest sale of
 * the job (a マイナス処理 only when the job has no other sale): its 担当者 (names, 、-separated), 品名 (else the job's) and
 * 売上数量. Like the lookup of the legacy board it is made for whoever plans the machines, whether or not they may open
 * 06. 売上入力.
 */
final class SalesLookup
{
    /** @return array{order: Order, person: string, product: string, quantity: string|null}|null */
    public function find(string $orderNumber): ?array
    {
        // only the sales the user may see (security review 2026-11-14: 品名 / 数量 / 担当者 of any 注番 could be read)
        $sales = SaleResource::onlyVisible(Sale::query())
            ->with(['assignees', 'order'])
            ->whereHas('order', fn (Builder $query): Builder => $query->where('order_number', $orderNumber))
            ->orderByDesc('id');

        $sale = (clone $sales)->where('kind', '!=', SaleChoices::KIND_CREDIT)->first() ?? $sales->first();
        if (! $sale instanceof Sale || ! $sale->order instanceof Order) {
            return null;
        }

        $person = $sale->assignees->map(fn (User $user): string => $user->name)->implode('、');

        return [
            'order' => $sale->order,
            'person' => mb_substr($person, 0, 40),
            'product' => (string) (filled($sale->product_name) ? $sale->product_name : $sale->order->product_name),
            'quantity' => $sale->quantity,
        ];
    }
}
