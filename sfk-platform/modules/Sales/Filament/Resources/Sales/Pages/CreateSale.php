<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Sales\Pages;

use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;
use Modules\Core\Filament\AppPages\AppCreateRecord;
use Modules\Orders\Models\Order;
use Modules\Sales\Filament\Resources\Sales\SaleResource;
use Modules\Sales\Models\Sale;
use Modules\Sales\Models\SaleAllocation;

/**
 * New sale of 06. 売上入力. ?order=<id> (button 「売上入力」 on a 04. 受注入力 record, legacy app action) starts it from the
 * job: 売上先 = the job's customer, 注番, 品名, 見積原価合計, 見積経費 and 担当者. The save checks of ChecksSale apply.
 */
final class CreateSale extends AppCreateRecord
{
    use ChecksSale;

    protected static string $resource = SaleResource::class;

    protected static ?string $title = '売上を追加';

    #[Url(as: 'order')]
    public ?string $fromOrder = null;

    protected function afterFill(): void
    {
        $order = $this->duplicate === null && is_numeric($this->fromOrder) ? Order::query()->find((int) $this->fromOrder) : null;
        if ($order === null) {
            return;
        }

        $this->form->fill([
            ...$this->form->getRawState(),
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'product_name' => $order->product_name,
            'estimated_cost' => $order->estimated_cost_total,
            'estimated_expense' => $order->estimated_profit,
            'assignees' => $order->assignees()->pluck('users.id')->map(fn (mixed $id): string => (string) $id)->all(),
        ]);
    }

    protected function beforeCreate(): void
    {
        $this->checkSale(null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->withJobProductName($data);
    }

    /**
     * レコードを再利用する: 売上No, the document links and the legacy id are not copied.
     *
     * @return list<string>
     */
    protected function duplicateExcludedFields(): array
    {
        return ['sales_number', 'legacy_id', 'delivery_note_id', 'invoice_id', 'locked', 'tax', 'billing_month'];
    }

    /** 担当者 and the 按分 rows go with the copy. */
    protected function mutateDuplicateData(array $data, Model $source): array
    {
        if ($source instanceof Sale) {
            $data['assignees'] = $source->assignees()->pluck('users.id')->map(fn (mixed $id): string => (string) $id)->all();
            $data['allocations'] = $source->allocations->map(fn (SaleAllocation $row): array => ['user_id' => $row->user_id, 'ratio' => $row->ratio])->all();
        }

        return $data;
    }

    /** Like the legacy system, show the saved record. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
