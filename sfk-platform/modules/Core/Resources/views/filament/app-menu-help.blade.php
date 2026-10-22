{{--
    ボタンの説明: the legacy menu text of the page (menuHelp(): button label => explanation) as a collapsible panel under
    the toolbar; opened or closed is remembered per screen in the browser.
    $help: array<string, string>, $key: storage key of the screen.
--}}
<section
    class="sfk-app-help"
    x-data="{
        open: true,
        init() {
            try { this.open = localStorage.getItem(@js($key)) !== 'closed' } catch (e) {}
        },
        toggle() {
            this.open = ! this.open
            try { localStorage.setItem(@js($key), this.open ? 'open' : 'closed') } catch (e) {}
        },
    }"
>
    <button type="button" class="sfk-app-help-bar" x-on:click="toggle()" x-bind:aria-expanded="open ? 'true' : 'false'">
        <x-filament::icon icon="heroicon-o-information-circle" class="sfk-app-help-icon" />
        <span class="sfk-app-help-title">ボタンの説明</span>
        <x-filament::icon icon="heroicon-m-chevron-down" class="sfk-app-help-chevron" x-bind:class="{ 'is-open': open }" />
    </button>

    <dl class="sfk-app-help-list" x-show="open" x-collapse>
        @foreach ($help as $label => $text)
            <div class="sfk-app-help-item">
                <dt>{{ $label }}</dt>
                <dd>{{ $text }}</dd>
            </div>
        @endforeach
    </dl>
</section>
