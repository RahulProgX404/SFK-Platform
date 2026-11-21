{{--
    Live pages (Modules\Core\Application\Realtime\DataPulse, marker core::filament.live-data): every answer of the page
    pulse (`sfk:pulse`, core::filament.notification-pulse-script) carries the stamps of the watched tables (pulse.x.d).
    When one differs from what a marker on the page was drawn with, the marker's Livewire component is refreshed
    ($refresh: same filters, same year, fresh figures) — not while the user is typing in it (then on leaving the input),
    one refresh at a time. With a live WebSocket the pulse is asked only every minute, so a page with a marker asks it
    itself every few seconds while in view. Part of theme.js (ThemeAssets): runs once, survives the page swaps.
    Statements end with ";" (Blade).
--}}
<script>
    (() => {
        if (window.sfkLiveData) {
            return;
        }

        const INTERVAL = 4000;
        let stamps = null;
        const busy = new Set();
        const waiting = new Set();

        const read = (marker) => {
            try {
                return JSON.parse(marker.dataset.sfkLive || '{}') || {};
            } catch (error) {
                return {};
            }
        };

        const typing = (root) => {
            const active = document.activeElement;
            return active instanceof Element && root.contains(active) && active.matches('input, textarea, select, [contenteditable]');
        };

        const refresh = (marker) => {
            const root = marker.closest('[wire\\:id]');
            const id = root?.getAttribute('wire:id');
            const component = id ? window.Livewire?.find(id) : null;
            if (!component || busy.has(id)) {
                return;
            }
            if (typing(root)) {
                if (!waiting.has(id)) {
                    waiting.add(id);
                    root.addEventListener('focusout', () => { waiting.delete(id); window.setTimeout(check, 0); }, { once: true });
                }
                return;
            }
            busy.add(id);
            Promise.resolve(component.$refresh()).catch(() => {}).finally(() => busy.delete(id));
        };

        const check = () => {
            if (!stamps) {
                return;
            }
            document.querySelectorAll('[data-sfk-live]').forEach((marker) => {
                const drawn = read(marker);
                if (Object.keys(drawn).some((table) => typeof stamps[table] === 'string' && stamps[table] !== drawn[table])) {
                    refresh(marker);
                }
            });
        };

        window.addEventListener('sfk:pulse', (event) => {
            const d = event.detail?.x?.d;
            if (d && typeof d === 'object') {
                stamps = d;
                check();
            }
        });

        window.setInterval(() => {
            if (window.sfkRealtime?.connected === true && document.visibilityState === 'visible' && document.querySelector('[data-sfk-live]')) {
                window.sfkNotificationPulse?.check(true);
            }
        }, INTERVAL);

        window.sfkLiveData = { check };
    })();
</script>
