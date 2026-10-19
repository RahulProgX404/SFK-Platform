{{-- 過去の受注履歴検索 on a 04. 受注入力 record (Modules\Orders\Filament\Resources\Orders\Pages\ViewOrder). --}}
@php
    use Modules\Orders\Filament\Resources\Orders\OrderFormat;
    use Modules\Orders\Filament\Resources\Orders\OrderResource;

    $page = $getLivewire();
    $result = $page->pastOrders();
@endphp

<section class="sfk-order-past" x-data="{ open: true }" aria-labelledby="sfk-order-past-title">
    <div class="sfk-order-past-head">
        <h3 id="sfk-order-past-title" class="sfk-order-past-title">過去の受注履歴検索</h3>
        <button type="button" class="sfk-order-past-toggle" x-on:click="open = ! open" x-bind:aria-expanded="open ? 'true' : 'false'"
                x-text="open ? '折りたたみ ▲' : '展開 ▼'">折りたたみ ▲</button>
    </div>

    <div x-show="open">
        <form class="sfk-order-past-search" wire:submit="searchPastOrders">
            <label class="sfk-order-past-field">
                <span>得意先(受注先)</span>
                <input type="text" wire:model="pastCustomer" maxlength="100">
            </label>
            <label class="sfk-order-past-field">
                <span>品名</span>
                <input type="text" wire:model="pastProduct" maxlength="100">
            </label>
            <div class="sfk-order-past-field">
                <span>受注日</span>
                <span class="sfk-order-past-range">
                    <input type="date" wire:model="pastFrom" aria-label="受注日（から）">
                    <span aria-hidden="true">～</span>
                    <input type="date" wire:model="pastTo" aria-label="受注日（まで）">
                </span>
            </div>
            <button type="submit" class="sfk-filter-btn sfk-filter-btn-submit">検索</button>
            <button type="button" class="sfk-filter-btn" wire:click="resetPastOrders">リセット</button>
        </form>

        @if ($page->pastSearched)
            <div class="sfk-order-past-status">
                <span>全{{ number_format($result['total']) }}件中 {{ $result['from'] }}～{{ $result['to'] }}件を表示</span>
                @if ($result['pages'] > 1)
                    <span class="sfk-order-past-pager">
                        <button type="button" class="sfk-order-past-page" wire:click="showPastOrdersPage({{ $result['page'] - 1 }})" @disabled($result['page'] <= 1)>← 前</button>
                        <span>ページ {{ $result['page'] }} / {{ $result['pages'] }}</span>
                        <button type="button" class="sfk-order-past-page" wire:click="showPastOrdersPage({{ $result['page'] + 1 }})" @disabled($result['page'] >= $result['pages'])>次 →</button>
                    </span>
                @endif
            </div>

            @if ($result['orders']->isNotEmpty())
                <div class="sfk-order-past-table">
                    <table>
                        <thead>
                            <tr>
                                <th><span class="fi-sr-only">レコード</span></th>
                                <th>受注日</th>
                                <th>注番</th>
                                <th>得意先(受注先)</th>
                                <th>品名</th>
                                <th class="is-number">数量</th>
                                <th class="is-number">売上金額</th>
                                <th>ステータス</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($result['orders'] as $order)
                                @php($url = OrderResource::getUrl('view', ['record' => $order]))
                                <tr wire:key="past-order-{{ $order->id }}">
                                    <td><a href="{{ $url }}" target="_blank" rel="noopener" title="レコードを開く"><x-filament::icon icon="heroicon-o-document-text" class="sfk-order-past-icon" /></a></td>
                                    <td>{{ $order->order_date?->format('Y年m月d日') }}</td>
                                    <td><a href="{{ $url }}" target="_blank" rel="noopener">{{ $order->order_number }}</a></td>
                                    <td>{{ $order->customer?->name }}</td>
                                    <td>{{ $order->product_name }}</td>
                                    <td class="is-number">{{ OrderFormat::number($order->quantity) }}</td>
                                    <td class="is-number">{{ OrderFormat::yen($order->sales_amount) }}</td>
                                    <td>{{ $order->status }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
</section>
