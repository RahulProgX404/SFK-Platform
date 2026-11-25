{{-- 売上合計 (Modules\Analytics\Filament\Widgets\RevenueCard): the first figure of the dashboard — the sales of the period,
     compared with the same number of days just before (前期間比 and the difference, then that period's days and amount on
     a line of their own), and the progress towards 売上目標 (個人: 売上予想) of the period. No percentage when either
     period reaches past the sales data ($dataNote says how far it goes). --}}
@php
    use Modules\Core\Filament\Support\AppFormat;

    $progress = $reached === null ? 0 : min(100, $reached);
@endphp

<div @class(["sfk-dash-cell sfk-dash-span-4", "sfk-dash-t-full" => $personal])>
    {{-- live: redrawn when what it reads changes (DashboardCard::liveTables(), DataPulse) --}}
    @include('core::filament.live-data', ['tables' => $this::liveTables()])
    <section class="sfk-revenue" aria-labelledby="sfk-revenue-title">
        <div class="sfk-revenue-head">
            <div>
                <h2 class="sfk-revenue-title" id="sfk-revenue-title">@include('analytics::widgets.partials.term', ['tip' => $tips['amount'], 'label' => '売上合計'])</h2>
                <span class="sfk-revenue-caption"><span class="sfk-dash-tag is-selected">選択期間</span>{{ $this->periodCaption() }}</span>
            </div>
        </div>

        <div>
            <p class="sfk-revenue-amount">{{ AppFormat::yen($amount) }}</p>
            <p class="sfk-revenue-sub">@include('analytics::widgets.partials.term', ['tip' => $tips['count'], 'label' => '売上件数']) {{ number_format($count) }}件</p>
        </div>

        <div class="sfk-revenue-compare">
            @if ($change !== null || $comparable)
                <div class="sfk-revenue-compare-head">
                    @include('analytics::widgets.partials.change', ['label' => '前期間比', 'change' => $change, 'tip' => $tips['compare']])
                    @if ($comparable)
                        <span class="sfk-revenue-diff"><small>差額</small>{{ $difference < 0 ? '−' : ($difference > 0 ? '+' : '±') }}{{ AppFormat::yen(abs($difference)) }}</span>
                    @endif
                </div>
            @endif
            <dl class="sfk-revenue-baseline">
                <dt>前期間<small>{{ $previousLabel }}</small></dt>
                <dd>{{ AppFormat::yen($before) }}</dd>
            </dl>
            @if ($dataNote !== null)
                <p class="sfk-revenue-note"><x-filament::icon icon="heroicon-m-exclamation-triangle" />{{ $dataNote }}</p>
            @endif
        </div>

        <div class="sfk-revenue-target">
            <div class="sfk-revenue-target-row">
                <span>@include('analytics::widgets.partials.term', ['tip' => $tips['target'], 'label' => $targetLabel])</span>
                <strong>{{ $target !== null ? AppFormat::yen($target) : '未設定' }}</strong>
            </div>
            @if ($reached !== null)
                <div class="sfk-revenue-bar" role="progressbar" aria-label="{{ $targetLabel }}の達成率" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}"><i style="width: {{ $progress }}%"></i></div>
                <div class="sfk-revenue-target-note">
                    <span><b>{{ $reached }}% 達成</b></span>
                    <span>{{ $amount >= $target ? '超過 '.AppFormat::yen($amount - $target) : '残り '.AppFormat::yen($target - $amount) }}</span>
                </div>
            @endif
        </div>

        @if ($detailUrl !== null)
            <a href="{{ $detailUrl }}" class="sfk-revenue-more"><x-filament::icon icon="heroicon-o-chart-bar" />売上分析を見る<x-filament::icon icon="heroicon-m-arrow-right" /></a>
        @endif
    </section>
</div>
