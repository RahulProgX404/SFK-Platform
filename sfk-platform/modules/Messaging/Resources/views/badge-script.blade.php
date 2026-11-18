{{--
    The unread badge of メッセージ on every page (part of theme.js through ThemeAssets::extend — kept small). It reads the
    messenger's channel of Core's pulse ('m', the inbox version; core::filament.notification-pulse-script announces each
    answer as `sfk:pulse`): when it changed, it asks GET /messaging/api/unread (<meta name="sfk-messaging">) once and
    draws the count on the sidebar's メッセージ and a dot on the floating button; outside the messenger a new message
    shows a toast (who wrote — the text only with 通知に本文を表示), plays the sound and, when allowed, a browser
    notification. The messenger page draws its own. With WebSockets the inbox version is pushed on the user's channel
    (`inbox`), so the badge changes the moment a message arrives. Survives SPA page swaps; statements end with ";" (Blade).
--}}
<style>
    .sfk-msg-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        margin-inline-start: auto;
        border-radius: 9999px;
        background: var(--sfk-danger);
        color: #ffffff;
        font-size: var(--sfk-fs-badge);
        font-weight: var(--sfk-fw-semibold);
        font-variant-numeric: tabular-nums;
        line-height: 1;
    }

    .sfk-has-new-message .fi-layout-sidebar-toggle-btn-ctn .fi-icon-btn {
        position: relative;
        overflow: visible;
    }

    .sfk-has-new-message .fi-layout-sidebar-toggle-btn-ctn .fi-icon-btn::before {
        content: "";
        position: absolute;
        top: 1px;
        left: 1px;
        width: 12px;
        height: 12px;
        border-radius: 9999px;
        background: var(--sfk-success);
        box-shadow: 0 0 0 2px #ffffff;
    }
</style>
<script>
    (() => {
        if (window.sfkMessagingBadge) {
            return;
        }

        const KEY = 'sfk-messaging-badge';
        const state = { version: null, count: 0, busy: false, toasted: 0 };

        const meta = () => document.querySelector('meta[name="sfk-messaging"]');
        const onMessenger = () => document.querySelector('.sfk-messenger') !== null;

        const draw = () => {
            const page = meta()?.dataset.page;
            if (!page) {
                return;
            }
            const path = new URL(page, location.href).pathname;
            document.querySelectorAll('.fi-sidebar-item a, .fi-sidebar-item-btn, a[data-sfk-messages-link]').forEach((link) => {
                if (!(link instanceof HTMLAnchorElement) || new URL(link.href, location.href).pathname !== path) {
                    return;
                }
                /* the top bar's icon button: its own Filament badge, as the bell's */
                const own = link.querySelector('.fi-icon-btn-badge-ctn .fi-badge');
                if (own) {
                    own.textContent = state.count > 99 ? '99+' : String(state.count);
                    link.classList.toggle('sfk-has-unread', state.count > 0);
                    return;
                }
                let badge = link.querySelector('.sfk-msg-badge');
                if (state.count > 0) {
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'sfk-msg-badge';
                        link.appendChild(badge);
                    }
                    badge.textContent = state.count > 99 ? '99+' : String(state.count);
                } else {
                    badge?.remove();
                }
            });
            document.documentElement.classList.toggle('sfk-has-new-message', state.count > 0 && !onMessenger());
        };

        const toast = (newest, prefs) => {
            if (!newest || newest.muted || newest.message <= state.toasted || onMessenger() || (prefs && prefs.dnd_until)) {
                return;
            }
            state.toasted = newest.message;
            const page = meta()?.dataset.page;
            const url = page ? `${page}?c=${newest.conversation}&m=${newest.message}` : null;
            const title = newest.group ? `${newest.name}（${newest.group}）` : `${newest.name}さんからメッセージ`;
            const body = newest.text || '新しいメッセージがあります';
            if (typeof window.FilamentNotification === 'function') {
                const notice = new window.FilamentNotification().title(title).body(body).icon('heroicon-o-chat-bubble-left-right').iconColor('primary').duration(8000);
                if (url && typeof window.FilamentNotificationAction === 'function') {
                    notice.actions([new window.FilamentNotificationAction('open').label('開く').url(url).button()]);
                }
                notice.send();
            }
            if (prefs?.sound) {
                window.sfkMessagingSound?.();
            }
            if (prefs?.desktop && document.visibilityState !== 'visible' && 'Notification' in window && Notification.permission === 'granted') {
                try {
                    const n = new Notification(title, { body, tag: 'sfk-message-' + newest.conversation });
                    n.onclick = () => { window.focus(); if (url) { location.href = url; } };
                } catch (error) { /* not supported */ }
            }
        };

        const refresh = async (version) => {
            const url = meta()?.getAttribute('content');
            if (!url || state.busy) {
                return;
            }
            state.busy = true;
            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (response.ok) {
                    const data = await response.json();
                    const first = state.version === null;
                    state.version = version;
                    state.count = (data.count || 0) + (data.announcements || 0);
                    try { sessionStorage.setItem(KEY, JSON.stringify({ v: version, n: state.count, t: data.newest?.message || 0 })); } catch (error) { /* private mode */ }
                    if (first) {
                        state.toasted = data.newest?.message || 0;
                    } else {
                        toast(data.newest, data.prefs);
                    }
                    draw();
                }
            } catch (error) {
                /* offline: the next pulse tries again */
            } finally {
                state.busy = false;
            }
        };

        /* a short two-note chime (Web Audio — no sound file) */
        window.sfkMessagingSound = () => {
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) { return; }
                const ctx = window.sfkMessagingAudio || (window.sfkMessagingAudio = new Ctx());
                [[880, 0], [1318.5, 0.11]].forEach(([freq, at]) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.value = freq;
                    gain.gain.setValueAtTime(0.0001, ctx.currentTime + at);
                    gain.gain.exponentialRampToValueAtTime(0.12, ctx.currentTime + at + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + at + 0.25);
                    osc.connect(gain).connect(ctx.destination);
                    osc.start(ctx.currentTime + at);
                    osc.stop(ctx.currentTime + at + 0.3);
                });
            } catch (error) { /* audio blocked until the user interacted */ }
        };

        try {
            const saved = JSON.parse(sessionStorage.getItem(KEY) || 'null');
            if (saved && typeof saved.v === 'string') {
                state.version = saved.v;
                state.count = saved.n || 0;
                state.toasted = saved.t || 0;
            }
        } catch (error) { /* private mode */ }

        window.addEventListener('sfk:pulse', (event) => {
            const version = event.detail?.x?.m;
            if (typeof version === 'string' && version !== state.version) {
                refresh(version);
            }
        });
        /* the messenger tells the badge when it read something (its own pulse is faster) */
        window.addEventListener('sfk:messaging-unread', (event) => {
            state.count = Math.max(0, Number(event.detail?.count) || 0);
            draw();
        });
        document.addEventListener('livewire:navigated', draw);

        /* WebSockets: the inbox version pushed on the user's own channel; after a reconnect, read it once */
        const listen = () => {
            const realtime = window.sfkRealtime;
            if (!realtime?.enabled || !realtime.user || !meta()) {
                return;
            }
            realtime.subscribe('sfk.user.' + realtime.user, 'inbox', (data) => {
                if (typeof data?.v === 'string' && data.v !== state.version) {
                    refresh(data.v);
                }
            });
            realtime.onState((connected) => { if (connected && state.version !== null) { refresh(state.version); } });
        };

        window.sfkMessagingBadge = { draw, refresh };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => { draw(); listen(); }, { once: true });
        } else {
            draw();
            listen();
        }
    })();
</script>
