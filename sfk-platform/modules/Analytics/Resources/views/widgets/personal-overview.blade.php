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
    {{-- live: redrawn when what it reads changes (DashboardCard::liveTables(), DataPulse) --}}
    @include('core::filament.live-data', ['tables' => $this::liveTables()])
    @if ($shown)
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
