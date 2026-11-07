{{-- 個人の概要 (Modules\Analytics\Filament\Widgets\PersonalOverviewCard, …\Users\UserOverviewCard; overview.png): a dark
     band across the whole row — the title, the period (or, on a user's page, its choice) and five figures with icons
     (出勤日数 = the days with a 日報, bug.md 2026-11-07) --}}
@php
    use Modules\Core\Filament\Support\AppFormat;

    $number = fn (float $value): string => rtrim(rtrim(number_format($value, 2), '0'), '.');
    $items = $figures === null ? [] : [
        ['label' => '勤務時間', 'icon' => 'heroicon-o-clock', 'value' => $number($figures['hours']), 'unit' => '時間'],
        ['label' => '出勤日数', 'icon' => 'heroicon-o-briefcase', 'value' => number_format($figures['work_days']), 'unit' => '日'],
        ['label' => '収入', 'icon' => 'heroicon-o-banknotes', 'value' => AppFormat::yen($figures['income']), 'unit' => ''],
        ['label' => '休暇日数', 'icon' => 'heroicon-o-calendar-days', 'value' => $number($figures['leave_days']), 'unit' => '日'],
        ['label' => '有給休暇', 'icon' => 'heroicon-o-sun', 'value' => $number($figures['paid_leave_days']), 'unit' => '日'],
    ];
@endphp

<div @class(['sfk-dash-cell sfk-dash-span-12' => $ranges === null, 'sfk-personal-wrap' => $ranges !== null])>
    @if ($shown)
        <style>
            .sfk-personal-wrap { display: flex; }
            .sfk-personal-wrap > * { flex: 1; min-width: 0; }
            .sfk-personal { position: relative; display: flex; flex-direction: column; gap: var(--sfk-space-4); padding: var(--sfk-space-5) var(--sfk-space-6); overflow: hidden; border-radius: var(--sfk-radius-xl); background: radial-gradient(circle at 78% 120%, rgba(91, 150, 230, .55), transparent 55%), linear-gradient(115deg, #071a33 0%, #0b2c57 38%, var(--sfk-primary) 72%, #2c6cc0 100%); box-shadow: 0 10px 24px rgba(5, 70, 126, .22); color: #fff; }
            /* the stars and the wire hill of overview.png */
            .sfk-personal::before { content: ""; position: absolute; inset: 0; background-image: radial-gradient(rgba(255, 255, 255, .55) 1px, transparent 1.5px), radial-gradient(rgba(255, 255, 255, .3) 1px, transparent 1.5px); background-position: 0 0, 37px 23px; background-size: 140px 90px, 90px 70px; opacity: .45; pointer-events: none; }
            .sfk-personal-mesh { position: absolute; right: 0; bottom: 0; width: min(42%, 380px); height: 100%; color: rgba(255, 255, 255, .28); pointer-events: none; }
            .sfk-personal-head { position: relative; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--sfk-space-3); }
            .sfk-personal-title { margin: 0; font-size: var(--sfk-fs-heading); font-weight: var(--sfk-fw-bold); }
            .sfk-personal-tools { display: inline-flex; flex-wrap: wrap; align-items: center; gap: var(--sfk-space-2); }
            .sfk-personal-period { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: var(--sfk-radius); background: rgba(255, 255, 255, .14); color: rgba(255, 255, 255, .92); font-size: var(--sfk-fs-badge); font-variant-numeric: tabular-nums; white-space: nowrap; }
            .sfk-personal-period .fi-icon { width: 14px; height: 14px; }
            .sfk-personal-range { height: 30px; padding: 0 28px 0 10px; border: 0; border-radius: var(--sfk-radius); background-color: rgba(255, 255, 255, .16); color: #fff; font: inherit; font-size: var(--sfk-fs-small); }
            .sfk-personal-range option { color: var(--sfk-ink); }
            .sfk-personal-range:focus { outline: none; box-shadow: 0 0 0 2px rgba(255, 255, 255, .5); }
            .sfk-personal-figures { position: relative; display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: var(--sfk-space-4); margin: 0; }
            .sfk-personal-figure dt { display: flex; align-items: center; gap: var(--sfk-space-2); color: rgba(255, 255, 255, .88); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-medium); }
            .sfk-personal-figure dt .fi-icon { width: 18px; height: 18px; flex: none; }
            .sfk-personal-figure dd { margin: 6px 0 0; font-size: 1.25rem; font-weight: var(--sfk-fw-bold); font-variant-numeric: tabular-nums; line-height: 1.3; overflow-wrap: anywhere; }
            .sfk-personal-figure dd small { margin-left: 3px; color: rgba(255, 255, 255, .8); font-size: var(--sfk-fs-small); font-weight: var(--sfk-fw-medium); }
            @media (max-width: 767px) {
                .sfk-personal { padding: var(--sfk-space-4); }
                .sfk-personal-figures { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }
        </style>

        <section class="sfk-personal" aria-labelledby="sfk-personal-title-{{ $this->getId() }}">
            <svg class="sfk-personal-mesh" viewBox="0 0 380 150" preserveAspectRatio="xMaxYMax meet" fill="none" stroke="currentColor" stroke-width=".8" aria-hidden="true">
                @for ($i = 0; $i < 9; $i++)
                    <path d="M{{ 20 + $i * 8 }} 150 C {{ 120 + $i * 6 }} {{ 140 - $i * 9 }}, {{ 190 + $i * 4 }} {{ 30 + $i * 6 }}, {{ 250 + $i * 2 }} {{ 18 + $i * 9 }} S {{ 330 + $i * 3 }} {{ 70 + $i * 8 }}, 380 {{ 60 + $i * 10 }}" />
                @endfor
                @for ($i = 0; $i < 8; $i++)
                    <path d="M{{ 150 + $i * 28 }} 150 L {{ 205 + $i * 16 }} {{ 40 + abs(4 - $i) * 14 }}" />
                @endfor
            </svg>

            <header class="sfk-personal-head">
                <h2 class="sfk-personal-title" id="sfk-personal-title-{{ $this->getId() }}">個人の概要</h2>
                <span class="sfk-personal-tools">
                    <span class="sfk-personal-period"><x-filament::icon icon="heroicon-o-calendar" />{{ $period->label() }}</span>
                    @if ($ranges !== null)
                        <select class="sfk-personal-range" wire:model.live="range" aria-label="期間">
                            @foreach ($ranges as $key => $rangeLabel)
                                <option value="{{ $key }}">{{ $rangeLabel }}</option>
                            @endforeach
                        </select>
                    @endif
                </span>
            </header>

            <dl class="sfk-personal-figures">
                @foreach ($items as $item)
                    <div class="sfk-personal-figure">
                        <dt><x-filament::icon :icon="$item['icon']" />{{ $item['label'] }}</dt>
                        <dd>{{ $item['value'] }}@if ($item['unit'] !== '')<small>{{ $item['unit'] }}</small>@endif</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endif
</div>
