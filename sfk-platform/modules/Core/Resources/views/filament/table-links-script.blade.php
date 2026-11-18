{{--
    Table rows open their record in place (Modules\Core\Filament\Tables\RecordLink): one click listener for the whole
    page instead of a wire:navigate on every cell. A plain left click on a link marked data-sfk-navigate is opened with
    Alpine.navigate() — as wire:navigate did; Ctrl / ⌘ / Shift / Alt / middle click keep the browser's own behaviour
    (new tab, new window). Clicks another handler already took (prevented) are left alone. Part of the cached panel
    script (ThemeAssets); runs once and survives the in-place page swaps. Statements end with ";" (Blade).
--}}
<script>
    (() => {
        if (window.sfkTableLinks) {
            return;
        }
        window.sfkTableLinks = true;

        document.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
                return;
            }
            const link = event.target instanceof Element ? event.target.closest('a[data-sfk-navigate]') : null;
            if (!link || !window.Alpine?.navigate) {
                return;
            }
            event.preventDefault();
            window.Alpine.navigate(link.getAttribute('href'));
        });
    })();
</script>
