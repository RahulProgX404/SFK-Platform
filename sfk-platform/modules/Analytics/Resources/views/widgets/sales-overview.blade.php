{{-- 売上概要 (Modules\Analytics\Filament\Widgets\SalesOverviewCard): 売上金額 (前年同期比) and 売上件数 (前期間比, with the
     number of sales in each part of the period), then how the period's sales stand with their payment — 入金済 / 入金待ち /
     期限超過 / 未請求 — as one bar of their amounts and a column each: amount, number of sales and share of the amount. --}}
@php
    use Modules\Core\Filament\Support\AppFormat;

    $tones = ['paid' => 'green', 'pending' => 'amber', 'overdue' => 'red', 'unbilled' => 'slate'];
    $share = fn (int $amount): int => $overview['amount'] > 0 ? (int) round($amount / $overview['amount'] * 100) : 0;
    $peak = max(1, ...$overview['parts']);
@endphp

<div class="sfk-dash-cell sfk-dash-span-{{ $span }}">
    <style>
        .sfk-overview-kpis { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr); gap: var(--sfk-space-4); }
        .sfk-overview-kpi { min-width: 0; }
        .sfk-overview-kpi + .sfk-overview-kpi { padding-left: var(--sfk-space-4); border-left: 1px solid var(--sfk-line-soft); }
        .sfk-overview-label { margin: 0; color: var(--sfk-muted); font-size: var(--sfk-fs-small); }
        .sfk-overview-value { margin: 2px 0 4px; color: var(--sfk-ink); font-size: 20px; font-weight: var(--sfk-fw-bold); line-height: 1.25; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .sfk-overview-value small { margin-left: 2px; color: var(--sfk-muted); font-size: var(--sfk-fs-small); font-weight: var(--sfk-fw-medium); }
        .sfk-overview-count-row { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--sfk-space-3); }
        .sfk-overview-spark { display: flex; align-items: flex-end; gap: 3px; width: 84px; height: 30px; flex: none; }
        .sfk-overview-spark i { flex: 1; min-height: 3px; border-radius: 2px 2px 0 0; background: var(--sfk-primary); opacity: .3; }
        .sfk-overview-spark i:last-child { opacity: .85; }
        .sfk-overview-bar { display: flex; gap: 2px; height: 10px; margin: var(--sfk-space-5) 0 var(--sfk-space-4); overflow: hidden; border-radius: 9999px; background: var(--sfk-line-soft); }
        .sfk-overview-bar span { height: 100%; background: var(--sfk-tone); }
        .sfk-overview-statuses { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: var(--sfk-space-3); margin: 0; }
        .sfk-overview-status { min-width: 0; padding-left: var(--sfk-space-3); border-left: 3px solid var(--sfk-tone); }
        .sfk-overview-status dt { color: var(--sfk-text); font-size: var(--sfk-fs-small); font-weight: var(--sfk-fw-medium); white-space: nowrap; }
        .sfk-overview-status dd { margin: 2px 0 0; }
        .sfk-overview-status-amount { display: block; overflow: hidden; color: var(--sfk-ink); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-bold); font-variant-numeric: tabular-nums; text-overflow: ellipsis; white-space: nowrap; }
        .sfk-overview-status-note { display: block; color: var(--sfk-muted); font-size: var(--sfk-fs-badge); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .sfk-overview-status.is-alert .sfk-overview-status-amount { color: var(--sfk-danger-text, var(--sfk-danger)); }
        .sfk-tone-slate { --sfk-tone: #64748b; --sfk-tone-soft: #f1f5f9; }
        @media (max-width: 640px) {
            .sfk-overview-kpis { grid-template-columns: minmax(0, 1fr); }
            .sfk-overview-kpi + .sfk-overview-kpi { padding: var(--sfk-space-3) 0 0; border-top: 1px solid var(--sfk-line-soft); border-left: 0; }
            .sfk-overview-statuses { grid-template-columns: repeat(2, minmax(0, 1fr)); row-gap: var(--sfk-space-4); }
        }
    </style>

    <section class="sfk-dash-card" aria-labelledby="sfk-overview-title">
        <header class="sfk-dash-card-head">
            <div class="sfk-dash-card-titles">
                <h2 class="sfk-dash-card-title" id="sfk-overview-title">売上概要</h2>
                <span class="sfk-dash-caption">{{ $period->shortLabel() }}</span>
            </div>
            @if ($salesUrl !== null)
                <a href="{{ $salesUrl }}" class="sfk-dash-more" title="売上入力を開く"><x-filament::icon icon="heroicon-m-arrow-right" /><span class="fi-sr-only">売上入力を開く</span></a>
            @endif
        </header>

        <div class="sfk-overview-kpis">
            <div class="sfk-overview-kpi">
                <p class="sfk-overview-label">売上金額（税抜）</p>
                <p class="sfk-overview-value">{{ AppFormat::yen($overview['amount']) }}</p>
                @if ($yearChange !== null)
                    <span @class(['sfk-dash-trend', 'is-down' => $yearChange < 0, 'is-flat' => $yearChange == 0])>前年同期比 {{ $yearChange >= 0 ? '▲' : '▼' }} {{ abs($yearChange) }}%</span>
                @else
                    <span class="sfk-dash-caption">前年同期の売上はありません</span>
                @endif
            </div>
            <div class="sfk-overview-kpi">
                <p class="sfk-overview-label">売上件数</p>
                <div class="sfk-overview-count-row">
                    <p class="sfk-overview-value">{{ number_format($overview['count']) }}<small>件</small></p>
                    <div class="sfk-overview-spark" role="img" aria-label="期間内の売上件数の推移">
                        @foreach ($overview['parts'] as $count)
                            <i style="height: {{ max(8, (int) round($count / $peak * 100)) }}%" title="{{ number_format($count) }}件"></i>
                        @endforeach
                    </div>
                </div>
                @if ($countChange !== null)
                    <span @class(['sfk-dash-trend', 'is-down' => $countChange < 0, 'is-flat' => $countChange == 0])>前期間比 {{ $countChange >= 0 ? '▲' : '▼' }} {{ abs($countChange) }}%</span>
                @else
                    <span class="sfk-dash-caption">前期間の売上はありません</span>
                @endif
            </div>
        </div>

        <div class="sfk-overview-bar" role="img" aria-label="入金の状況（売上金額の割合）">
            @foreach ($labels as $key => $label)
                @if (($width = $share($overview['statuses'][$key]['amount'])) > 0)
                    <span class="sfk-tone-{{ $tones[$key] }}" style="width: {{ $width }}%" title="{{ $label }} {{ AppFormat::yen($overview['statuses'][$key]['amount']) }}（{{ $width }}%）"></span>
                @endif
            @endforeach
        </div>

        <dl class="sfk-overview-statuses">
            @foreach ($labels as $key => $label)
                <div @class(['sfk-overview-status', 'sfk-tone-'.$tones[$key], 'is-alert' => $key === 'overdue' && $overview['statuses'][$key]['amount'] > 0])>
                    <dt>{{ $label }}</dt>
                    <dd>
                        <span class="sfk-overview-status-amount" title="{{ AppFormat::yen($overview['statuses'][$key]['amount']) }}">{{ AppFormat::yen($overview['statuses'][$key]['amount']) }}</span>
                        <span class="sfk-overview-status-note">{{ number_format($overview['statuses'][$key]['count']) }}件・{{ $share($overview['statuses'][$key]['amount']) }}%</span>
                    </dd>
                </div>
            @endforeach
        </dl>
    </section>
</div>
