<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\Orders\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\Core\Filament\AppResource;
use Modules\Orders\Domain\Orders\OrderFields;
use Modules\Orders\Filament\Resources\Orders\OrderResource;
use Modules\Orders\Models\Order;

/**
 * A record of 04. 受注入力. 過去の受注履歴検索 (legacy OrderEntryAppSearchPastOrdersHistory widget, which never appeared
 * because its space was missing — D-04-3): other orders by 得意先(受注先) (this order's customer at first), 品名 and a
 * 受注日 range, newest 受注日 first, at most 500, 10 per page; each opens in a new tab. 「…」 has 売上入力 (legacy app action):
 * a new sale of 06. 売上入力 for this job, while that app is there and the user may add sales.
 *
 * @property Order $record
 */
final class ViewOrder extends AppViewRecord
{
    protected static string $resource = OrderResource::class;

    public const PAST_LIMIT = 500;

    public const PAST_PAGE_SIZE = 10;

    public string $pastCustomer = '';

    public string $pastProduct = '';

    public string $pastFrom = '';

    public string $pastTo = '';

    public bool $pastSearched = false;

    public int $pastPage = 1;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->pastCustomer = (string) $this->record->customer?->name;
    }

    public function getTitle(): string
    {
        return $this->record->order_number;
    }

    protected function getHeaderActions(): array
    {
        return array_map(function (Action|ActionGroup $action): Action|ActionGroup {
            if ($action instanceof ActionGroup) {
                $action->actions([$this->enterSaleAction(), ...$action->getActions()]);
            }

            return $action;
        }, parent::getHeaderActions());
    }

    private function enterSaleAction(): Action
    {
        $url = fn (): ?string => ($panel = Filament::getCurrentPanel()) === null
            ? null
            : AppResource::createUrlForApp('sales-entry', $panel, ['order' => $this->record->getKey()]);

        return Action::make('enterSale')
            ->label('売上入力')
            ->icon(Heroicon::OutlinedCurrencyYen)
            ->url($url)
            ->visible(fn (): bool => $this->historyVersion === null && $url() !== null);
    }

    public function searchPastOrders(): void
    {
        $this->pastSearched = true;
        $this->pastPage = 1;
    }

    public function resetPastOrders(): void
    {
        $this->pastCustomer = '';
        $this->pastProduct = '';
        $this->pastFrom = '';
        $this->pastTo = '';
        $this->pastSearched = false;
        $this->pastPage = 1;
    }

    public function showPastOrdersPage(int $page): void
    {
        $this->pastPage = max(1, min($page, $this->pastOrders()['pages']));
    }

    /**
     * The search result: total (at most PAST_LIMIT), the orders of the shown page and the paging.
     *
     * @return array{total: int, orders: Collection<int, Order>, page: int, pages: int, from: int, to: int}
     */
    public function pastOrders(): array
    {
        if (! $this->pastSearched) {
            return ['total' => 0, 'orders' => new Collection, 'page' => 1, 'pages' => 1, 'from' => 0, 'to' => 0];
        }

        // only the orders the user may see (レコードのアクセス権)
        $query = OrderResource::onlyVisible(Order::query())
            ->whereKeyNot($this->record->getKey())
            ->when(trim($this->pastCustomer) !== '', fn (Builder $q) => $q->whereHas('customer', fn (Builder $c) => $c->whereLike('name', '%'.trim($this->pastCustomer).'%')))
            ->when(trim($this->pastProduct) !== '', fn (Builder $q) => $q->whereLike('product_name', '%'.trim($this->pastProduct).'%'))
            ->when(self::isDate($this->pastFrom), fn (Builder $q) => $q->whereDate('order_date', '>=', $this->pastFrom))
            ->when(self::isDate($this->pastTo), fn (Builder $q) => $q->whereDate('order_date', '<=', $this->pastTo));

        $total = min((clone $query)->count(), self::PAST_LIMIT);
        $pages = max(1, (int) ceil($total / self::PAST_PAGE_SIZE));
        $page = max(1, min($this->pastPage, $pages));
        $offset = ($page - 1) * self::PAST_PAGE_SIZE;

        $orders = $query->with('customer')
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit(min(self::PAST_PAGE_SIZE, max(0, $total - $offset)))
            ->get();

        return [
            'total' => $total,
            'orders' => $orders,
            'page' => $page,
            'pages' => $pages,
            'from' => $total === 0 ? 0 : $offset + 1,
            'to' => $offset + $orders->count(),
        ];
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return OrderFields::LABELS;
    }

    private static function isDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }
}
