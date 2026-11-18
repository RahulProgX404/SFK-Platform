{{--
    WebSockets for every page (Modules\Core\Application\Realtime, docs/realtime.md): one connection to Laravel Reverb
    (<meta name="sfk-realtime">: app key, address, user, channel-auth URL), opened on the first subscribe and kept across
    the in-place page swaps. A small client of the Pusher protocol Reverb speaks — no outside library: private channels
    are authorized by the app (POST broadcasting/auth with the CSRF token), pings are answered, a lost connection comes
    back with a growing pause and subscribes its channels again. Pages ask `window.sfkRealtime`:
      subscribe(channel, event, handler) → unsubscribe()   channel without "private-", e.g. "sfk.user.5"
      onState(handler(connected)) · connected · user
    Without the meta (real time off) nothing connects and `connected` stays false: the pages keep polling.
    Part of theme.js (ThemeAssets); statements end with ";" (Blade).
--}}
<script>
    (() => {
        if (window.sfkRealtime) {
            return;
        }

        const channels = new Map();
        const stateHandlers = new Set();
        const state = { socket: null, socketId: null, connected: false, retry: 0, timer: null, idle: null, config: undefined, activity: 30 };

        const readConfig = () => {
            if (state.config !== undefined) {
                return state.config;
            }
            try {
                const meta = document.querySelector('meta[name="sfk-realtime"]');
                state.config = meta ? JSON.parse(meta.getAttribute('content') || 'null') : null;
            } catch (error) {
                state.config = null;
            }
            return state.config;
        };

        const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const setConnected = (value) => {
            if (state.connected !== value) {
                state.connected = value;
                stateHandlers.forEach((handler) => { try { handler(value); } catch (error) { /* a page's handler */ } });
            }
        };

        const send = (payload) => {
            if (state.socket && state.socket.readyState === WebSocket.OPEN) {
                state.socket.send(JSON.stringify(payload));
            }
        };

        const keepAlive = () => {
            clearTimeout(state.idle);
            state.idle = setTimeout(() => send({ event: 'pusher:ping', data: {} }), state.activity * 1000);
        };

        const authorize = async (channel) => {
            const config = readConfig();
            const body = new URLSearchParams({ socket_id: state.socketId, channel_name: 'private-' + channel });
            const response = await fetch(config.auth, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                body,
            });
            if (!response.ok) {
                throw new Error('channel auth ' + response.status);
            }
            return (await response.json()).auth;
        };

        const join = async (channel) => {
            const entry = channels.get(channel);
            if (!entry || !state.socketId || entry.joined) {
                return;
            }
            entry.joined = true;
            try {
                const auth = await authorize(channel);
                send({ event: 'pusher:subscribe', data: { channel: 'private-' + channel, auth } });
            } catch (error) {
                /* not allowed (left the group, signed out) or offline: no events; a reconnect asks again */
                entry.joined = false;
            }
        };

        const connect = () => {
            const config = readConfig();
            if (!config || !config.key || state.socket || typeof WebSocket !== 'function') {
                return;
            }
            const secure = config.scheme ? config.scheme === 'https' : location.protocol === 'https:';
            const host = config.host || location.hostname;
            const port = config.port || (config.host ? (secure ? 443 : 80) : location.port);
            const url = `${secure ? 'wss' : 'ws'}://${host}${port ? ':' + port : ''}/app/${encodeURIComponent(config.key)}?protocol=7&client=sfk&version=1.0&flash=false`;

            let socket;
            try {
                socket = new WebSocket(url);
            } catch (error) {
                schedule();
                return;
            }
            state.socket = socket;

            socket.onmessage = (message) => {
                keepAlive();
                let packet;
                try { packet = JSON.parse(message.data); } catch (error) { return; }
                const event = packet.event;
                let data = packet.data;
                if (typeof data === 'string') {
                    try { data = JSON.parse(data); } catch (error) { /* plain text */ }
                }
                if (event === 'pusher:connection_established') {
                    state.socketId = data.socket_id;
                    state.activity = Math.max(10, Math.min(120, Number(data.activity_timeout) || 30));
                    state.retry = 0;
                    setConnected(true);
                    channels.forEach((entry, channel) => { entry.joined = false; join(channel); });
                    return;
                }
                if (event === 'pusher:ping') {
                    send({ event: 'pusher:pong', data: {} });
                    return;
                }
                if (!packet.channel || event.startsWith('pusher')) {
                    return;
                }
                const entry = channels.get(packet.channel.replace(/^private-/, ''));
                entry?.handlers.get(event)?.forEach((handler) => { try { handler(data); } catch (error) { /* a page's handler */ } });
            };

            socket.onclose = () => {
                clearTimeout(state.idle);
                state.socket = null;
                state.socketId = null;
                channels.forEach((entry) => { entry.joined = false; });
                setConnected(false);
                if (channels.size > 0) {
                    schedule();
                }
            };
            socket.onerror = () => { /* onclose follows */ };
        };

        /* 1 s, 2 s, 4 s … at most 30 s between attempts; at once when the tab or the network comes back */
        const schedule = () => {
            clearTimeout(state.timer);
            const wait = Math.min(30000, 1000 * Math.pow(2, state.retry++));
            state.timer = setTimeout(connect, wait);
        };

        const subscribe = (channel, event, handler) => {
            if (!readConfig()) {
                return () => {};
            }
            let entry = channels.get(channel);
            if (!entry) {
                entry = { handlers: new Map(), joined: false };
                channels.set(channel, entry);
            }
            if (!entry.handlers.has(event)) {
                entry.handlers.set(event, new Set());
            }
            entry.handlers.get(event).add(handler);
            if (state.socket) {
                join(channel);
            } else {
                connect();
            }

            return () => {
                entry.handlers.get(event)?.delete(handler);
                const empty = [...entry.handlers.values()].every((set) => set.size === 0);
                if (empty && channels.get(channel) === entry) {
                    channels.delete(channel);
                    send({ event: 'pusher:unsubscribe', data: { channel: 'private-' + channel } });
                }
            };
        };

        const wake = () => {
            if (!state.socket && channels.size > 0) {
                state.retry = 0;
                clearTimeout(state.timer);
                connect();
            }
        };
        window.addEventListener('online', wake);
        document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') { wake(); } });

        window.sfkRealtime = {
            subscribe,
            onState(handler) { stateHandlers.add(handler); return () => stateHandlers.delete(handler); },
            get connected() { return state.connected; },
            get user() { return readConfig()?.user ?? null; },
            get enabled() { return !!readConfig(); },
        };
    })();
</script>
