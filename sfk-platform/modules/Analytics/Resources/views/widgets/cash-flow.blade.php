{{-- 入出金 (Modules\Analytics\Filament\Widgets\CashFlowCard): a narrow card, its three tinted boxes one under the other;
     個人 ($waiting): 入金 and the user's sales still to be paid (入金待ち, 期限超過) --}}
@php
    use Modules\Analytics\Domain\ShortYen;
    use Modules\Core\Filament\Support\AppFormat;

    $boxes = $waiting === null ? [
        ['label' => '入金', 'amount' => $cash['inflow'], 'tone' => 'green', 'icon' => 'heroicon-o-arrow-down-left'],
        ['label' => '出金', 'amount' => $cash['outflow'], 'tone' => 'red', 'icon' => 'heroicon-o-arrow-up-right'],
        ['label' => '差引', 'amount' => $cash['net'], 'tone' => 'amber', 'icon' => 'heroicon-o-scale'],
    ] : [
        ['label' => '入金', 'amount' => $cash['inflow'], 'tone' => 'green', 'icon' => 'heroicon-o-arrow-down-left'],
        ['label' => '入金待ち', 'amount' => $waiting['pending']['amount'], 'tone' => 'amber', 'icon' => 'heroicon-o-clock'],
        ['label' => '期限超過', 'amount' => $waiting['overdue']['amount'], 'tone' => 'red', 'icon' => 'heroicon-o-exclamation-triangle'],
    ];
@endphp

<div class="sfk-dash-cell sfk-dash-span-2">
    <style>
        .sfk-cash-boxes { display: flex; flex: 1; flex-direction: column; gap: var(--sfk-space-2); }
        .sfk-cash-box { position: relative; display: flex; flex-direction: column; justify-content: center; gap: 2px; flex: 1; min-width: 0; padding: var(--sfk-space-3); overflow: hidden; border-radius: var(--sfk-radius-lg); background: var(--sfk-tone-soft); }
        .sfk-cash-box::after { content: ""; position: absolute; right: -18px; top: -18px; width: 64px; height: 64px; border-radius: 9999px; background: var(--sfk-tone); opacity: .14; filter: blur(4px); }
        .sfk-cash-name { display: flex; align-items: center; gap: var(--sfk-space-2); color: var(--sfk-text); font-size: var(--sfk-fs-small); }
        .sfk-cash-name .fi-icon { width: 16px; height: 16px; flex: none; color: var(--sfk-tone); }
        .sfk-cash-amount { color: var(--sfk-ink); font-size: var(--sfk-fs-subheading); font-weight: var(--sfk-fw-bold); font-variant-numeric: tabular-nums; white-space: nowrap; }
    </style>

    <section class="sfk-dash-card sfk-cash" aria-labelledby="sfk-cash-title">
        <header class="sfk-dash-card-head">
            <h2 class="sfk-dash-card-title" id="sfk-cash-title">入出金</h2>
            @if ($receiptsUrl !== null)
                <a href="{{ $receiptsUrl }}" class="sfk-dash-more" title="売入金管理を開く"><x-filament::icon icon="heroicon-m-arrow-right" /><span class="fi-sr-only">売入金管理を開く</span></a>
            @endif
        </header>

        <div class="sfk-cash-boxes">
            @foreach ($boxes as $box)
                <div class="sfk-cash-box sfk-tone-{{ $box['tone'] }}" title="{{ $box['label'] }} {{ AppFormat::yen($box['amount']) }}">
                    <span class="sfk-cash-name"><x-filament::icon :icon="$box['icon']" />{{ $box['label'] }}</span>
                    <span class="sfk-cash-amount">{{ ShortYen::format($box['amount']) }}</span>
                </div>
            @endforeach
        </div>
    </section>
</div>
