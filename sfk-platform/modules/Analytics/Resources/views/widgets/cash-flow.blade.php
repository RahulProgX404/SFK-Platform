{{-- 入出金 (Modules\Analytics\Filament\Widgets\CashFlowCard): 入金 and 出金 of the period with their sign, then 差引 as the
     result (green when money came in, red when more went out); 個人 ($waiting): 入金 and the user's sales still to be paid
     (入金待ち, 期限超過) instead. Whole yen like the rest of the dashboard. --}}
@php
    use Modules\Core\Filament\Support\AppFormat;

    $rows = $waiting === null ? [
        ['label' => '入金', 'amount' => $cash['inflow'], 'sign' => '+', 'tone' => 'green', 'icon' => 'heroicon-o-arrow-down-left'],
        ['label' => '出金', 'amount' => $cash['outflow'], 'sign' => '−', 'tone' => 'red', 'icon' => 'heroicon-o-arrow-up-right'],
    ] : [
        ['label' => '入金', 'amount' => $cash['inflow'], 'sign' => '+', 'tone' => 'green', 'icon' => 'heroicon-o-arrow-down-left'],
        ['label' => '入金待ち', 'amount' => $waiting['pending']['amount'], 'sign' => '', 'tone' => 'amber', 'icon' => 'heroicon-o-clock'],
        ['label' => '期限超過', 'amount' => $waiting['overdue']['amount'], 'sign' => '', 'tone' => 'red', 'icon' => 'heroicon-o-exclamation-triangle'],
    ];
    $net = $cash['net'];
@endphp

<div class="sfk-dash-cell sfk-dash-span-3">
    <style>
        .sfk-cash-rows { display: flex; flex-direction: column; margin: 0; }
        .sfk-cash-row { display: flex; align-items: center; justify-content: space-between; gap: var(--sfk-space-3); padding: var(--sfk-space-3) 0; }
        .sfk-cash-row + .sfk-cash-row { border-top: 1px solid var(--sfk-line-soft); }
        .sfk-cash-row dt { display: flex; align-items: center; gap: var(--sfk-space-2); color: var(--sfk-text); font-size: var(--sfk-fs-body); }
        .sfk-cash-icon { display: grid; place-items: center; width: 28px; height: 28px; flex: none; border-radius: var(--sfk-radius); background: var(--sfk-tone-soft); color: var(--sfk-tone); }
        .sfk-cash-icon .fi-icon { width: 16px; height: 16px; }
        .sfk-cash-row dd { margin: 0; color: var(--sfk-ink); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-semibold); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .sfk-cash-net { display: flex; align-items: baseline; justify-content: space-between; gap: var(--sfk-space-3); margin-top: auto; padding: var(--sfk-space-3) var(--sfk-space-4); border-radius: var(--sfk-radius-lg); background: var(--sfk-success-soft); }
        .sfk-cash-net.is-minus { background: var(--sfk-danger-soft); }
        .sfk-cash-net span { color: var(--sfk-text); font-size: var(--sfk-fs-small); font-weight: var(--sfk-fw-semibold); }
        .sfk-cash-net strong { color: var(--sfk-success); font-size: var(--sfk-fs-figure); font-weight: var(--sfk-fw-bold); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .sfk-cash-net.is-minus strong { color: var(--sfk-danger-text, var(--sfk-danger)); }
    </style>

    <section class="sfk-dash-card sfk-cash" aria-labelledby="sfk-cash-title">
        <header class="sfk-dash-card-head">
            <div class="sfk-dash-card-titles">
                <h2 class="sfk-dash-card-title" id="sfk-cash-title">入出金</h2>
                <span class="sfk-dash-caption">{{ $this->periodCaption() }}</span>
            </div>
            @if ($receiptsUrl !== null)
                <a href="{{ $receiptsUrl }}" class="sfk-dash-more" title="売入金管理を開く"><x-filament::icon icon="heroicon-m-arrow-right" /><span class="fi-sr-only">売入金管理を開く</span></a>
            @endif
        </header>

        <dl class="sfk-cash-rows">
            @foreach ($rows as $row)
                <div class="sfk-cash-row sfk-tone-{{ $row['tone'] }}">
                    <dt><span class="sfk-cash-icon" aria-hidden="true"><x-filament::icon :icon="$row['icon']" /></span>{{ $row['label'] }}</dt>
                    <dd>{{ $row['amount'] > 0 ? $row['sign'] : '' }}{{ AppFormat::yen($row['amount']) }}</dd>
                </div>
            @endforeach
        </dl>

        @if ($waiting === null)
            <div @class(['sfk-cash-net', 'is-minus' => $net < 0])>
                <span>差引</span>
                <strong>{{ $net < 0 ? '−'.AppFormat::yen(abs($net)) : AppFormat::yen($net) }}</strong>
            </div>
        @endif
    </section>
</div>
