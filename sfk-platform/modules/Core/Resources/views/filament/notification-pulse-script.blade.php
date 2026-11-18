{{--
    通知 in real time (Modules\Core\Application\Notifications\NotificationPulse): while the page is in view it asks the
    user's pulse (<meta name="sfk-notification-pulse">) every few seconds, and at once when the tab comes back. When the
    pulse changed — a new 通知 — the bell is refreshed (Filament's databaseNotificationsSent; it may be closed in the
    sidebar, which then loads it fresh when opened), the newest 通知 is shown as a toast with レコードを開く, and the
    floating button of the closed sidebar gets a dot until the sidebar is opened. With WebSockets (core::filament.
    realtime-script) the 通知 is pushed on the user's channel the moment it is stored, and the pulse is asked only every
    minute (and once after a reconnect) as a safety net. Part of theme.js (ThemeAssets): it
    runs once and keeps working across the in-place page swaps (wire:navigate). Statements end with ";" (Blade).
--}}
<script>
    (() => {
        if (window.sfkNotificationPulse) {
            return;
        }

        const INTERVAL = 4000;
        /* with a live WebSocket the pushes carry the news: polling is only a safety net */
        const PUSHED_INTERVAL = 60000;
        const state = { url: null, version: null, timer: null, busy: false, last: 0, active: Date.now() };
        /* 離席中 (社内メッセージ): after five minutes without a key, a click or the mouse on this page the pulse says "idle" */
        const IDLE_AFTER = 5 * 60 * 1000;
        const markActive = () => { state.active = Date.now(); };
        ['pointerdown', 'keydown', 'wheel', 'touchstart'].forEach((type) => window.addEventListener(type, markActive, { passive: true, capture: true }));
        let moved = 0;
        window.addEventListener('pointermove', () => { const now = Date.now(); if (now - moved > 15000) { moved = now; markActive(); } }, { passive: true });

        const escape = (text) => String(text ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

        /* the pulse of the page's user; a page of another user (sign-out / sign-in) starts afresh */
        const readMeta = () => {
            const meta = document.querySelector('meta[name="sfk-notification-pulse"]');
            const url = meta?.getAttribute('content') || null;
            if (url !== state.url) {
                state.url = url;
                state.version = meta?.dataset.version ?? '';
            }
            return url;
        };

        const toast = (pulse) => {
            if (typeof window.FilamentNotification !== 'function') {
                return;
            }
            /* a page already showing what the 通知 links to (e.g. the open conversation in 社内メッセージ) keeps quiet */
            if (pulse.u && typeof window.sfkNoticeShown === 'function' && window.sfkNoticeShown(pulse.u)) {
                return;
            }
            const notice = new window.FilamentNotification()
                .title(escape(pulse.t || '新しい通知'))
                .duration(10000);
            if (pulse.b) {
                notice.body(escape(pulse.b));
            }
            if (pulse.i) {
                notice.icon(pulse.i);
                if (pulse.c) {
                    notice.iconColor(pulse.c);
                }
            }
            if (pulse.u && typeof window.FilamentNotificationAction === 'function') {
                notice.actions([new window.FilamentNotificationAction('open').label(escape(pulse.l || 'レコードを開く')).url(pulse.u).button()]);
            }
            notice.send();
        };

        const announce = (pulse) => {
            window.Livewire?.dispatch('databaseNotificationsSent');
            document.documentElement.classList.add('sfk-has-new-notice');
            toast(pulse);
        };

        /* a pulse from the poll or a push: announce it when it is new */
        const apply = (pulse) => {
            if (pulse && typeof pulse.v === 'string' && pulse.v !== state.version) {
                const isNew = pulse.v !== '' && state.version !== null;
                state.version = pulse.v;
                if (isNew) {
                    announce(pulse);
                }
            }
        };

        const check = async (force = false) => {
            const url = readMeta();
            const pushed = window.sfkRealtime?.connected === true;
            if (!url || state.busy || document.visibilityState !== 'visible' || (pushed && force !== true && Date.now() - state.last < PUSHED_INTERVAL)) {
                return;
            }
            state.busy = true;
            state.last = Date.now();
            try {
                const idle = Date.now() - state.active > IDLE_AFTER ? (url.includes('?') ? '&' : '?') + 'idle=1' : '';
                const response = await fetch(url + idle, { cache: 'no-store', credentials: 'omit', headers: { Accept: 'application/json' } });
                if (response.ok) {
                    const pulse = await response.json();
                    /* other modules read their channels (PulseChannels, pulse.x) */
                    window.dispatchEvent(new CustomEvent('sfk:pulse', { detail: pulse }));
                    if (url === state.url) {
                        apply(pulse);
                    }
                }
            } catch (error) {
                /* offline or the server restarting: the next turn tries again */
            } finally {
                state.busy = false;
            }
        };

        const start = () => {
            if (state.timer === null) {
                state.timer = window.setInterval(check, INTERVAL);
            }
            check(true);
            const realtime = window.sfkRealtime;
            if (realtime?.enabled && realtime.user) {
                realtime.subscribe('sfk.user.' + realtime.user, 'notification', (pulse) => { readMeta(); apply(pulse); });
                /* back after a lost connection: what was pushed meanwhile is caught up from the pulse */
                realtime.onState((connected) => { if (connected) { check(true); } });
            }
        };

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                check();
            }
        });
        window.addEventListener('focus', () => check());
        document.addEventListener('livewire:navigated', () => check());

        /* opening the sidebar (the floating button) shows the bell: the dot has done its work */
        document.addEventListener('click', (event) => {
            if (event.target instanceof Element && event.target.closest('.fi-layout-sidebar-toggle-btn-ctn, .fi-no-database')) {
                document.documentElement.classList.remove('sfk-has-new-notice');
            }
        }, true);

        window.sfkNotificationPulse = { check };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', start, { once: true });
        } else {
            start();
        }
    })();
</script>
