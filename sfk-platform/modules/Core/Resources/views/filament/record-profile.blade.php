{{--
    The head of a record page with tabs (design_ref/customer-overview.png; e.g. 01. 取引先マスタ): the record's initial,
    code, name, badges and date, a row of contact details, its summary cards on the right, and the tabs (概要 + the
    tabs other modules add through Modules\Core\Filament\Records\RecordTabs).
    $initial, $code, $name, $badges (list<string>), $meta (string|null), $info (list<array{icon, label, value}>),
    $cards (summary cards), $tabs (list<array{key, label, icon}>), $active (key of the tab shown).
--}}
<div class="sfk-profile-card">
    <div class="sfk-profile-card-main">
        <div class="sfk-profile-id">
            <span class="sfk-profile-avatar" aria-hidden="true">{{ $initial }}</span>
            <div class="sfk-profile-id-text">
                @if (filled($code))
                    <div class="sfk-profile-code">{{ $code }}</div>
                @endif
                <div class="sfk-profile-name">
                    {{ $name }}
                    @foreach ($badges as $badge)
                        <span class="sfk-profile-badge">{{ $badge }}</span>
                    @endforeach
                </div>
                @if (filled($meta))
                    <div class="sfk-profile-meta">
                        <x-filament::icon icon="heroicon-o-calendar" class="sfk-profile-meta-icon" />
                        {{ $meta }}
                    </div>
                @endif
            </div>
        </div>

        @if ($info !== [])
            <dl class="sfk-profile-info">
                @foreach ($info as $item)
                    <div>
                        <dt><x-filament::icon :icon="$item['icon']" class="sfk-profile-info-icon" />{{ $item['label'] }}</dt>
                        <dd>{{ filled($item['value']) ? $item['value'] : '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </div>

    @if ($cards !== [])
        <div class="sfk-profile-cards">
            @foreach ($cards as $card)
                <div class="sfk-profile-stat sfk-tone-{{ $card['tone'] ?? 'blue' }}">
                    <div>
                        <div class="sfk-profile-stat-value">{{ $card['value'] }}</div>
                        <div class="sfk-profile-stat-label"><span class="sfk-profile-stat-dot" aria-hidden="true"></span>{{ $card['label'] }}</div>
                    </div>
                    <span class="sfk-profile-stat-icon" aria-hidden="true"><x-filament::icon :icon="$card['icon'] ?? 'heroicon-o-banknotes'" class="sfk-profile-stat-svg" /></span>
                </div>
            @endforeach
        </div>
    @endif
</div>

@if (count($tabs) > 1)
    <nav class="sfk-profile-tabs" aria-label="レコードのタブ">
        @foreach ($tabs as $tab)
            <button type="button" wire:click="$set('recordTab', @js($tab['key']))"
                @class(['sfk-profile-tab', 'is-active' => $active === $tab['key']])
                @if ($active === $tab['key']) aria-current="page" @endif>
                <x-filament::icon :icon="$tab['icon']" class="sfk-profile-tab-icon" />
                <span>{{ $tab['label'] }}</span>
            </button>
        @endforeach
    </nav>
@endif
