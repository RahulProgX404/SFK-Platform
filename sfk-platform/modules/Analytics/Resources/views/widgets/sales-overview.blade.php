{{-- 売上概要 (Modules\Analytics\Filament\Widgets\SalesOverviewCard): 売上金額 (前年同期比 — 前年度同期比 for 今年度) and
     売上件数 (前期間比, with the number of sales in each part of the period; parts after the sales data are hatched),
     each with its baseline on a line under it, then how the period's sales stand with their payment — 入金済 / 入金待ち /
     期限超過 / 未請求 — as one bar of their amounts and a column each: amount, number of sales and share of the amount. --}}
@php
    use Modules\Core\Filament\Support\AppFormat;

    $tones = ['paid' => 'green', 'pending' => 'amber', 'overdue' => 'red', 'unbilled' => 'slate'];
    $share = fn (int $amount): int => $overview['amount'] > 0 ? (int) round($amount / $overview['amount'] * 100) : 0;
    $peak = max(1, ...$overview['parts']);
@endphp

<div class="sfk-dash-cell sfk-dash-span-{{ $span }}">
    {{-- live: redrawn when what it reads changes (DashboardCard::liveTables(), DataPulse) --}}
    @include('core::filament.live-data', ['tables' => $this::liveTables()])
    <section class="sfk-dash-card" aria-labelledby="sfk-overview-title">
        <header class="sfk-dash-card-head">
            <div class="sfk-dash-card-titles">
                <h2 class="sfk-dash-card-title" id="sfk-overview-title">売上概要</h2>
                <span class="sfk-dash-caption"><span class="sfk-dash-tag is-selected">選択期間</span>{{ $period->shortLabel() }}</span>
            </div>
            @if ($salesUrl !== null)
                <a href="{{ $salesUrl }}" class="sfk-dash-more" x-tooltip="{{ \Modules\Core\Filament\Support\Tip::text('売上入力を開く')->x() }}"><x-filament::icon icon="heroicon-m-arrow-right" /><span class="fi-sr-only">売上入力を開く</span></a>
            @endif
        </header>

        <div class="sfk-overview-kpis">
            <div class="sfk-overview-kpi">
                <p class="sfk-overview-label">@include('analytics::widgets.partials.term', ['tip' => $tips['amount'], 'label' => '売上金額（税抜）'])</p>
                <p class="sfk-overview-value">{{ AppFormat::yen($overview['amount']) }}</p>
                <div class="sfk-overview-compare">
                    @include('analytics::widgets.partials.change', ['label' => $lastYearName.'比', 'change' => $yearChange, 'tip' => $tips['year']])
                    @if ($dataNote !== null)
                        <span class="sfk-dash-datanote"><x-filament::icon icon="heroicon-m-exclamation-triangle" />{{ $dataNote }}</span>
                    @endif
                </div>
                <span class="sfk-overview-baseline">{{ $lastYearName }}（{{ $lastYearLabel }}） <b>{{ AppFormat::yen($lastYearAmount) }}</b></span>
            </div>
            <div class="sfk-overview-kpi">
                <p class="sfk-overview-label">@include('analytics::widgets.partials.term', ['tip' => $tips['count'], 'label' => '売上件数'])</p>
                <div class="sfk-overview-count-row">
                    <p class="sfk-overview-value">{{ number_format($overview['count']) }}<small>件</small></p>
                    <div class="sfk-overview-spark" role="img" aria-label="期間内の売上件数の推移">
                        @foreach ($overview['parts'] as $index => $count)
                            @if ($partsWithoutData[$index] ?? false)
                                <i class="is-nodata" data-sfk-tip x-tooltip="{{ \Modules\Core\Filament\Support\Tip::text('データなし')->x() }}"></i>
                            @else
                                <i style="height: {{ max(8, (int) round($count / $peak * 100)) }}%" data-sfk-tip x-tooltip="{{ $tips['parts'][$index]->x() }}"></i>
                            @endif
                        @endforeach
                    </div>
                </div>
                @include('analytics::widgets.partials.change', ['label' => '前期間比', 'change' => $countChange, 'tip' => $tips['countCompare']])
                <span class="sfk-overview-baseline">前期間（{{ $previousLabel }}） <b>{{ number_format($beforeCount) }}件</b></span>
            </div>
        </div>

        <div class="sfk-overview-bar" role="img" aria-label="入金の状況（売上金額の割合）">
            @foreach ($labels as $key => $label)
                @if (($width = $share($overview['statuses'][$key]['amount'])) > 0)
                    <span class="sfk-tone-{{ $tones[$key] }}" style="width: {{ $width }}%" data-sfk-tip x-tooltip="{{ $tips['segments'][$key]->x() }}"></span>
                @endif
            @endforeach
        </div>

        <dl class="sfk-overview-statuses">
            @foreach ($labels as $key => $label)
                <div @class(['sfk-overview-status', 'sfk-tone-'.$tones[$key], 'is-alert' => $key === 'overdue' && $overview['statuses'][$key]['amount'] > 0])>
                    <dt>@include('analytics::widgets.partials.term', ['tip' => $tips['statuses'][$key], 'label' => $label])</dt>
                    <dd>
                        <span class="sfk-overview-status-amount">{{ AppFormat::yen($overview['statuses'][$key]['amount']) }}</span>
                        <span class="sfk-overview-status-note">{{ number_format($overview['statuses'][$key]['count']) }}件・{{ $share($overview['statuses'][$key]['amount']) }}%</span>
                    </dd>
                </div>
            @endforeach
        </dl>
    </section>
</div>
