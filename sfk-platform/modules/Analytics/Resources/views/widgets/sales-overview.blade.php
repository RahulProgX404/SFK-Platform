{{-- 売上概要 (Modules\Analytics\Filament\Widgets\SalesOverviewCard) --}}
@php
    use Modules\Core\Filament\Support\AppFormat;

    $tones = ['paid' => 'green', 'pending' => 'amber', 'overdue' => 'red', 'unbilled' => 'blue'];
    // shares of the number of sales (the bar and 「全体の N%」)
    $share = fn (int $count): int => $overview['count'] > 0 ? (int) round($count / $overview['count'] * 100) : 0;
    $peak = max(1, ...$overview['parts']);
@endphp

<div class="sfk-dash-cell sfk-dash-span-6">
    <style>
        .sfk-overview-body { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.15fr); gap: var(--sfk-space-5); align-items: start; }
        .sfk-overview-box { display: flex; flex-direction: column; gap: var(--sfk-space-3); padding: var(--sfk-space-4); border-radius: var(--sfk-radius-lg); background: var(--sfk-primary-soft); box-shadow: inset 0 0 0 1px var(--sfk-primary-soft-strong); }
        .sfk-overview-box-top { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--sfk-space-2); }
        .sfk-overview-label { margin: 0; color: var(--sfk-muted); font-size: var(--sfk-fs-small); }
        .sfk-overview-count { margin: 2px 0 0; color: var(--sfk-ink); font-size: var(--sfk-fs-figure); font-weight: var(--sfk-fw-bold); font-variant-numeric: tabular-nums; }
        .sfk-overview-bar { display: flex; height: 14px; overflow: hidden; border-radius: 9999px; background: var(--sfk-surface); box-shadow: inset 0 0 0 1px var(--sfk-line); }
        .sfk-overview-bar span { height: 100%; }
        .sfk-overview-scale { display: flex; justify-content: space-between; color: var(--sfk-subtle); font-size: 11px; font-variant-numeric: tabular-nums; }
        .sfk-overview-spark { display: flex; align-items: flex-end; gap: 4px; height: 34px; }
        .sfk-overview-spark i { flex: 1; min-height: 3px; border-radius: 3px 3px 0 0; background: var(--sfk-primary); opacity: .35; }
        .sfk-overview-spark i:last-child { opacity: .9; }
        .sfk-overview-total { display: flex; align-items: baseline; flex-wrap: wrap; gap: 4px var(--sfk-space-3); margin: 4px 0 0; }
        .sfk-overview-total strong { color: var(--sfk-ink); font-size: var(--sfk-fs-figure); font-weight: var(--sfk-fw-bold); font-variant-numeric: tabular-nums; }
        .sfk-overview-statuses { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--sfk-space-4) var(--sfk-space-3); margin-top: var(--sfk-space-5); }
        .sfk-overview-status p { margin: 0; }
        .sfk-overview-status-name { color: var(--sfk-text); font-size: var(--sfk-fs-small); }
        .sfk-overview-status-count { display: flex; align-items: center; gap: 6px; margin-top: 2px !important; color: var(--sfk-ink); font-size: var(--sfk-fs-body); font-weight: var(--sfk-fw-semibold); font-variant-numeric: tabular-nums; }
        .sfk-overview-status-count i { width: 8px; height: 8px; border-radius: 9999px; background: var(--sfk-tone); }
        .sfk-overview-status-share { color: var(--sfk-muted); font-size: var(--sfk-fs-badge); font-variant-numeric: tabular-nums; }
        @media (min-width: 1600px) { .sfk-overview-statuses { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        @media (max-width: 640px) { .sfk-overview-body { grid-template-columns: minmax(0, 1fr); } }
    </style>

    <section class="sfk-dash-card" aria-labelledby="sfk-overview-title">
        <header class="sfk-dash-card-head">
            <h2 class="sfk-dash-card-title" id="sfk-overview-title">売上概要</h2>
            <span class="sfk-dash-period"><x-filament::icon icon="heroicon-o-calendar" />{{ $period->label() }}</span>
        </header>

        <div class="sfk-overview-body">
            <div class="sfk-overview-box">
                <div class="sfk-overview-box-top">
                    <div>
                        <p class="sfk-overview-label">売上件数</p>
                        <p class="sfk-overview-count">{{ number_format($overview['count']) }}件</p>
                    </div>
                    @if ($countChange !== null)
                        <span @class(['sfk-dash-trend', 'is-down' => $countChange < 0]) title="前の期間との比較">{{ $countChange >= 0 ? '▲' : '▼' }} {{ abs($countChange) }}%</span>
                    @endif
                </div>
                <div class="sfk-overview-spark" aria-hidden="true" title="期間内の売上件数の推移">
                    @foreach ($overview['parts'] as $count)
                        <i style="height: {{ max(8, (int) round($count / $peak * 100)) }}%"></i>
                    @endforeach
                </div>
                <div>
                    <div class="sfk-overview-bar" role="img" aria-label="売上の状況">
                        @foreach ($labels as $key => $label)
                            @if (($width = $share($overview['statuses'][$key]['count'])) > 0)
                                <span class="sfk-tone-{{ $tones[$key] }}" style="width: {{ $width }}%; background: var(--sfk-tone)" title="{{ $label }} {{ $width }}%"></span>
                            @endif
                        @endforeach
                    </div>
                    <div class="sfk-overview-scale" aria-hidden="true"><span>0%</span><span>25%</span><span>50%</span><span>75%</span><span>100%</span></div>
                </div>
            </div>

            <div>
                <p class="sfk-overview-label" style="display: flex; align-items: center; gap: 6px"><x-filament::icon icon="heroicon-o-document-text" class="h-4 w-4" /> 売上金額（税抜）</p>
                <p class="sfk-overview-total">
                    <strong>{{ AppFormat::yen($overview['amount']) }}</strong>
                    @if ($yearChange !== null)
                        <span @class(['sfk-dash-trend', 'is-down' => $yearChange < 0]) title="前年同期との比較">前年同期比 {{ $yearChange >= 0 ? '▲' : '▼' }} {{ abs($yearChange) }}%</span>
                    @endif
                </p>

                <div class="sfk-overview-statuses">
                    @foreach ($labels as $key => $label)
                        <div class="sfk-overview-status sfk-tone-{{ $tones[$key] }}">
                            <p class="sfk-overview-status-name">{{ $label }}</p>
                            <p class="sfk-overview-status-count"><i aria-hidden="true"></i>{{ number_format($overview['statuses'][$key]['count']) }}件</p>
                            <p class="sfk-overview-status-share" title="{{ AppFormat::yen($overview['statuses'][$key]['amount']) }}">全体の{{ $share($overview['statuses'][$key]['count']) }}%・{{ \Modules\Analytics\Domain\ShortYen::format($overview['statuses'][$key]['amount']) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
</div>
