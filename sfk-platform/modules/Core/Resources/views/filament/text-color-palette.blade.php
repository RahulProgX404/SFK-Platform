{{--
    文字色 of a rich text editor (Modules\Core\Filament\Support\RichEditorTools): the editor's 文字色 button opens this
    palette right under it; a click on a colour colours the selection at once, 色なし removes the colour.
    $colors: name => [label, color] (the editor's text colours, stored by name).
--}}
<div
    x-data="{
        open: false,
        editor: null,
        top: 0,
        left: 0,
        show(detail) {
            const rect = detail.anchor.getBoundingClientRect()
            this.editor = detail.editor
            this.top = rect.bottom + 4
            this.left = Math.min(rect.left, window.innerWidth - 260)
            this.open = ! this.open
        },
        // the editor itself, not Alpine's reactive copy: ProseMirror refuses transactions of a proxied state
        pick(color) {
            Alpine.raw(this.editor)?.chain().focus().setTextColor({ color }).run()
            this.open = false
        },
        clear() {
            Alpine.raw(this.editor)?.chain().focus().unsetTextColor().run()
            this.open = false
        },
    }"
    x-on:sfk-text-palette.window="show($event.detail)"
    x-on:keydown.escape.window="open = false"
    x-on:scroll.window="open = false"
>
    {{-- inside the dialog (not teleported): the dialog's focus trap blocks clicks outside it --}}
    <div x-show="open" x-cloak x-on:click.outside="open = false" class="sfk-text-palette" x-bind:style="`top: ${top}px; left: ${left}px`" role="dialog" aria-label="文字色">
        <div class="sfk-text-palette-grid">
            @foreach ($colors as $name => $color)
                <button type="button" class="sfk-text-palette-swatch" style="background: {{ $color['color'] }}" title="{{ $color['label'] }}" x-on:click="pick(@js($name))">
                    <span class="fi-sr-only">{{ $color['label'] }}</span>
                </button>
            @endforeach
        </div>
        <button type="button" class="sfk-filter-link sfk-text-palette-clear" x-on:click="clear()">色なし</button>
    </div>
</div>
