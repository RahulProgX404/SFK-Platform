{{--
    Summary cards above the 一覧 tabs (AppListRecords::summaryCards(), design_ref/app-index.png): label, value and an
    icon in the card's tone, for the listed records as filtered and searched.
    $cards: list<array{label: string, value: string, tone?: string, icon?: string}>
--}}
<section @class(['sfk-app-summary', 'is-compact' => count($cards) > 4]) aria-label="集計">
    @foreach ($cards as $card)
        <div class="sfk-stat sfk-tone-{{ $card['tone'] ?? 'blue' }}">
            <div class="sfk-stat-text">
                <div class="sfk-stat-label">{{ $card['label'] }}</div>
                <div class="sfk-stat-value">{{ $card['value'] }}</div>
            </div>
            <span class="sfk-stat-icon" aria-hidden="true">
                <x-filament::icon :icon="$card['icon'] ?? 'heroicon-o-chart-bar'" class="sfk-stat-icon-svg" />
            </span>
        </div>
    @endforeach
</section>
