{{--
    分析 → 売上分析 (Modules\Analytics\Filament\Pages\SalesAnalysis): the summary cards, the controls, then the tables — 月次:
    the totals and a table per 担当者; 年次: the totals; a month opens its 売上先 on the right.
--}}
@php
    use Filament\Support\Icons\Heroicon;
    use Modules\Core\Filament\Support\AppFormat;

    $yen = fn (int $amount): string => AppFormat::yen($amount);
    $monthLabel = fn (int $month): string => $month.'月';
@endphp

<x-filament-panels::page>
    @include('analytics::filament.analysis-styles')

    <div class="sfk-an">
        @include('core::filament.app-summary', ['cards' => $sales['cards']])

        <div class="sfk-an-toolbar">
            {{-- 年度: several can be compared --}}
            <x-filament::dropdown placement="bottom-start" width="xs" :teleport="true">
                <x-slot name="trigger">
                    <button type="button" class="sfk-list-tool">
                        <x-filament::icon :icon="Heroicon::OutlinedCalendar" class="sfk-list-tool-icon" />
                        <span>年度：{{ $sales['years'] === [] ? '未選択' : implode('・', array_map(fn (int $year): string => $year.'年度', $sales['years'])) }}</span>
                        <x-filament::icon :icon="Heroicon::ChevronDown" class="sfk-list-tool-chevron" />
                    </button>
                </x-slot>
                <div class="sfk-an-checks">
                    @foreach (array_reverse($sales['available']) as $year)
                        <label wire:key="year-{{ $year }}">
                            <input type="checkbox" class="sfk-ui-check" wire:model.live="years" value="{{ $year }}">
                            {{ $year }}年度
                        </label>
                    @endforeach
                </div>
            </x-filament::dropdown>

            <div class="sfk-an-seg" role="group" aria-label="表示">
                @foreach (['monthly' => '月次', 'yearly' => '年次'] as $value => $label)
                    <button type="button" wire:click="$set('mode', @js($value))" @class(['is-active' => $this->mode === $value])>{{ $label }}</button>
                @endforeach
            </div>

            @if ($this->mode === 'monthly')
                {{-- 担当者 shown as tables --}}
                <x-filament::dropdown placement="bottom-start" width="xs" :teleport="true">
                    <x-slot name="trigger">
                        <button type="button" class="sfk-list-tool">
                            <x-filament::icon :icon="Heroicon::OutlinedUsers" class="sfk-list-tool-icon" />
                            <span>担当者：{{ match (true) {
                                $sales['chosen'] === [] => '未選択',
                                count(array_intersect($sales['chosen'], array_keys($sales['staff']))) === count($sales['staff']) => '全員',
                                default => count(array_intersect($sales['chosen'], array_keys($sales['staff']))).'名',
                            } }}</span>
                            <x-filament::icon :icon="Heroicon::ChevronDown" class="sfk-list-tool-chevron" />
                        </button>
                    </x-slot>
                    <div class="sfk-an-checks">
                        @foreach ($sales['staff'] as $id => $name)
                            <label wire:key="staff-{{ $id }}">
                                <input type="checkbox" class="sfk-ui-check" wire:model.live="staff" value="{{ $id }}">
                                {{ $name }}
                            </label>
                        @endforeach
                    </div>
                    <div class="sfk-an-checks-actions">
                        <button type="button" class="sfk-filter-link" wire:click="$set('staff', @js(array_keys($sales['staff'])))">すべて選択</button>
                        <button type="button" class="sfk-filter-link" wire:click="$set('staff', [])">すべて解除</button>
                    </div>
                </x-filament::dropdown>
            @endif
        </div>

        @if ($sales['years'] === [])
            <div class="sfk-an-card"><p class="sfk-an-empty">年度を選択してください。</p></div>
        @else
            {{-- 年度別 合計: every 担当者 --}}
            <section class="sfk-an-section" aria-labelledby="sfk-an-totals">
                <h2 class="sfk-an-heading" id="sfk-an-totals">年度別 合計</h2>
                @include('analytics::filament.analysis-sales-table', [
                    'staffId' => null,
                    'rows' => array_map(fn (int $year): array => ['year' => $year, 'months' => $sales['totals'][$year]], $sales['years']),
                    'planned' => $sales['planned'],
                    'editable' => false,
                ])
            </section>

            @if ($this->mode === 'monthly')
                <section class="sfk-an-section" aria-labelledby="sfk-an-people">
                    <h2 class="sfk-an-heading" id="sfk-an-people">担当者別</h2>
                    @forelse ($sales['people'] as $person)
                        <details class="sfk-an-person" open wire:key="person-{{ $person['id'] }}">
                            <summary>
                                <x-filament::icon :icon="Heroicon::ChevronDown" />
                                {{ $person['name'] }}
                                @if (($last = end($person['rows'])) !== false)
                                    <small>{{ $last['year'] }}年度 合計 {{ $yen(array_sum($last['months'])) }}</small>
                                @endif
                            </summary>
                            @include('analytics::filament.analysis-sales-table', [
                                'staffId' => $person['id'],
                                'rows' => $person['rows'],
                                'planned' => $person['planned'],
                                'editable' => $person['editable'],
                            ])
                        </details>
                    @empty
                        <div class="sfk-an-card"><p class="sfk-an-empty">担当者を選択してください。</p></div>
                    @endforelse
                </section>
            @endif
        @endif

        @if (($detail = $sales['detail']) !== null)
            <div class="sfk-an-drawer-bg" wire:click="closeDetail"></div>
            <aside class="sfk-an-drawer" role="dialog" aria-label="売上先別 明細" x-data x-on:keydown.escape.window="$wire.closeDetail()">
                <div class="sfk-an-drawer-head">
                    <div>
                        <h2>売上先別 明細</h2>
                        <p>{{ $detail['staffName'] }}・{{ $detail['year'] }}年度 {{ $detail['month'] }}月（{{ $detail['calendarYear'] }}年{{ $detail['month'] }}月）</p>
                        <p class="sfk-an-drawer-total">{{ $yen($detail['total']) }}</p>
                    </div>
                    <button type="button" class="sfk-an-close" wire:click="closeDetail" title="閉じる">
                        <x-filament::icon :icon="Heroicon::XMark" class="h-5 w-5" />
                        <span class="fi-sr-only">閉じる</span>
                    </button>
                </div>
                <div class="sfk-an-drawer-body">
                    @forelse ($detail['customers'] as $index => $customer)
                        <div class="sfk-an-customer" x-data="{ open: false }" x-bind:class="{ 'is-open': open }" wire:key="customer-{{ $index }}-{{ $customer['code'] }}">
                            <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open ? 'true' : 'false'">
                                <x-filament::icon :icon="Heroicon::ChevronRight" />
                                <span class="sfk-an-customer-name">
                                    @if (filled($customer['code']))
                                        <small>{{ $customer['code'] }}</small>
                                    @endif
                                    {{ $customer['name'] }}
                                </span>
                                <span class="sfk-an-customer-amount">{{ $yen($customer['amount']) }}</span>
                            </button>
                            <table class="sfk-an-lines" x-show="open" x-cloak>
                                <thead><tr><th class="is-date">売上日</th><th>品名</th><th class="is-amount">売上金額</th></tr></thead>
                                <tbody>
                                    @foreach ($customer['lines'] as $line)
                                        <tr><td class="is-date">{{ $line['date'] }}</td><td>{{ $line['product'] ?? '－' }}</td><td class="is-amount">{{ $yen($line['amount']) }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @empty
                        <p class="sfk-an-empty">この月の売上はありません。</p>
                    @endforelse
                </div>
            </aside>
        @endif
    </div>
</x-filament-panels::page>
