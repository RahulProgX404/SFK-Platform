{{--
    ボタンの説明: the legacy menu text of the page (menuHelp(): button label => explanation) in a popup panel next to the
    app's buttons, opened and closed by the (i) switch ($store.sfkAppMenu.help).
    $help: array<string, string>
--}}
<section class="sfk-app-help" x-data x-show="$store.sfkAppMenu.help" x-cloak x-transition.opacity role="dialog" aria-label="ボタンの説明">
    <header class="sfk-app-help-head">
        <x-filament::icon icon="heroicon-o-information-circle" class="sfk-app-help-icon" />
        <span class="sfk-app-help-title">ボタンの説明</span>
        <button type="button" class="sfk-app-help-close" x-on:click="$store.sfkAppMenu.help = false" title="閉じる">
            <x-filament::icon icon="heroicon-m-x-mark" class="sfk-app-help-close-icon" />
            <span class="fi-sr-only">閉じる</span>
        </button>
    </header>

    <dl class="sfk-app-help-list">
        @foreach ($help as $label => $text)
            <div class="sfk-app-help-item">
                <dt>{{ $label }}</dt>
                <dd>{{ $text }}</dd>
            </div>
        @endforeach
    </dl>
</section>
