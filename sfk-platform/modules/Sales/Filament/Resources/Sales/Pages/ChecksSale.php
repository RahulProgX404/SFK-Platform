<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Sales\Pages;

use Filament\Notifications\Notification;
use Modules\Orders\Models\Order;
use Modules\Sales\Application\Sales\SaleOrderTotals;
use Modules\Sales\Domain\Sales\SaleChecks;
use Modules\Sales\Filament\Resources\Sales\Schemas\SaleForm;

/**
 * The save checks of 06. 売上入力 on the create / edit page (legacy Customine 06 page 2, BR-SA-02..04): the sale is not
 * saved and the reasons are shown. Administrators are exempt like the legacy privileged user. Employees cannot change
 * 品名 of a sale on a job: it is the job's (page 23).
 */
trait ChecksSale
{
    /** Stops the save when a check fails. */
    protected function checkSale(?int $saleId): void
    {
        if (SaleForm::isAdministrator()) {
            return;
        }

        $order = is_numeric($this->data['order_id'] ?? null) ? Order::query()->find((int) $this->data['order_id']) : null;
        if ($order === null) {
            return;
        }

        $figures = app(SaleOrderTotals::class)->withoutSale($order, $saleId);
        $quantity = $this->data['quantity'] ?? null;
        $problems = SaleChecks::problems(
            (string) ($this->data['kind'] ?? ''),
            is_numeric($quantity) ? (string) $quantity : null,
            (int) ($this->data['amount'] ?? 0),
            $figures['remaining'],
            $figures['sold_amount'],
            $figures['planned_amount'],
        );

        if ($problems !== []) {
            Notification::make()->danger()->title('保存できません')->body(implode("\n", $problems))->persistent()->send();
            $this->halt();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function withJobProductName(array $data): array
    {
        if (! SaleForm::isAdministrator() && is_numeric($data['order_id'] ?? null)) {
            $data['product_name'] = Order::query()->whereKey((int) $data['order_id'])->value('product_name');
        }

        return $data;
    }
}
