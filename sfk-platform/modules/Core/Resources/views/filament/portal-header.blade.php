{{--
    Header of the dashboard (Modules\Core\Filament\Portal\PortalPage; design_ref/Dashboard.png): ダッシュボード, then
    全体 | 個人 (whose money the cards show; users whose 財務分析の範囲 is 全体), the period (a date button: presets or
    two dates) and 新規作成 (a menu of the apps the user may add records to — the page itself when there is one,
    disabled when there is none).
    $from, $to: Y-m-d; $scope: overall | personal; $switchesScope: bool; $createItems: label => [url, app] (app: the quick-create panel it opens on the dashboard, AppResource::QUICK_CREATE_EVENT)
--}}
@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Js;
    use Modules\Core\Filament\AppResource;

    $label = Carbon::parse($from)->format('Y/m/d').' 〜 '.Carbon::parse($to)->format('Y/m/d');
    $presets = ['today' => '今日', '7d' => '過去7日間', '30d' => '過去30日間', 'month' => '今月', 'lastMonth' => '先月', 'year' => '今年', 'fiscalYear' => '今年度'];
@endphp

<header class="sfk-dash-header">
    <h1 class="sfk-dash-heading">ダッシュボード</h1>

    <div class="sfk-dash-header-tools">
        @if ($switchesScope)
            <div class="sfk-dash-scope" role="radiogroup" aria-label="表示範囲">
                @foreach (['overall' => ['全体', 'heroicon-o-building-office-2'], 'personal' => ['個人', 'heroicon-o-user']] as $value => [$scopeLabel, $scopeIcon])
                    <button type="button" role="radio" aria-checked="{{ $scope === $value ? 'true' : 'false' }}" @class(['sfk-dash-scope-option', 'is-active' => $scope === $value])
                        wire:click="setScope(@js($value))" wire:loading.attr="disabled">
                        <x-filament::icon :icon="$scopeIcon" class="sfk-dash-scope-icon" />
                        <span>{{ $scopeLabel }}</span>
                    </button>
                @endforeach
            </div>
        @endif

        <x-filament::dropdown placement="bottom-end" width="sm" :teleport="true" class="sfk-dash-date-menu">
            <x-slot name="trigger">
                <button type="button" class="sfk-dash-date" title="期間を変更">
                    <x-filament::icon icon="heroicon-o-calendar" class="sfk-dash-date-icon" />
                    <span>{{ $label }}</span>
                    <x-filament::icon icon="heroicon-m-chevron-down" class="sfk-dash-date-chevron" />
                </button>
            </x-slot>

            <div class="sfk-dash-date-panel" x-data="{ from: @js($from), to: @js($to) }">
                <div class="sfk-dash-date-presets">
                    @foreach ($presets as $preset => $presetLabel)
                        <button type="button" wire:click="setPeriod(@js($preset))" x-on:click="close()">{{ $presetLabel }}</button>
                    @endforeach
                </div>
                <div class="sfk-dash-date-range">
                    <label>開始日<input type="date" x-model="from" class="sfk-dash-date-input"></label>
                    <label>終了日<input type="date" x-model="to" class="sfk-dash-date-input"></label>
                </div>
                <div class="sfk-dash-date-actions">
                    <button type="button" class="sfk-filter-btn sfk-filter-btn-submit" x-on:click="if (from && to) { $wire.applyPeriod(from, to); close() }">適用</button>
                </div>
            </div>
        </x-filament::dropdown>

        @if (count($createItems) === 1)
            @php($item = reset($createItems))
            <a href="{{ $item['url'] }}" class="sfk-dash-create"
                @if ($item['app']) x-on:click.prevent="$dispatch({{ Js::from(AppResource::QUICK_CREATE_EVENT) }}, { app: {{ Js::from($item['app']) }} })" @endif>
                <x-filament::icon icon="heroicon-m-plus-circle" class="sfk-dash-create-icon" />
                <span>新規作成</span>
            </a>
        @elseif ($createItems === [])
            <button type="button" class="sfk-dash-create" disabled title="追加できるアプリがありません">
                <x-filament::icon icon="heroicon-m-plus-circle" class="sfk-dash-create-icon" />
                <span>新規作成</span>
            </button>
        @else
            <x-filament::dropdown placement="bottom-end" width="xs" :teleport="true">
                <x-slot name="trigger">
                    <button type="button" class="sfk-dash-create">
                        <x-filament::icon icon="heroicon-m-plus-circle" class="sfk-dash-create-icon" />
                        <span>新規作成</span>
                        <x-filament::icon icon="heroicon-m-chevron-down" class="sfk-dash-create-icon" />
                    </button>
                </x-slot>
                <x-filament::dropdown.list>
                    @foreach ($createItems as $itemLabel => $item)
                        @if ($item['app'])
                            <x-filament::dropdown.list.item tag="a" :href="$item['url']" icon="heroicon-o-document-plus"
                                x-on:click.prevent="close(); $dispatch({{ Js::from(AppResource::QUICK_CREATE_EVENT) }}, { app: {{ Js::from($item['app']) }} })">{{ $itemLabel }}</x-filament::dropdown.list.item>
                        @else
                            <x-filament::dropdown.list.item tag="a" :href="$item['url']" icon="heroicon-o-document-plus">{{ $itemLabel }}</x-filament::dropdown.list.item>
                        @endif
                    @endforeach
                </x-filament::dropdown.list>
            </x-filament::dropdown>
        @endif
    </div>
</header>
