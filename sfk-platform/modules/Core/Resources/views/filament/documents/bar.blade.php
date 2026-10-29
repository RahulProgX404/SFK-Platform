{{--
    上部スペース of an app screen (帳票出力): the 帳票 drop-down and 出力, like the legacy PrintCreator widget. 出力設定 with
    the same ボタン名 are listed under it by サブボタン名.
    $bar: documentBar() of the page; $kind: list (the listed records, printDocuments action) or view (this record: a
    link, or printDocument when the ファイル名 has 「{ユーザー選択}」).
--}}
@php
    use Filament\Support\Icons\Heroicon;

    $links = collect($bar)->keyBy('key')->all();
    $groups = collect($bar)->groupBy('name', preserveKeys: false);
@endphp

<div class="sfk-docs-bar" x-data="{ selected: @js($bar[0]['key']), links: @js($links) }" role="group" aria-label="帳票出力">
    <x-filament::icon :icon="Heroicon::OutlinedPrinter" class="sfk-docs-bar-icon" />
    <label class="sfk-filter-select sfk-docs-bar-select">
        <span class="fi-sr-only">帳票</span>
        <select x-model="selected">
            @foreach ($groups as $name => $items)
                @if (count($items) === 1)
                    <option value="{{ $items[0]['key'] }}">{{ $name }}</option>
                @else
                    <optgroup label="{{ $name }}">
                        @foreach ($items as $item)
                            <option value="{{ $item['key'] }}">{{ $item['label'] }}</option>
                        @endforeach
                    </optgroup>
                @endif
            @endforeach
        </select>
    </label>
    @if ($kind === 'list')
        <button type="button" class="sfk-filter-btn sfk-filter-btn-submit sfk-docs-bar-btn"
            x-on:click="$wire.mountAction('printDocuments', { output: Number(selected) })">出力</button>
    @else
        <a href="{{ $bar[0]['url'] }}" @if ($bar[0]['newTab']) target="_blank" @endif
            class="sfk-filter-btn sfk-filter-btn-submit sfk-docs-bar-btn"
            x-bind:href="links[selected].url" x-bind:target="links[selected].newTab ? '_blank' : '_self'"
            x-on:click="if (links[selected].asks) { $event.preventDefault(); $wire.mountAction('printDocument', { output: Number(selected) }) }">出力</a>
    @endif
</div>
