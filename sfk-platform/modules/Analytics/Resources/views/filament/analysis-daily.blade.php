{{--
    分析 → 日報分析 (Modules\Analytics\Filament\Pages\DailyReportAnalysis): the summary cards, the 年度, then the ranking —
    per 作業者 and month the amount made (枚数単価 + 型替え単価) with the hours worked under it (作業時間 + 残業時間).
--}}
@php
    use Modules\Analytics\Filament\Pages\DailyReportAnalysis;
    use Modules\Core\Filament\Support\AppFormat;

    $yen = fn (int $amount): string => AppFormat::yen($amount);
    $hours = fn (float $value): string => DailyReportAnalysis::hours($value);
    $monthLabel = fn (int $month): string => $month.'月';
@endphp

<x-filament-panels::page>
    @include('analytics::filament.analysis-styles')

    <div class="sfk-an">
        @include('core::filament.app-summary', ['cards' => $daily['cards']])

        <div class="sfk-an-toolbar">
            <x-filament::input.wrapper class="sfk-an-year">
                <x-filament::input.select wire:model.live="year" aria-label="年度">
                    @foreach (array_reverse($daily['available']) as $year)
                        <option value="{{ $year }}">{{ $year }}年度</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        <section class="sfk-an-section" aria-labelledby="sfk-an-ranking">
            <h2 class="sfk-an-heading" id="sfk-an-ranking">年度別金額ランキング <small>{{ $daily['year'] }}年度（{{ $daily['year'] }}年5月〜{{ $daily['year'] + 1 }}年4月）・金額／作業時間</small></h2>
            <div class="sfk-an-card">
                @if ($daily['ranking'] === [])
                    <p class="sfk-an-empty">この年度の日報はありません。</p>
                @else
                    <table class="sfk-an-table is-dual">
                        <thead>
                            <tr>
                                <th class="is-rank">順位</th>
                                <th class="is-name">作業者</th>
                                @foreach ($months as $month)
                                    <th>{{ $monthLabel($month) }}</th>
                                @endforeach
                                <th class="is-total">年間合計</th>
                                <th class="is-total">総作業時間</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($daily['ranking'] as $row)
                                <tr wire:key="worker-{{ $row['user_id'] }}">
                                    <td class="is-rank"><span @class(['sfk-an-medal', 'is-'.$row['rank'] => $row['rank'] <= 3])>{{ $row['rank'] }}</span></td>
                                    <td class="is-name">{{ $row['name'] }}</td>
                                    @foreach ($months as $index => $month)
                                        @if ($daily['year'] === $currentYear && $index >= $futureFrom)
                                            <td class="is-future">－</td>
                                        @else
                                            <td>{{ $yen($row['months'][$month]) }}<small>{{ $hours($row['hours'][$month]) }}</small></td>
                                        @endif
                                    @endforeach
                                    <td class="is-total">{{ $yen($row['total']) }}</td>
                                    <td class="is-total">{{ $hours($row['total_hours']) }}</td>
                                </tr>
                            @endforeach
                            <tr class="is-sum">
                                <td></td>
                                <td class="is-name">合計</td>
                                @foreach ($months as $index => $month)
                                    @if ($daily['year'] === $currentYear && $index >= $futureFrom)
                                        <td class="is-future">－</td>
                                    @else
                                        <td>{{ $yen($daily['totals'][$month]) }}<small>{{ $hours($daily['totalHours'][$month]) }}</small></td>
                                    @endif
                                @endforeach
                                <td class="is-total">{{ $yen(array_sum($daily['totals'])) }}</td>
                                <td class="is-total">{{ $hours(array_sum($daily['totalHours'])) }}</td>
                            </tr>
                        </tbody>
                    </table>
                @endif
            </div>
        </section>
    </div>
</x-filament-panels::page>
