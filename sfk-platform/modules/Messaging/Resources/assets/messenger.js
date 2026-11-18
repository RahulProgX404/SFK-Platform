/*
 * 社内メッセージ — the messenger page (Modules\Messaging\Filament\Pages\MessengerPage), an Alpine component talking to the
 * JSON API (/messaging/api, MessengerController). Loaded only on that page (MessengerAssets), before Alpine starts.
 *
 * Real time: with WebSockets (window.sfkRealtime, Laravel Reverb) the changes are pushed — the inbox on the user's
 * channel, new / changed messages, read marks and 入力中 on the open conversation's channel — and the pulse is asked
 * only every 15 s (presence, safety net). Without them the page asks its pulse (Signals::pulse — a cache read) every
 * ~1.5 s while in view and ~8 s in the background. Either way a change fetches only what changed (new / changed
 * messages, the read marks, the inbox). Sent messages show at once (optimistic, with their own id against double sending) and take
 * their real state when the server answers. Every text is escaped before it is drawn; links are only http(s).
 */
(() => {
    'use strict';

    if (window.sfkMessengerLoaded) {
        return;
    }
    window.sfkMessengerLoaded = true;

    const QUICK_REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];
    const EMOJI = {
        'よく使う': [],
        '顔': ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '😉', '😊', '😇', '🥰', '😍', '🤩', '😘', '😋', '😛', '😜', '🤪', '🤗', '🤭', '🤔', '🤐', '😐', '😑', '😶', '😏', '😒', '🙄', '😬', '😌', '😔', '😪', '😴', '😷', '🤒', '🤧', '🥵', '🥶', '😵', '🤯', '🥳', '😎', '🤓', '🧐', '😕', '😟', '🙁', '😮', '😯', '😲', '😳', '🥺', '😦', '😧', '😨', '😰', '😥', '😢', '😭', '😱', '😖', '😣', '😞', '😓', '😩', '😫', '🥱', '😤', '😡', '😠'],
        '手・人': ['👍', '👎', '👌', '✌️', '🤞', '🤟', '🤘', '🤙', '👈', '👉', '👆', '👇', '☝️', '✋', '🤚', '🖐️', '🖖', '👋', '👏', '🙌', '👐', '🤲', '🙏', '🤝', '💪', '✍️', '🙇', '🙋', '🙆', '🙅', '🤷', '🤦', '💁', '🏃', '🚶', '👨‍💼', '👩‍💼', '👷', '🧑‍🔧', '🧑‍🏭', '🧑‍💻'],
        '記号': ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '💯', '💢', '💥', '💫', '💦', '💨', '✨', '⭐', '🌟', '🔥', '🎉', '🎊', '✅', '☑️', '✔️', '❌', '⭕', '❗', '❓', '‼️', '⁉️', '⚠️', '🚨', '🆗', '🆕', '🆙', '🔴', '🟢', '🔵', '⬆️', '⬇️', '➡️', '⬅️', '🔁', '⏰', '⏳', '📌', '📍'],
        '仕事': ['📅', '📆', '🗓️', '📝', '📋', '📁', '📂', '📄', '📃', '📑', '📊', '📈', '📉', '🗂️', '📦', '📮', '📧', '✉️', '📞', '☎️', '📱', '💻', '🖥️', '🖨️', '⌨️', '🖱️', '💡', '🔍', '🔧', '🔨', '⚙️', '🛠️', '🧰', '🏭', '🏢', '🚚', '🚛', '🚗', '🚲', '✈️', '🚃', '💴', '💰', '🧾', '🔑', '🔒'],
        '食べ物・自然': ['☕', '🍵', '🍺', '🍻', '🍶', '🍱', '🍙', '🍣', '🍜', '🍛', '🍞', '🍰', '🍩', '🍪', '🍎', '🍊', '🍓', '🍉', '🌸', '🌻', '🌹', '🍀', '🌳', '🍁', '☀️', '🌤️', '☁️', '🌧️', '⛈️', '❄️', '☃️', '🌈', '🌙', '🐶', '🐱', '🐻', '🐼', '🐸', '🐧', '🐦'],
    };

    const pad = (n) => String(n).padStart(2, '0');
    const WEEK = ['日', '月', '火', '水', '木', '金', '土'];
    const dayKey = (d) => `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;
    const escapeHtml = (text) => String(text ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    }));
    const isTouch = () => window.matchMedia('(pointer: coarse)').matches;
    const storage = {
        get(key, fallback) {
            try { const v = localStorage.getItem(key); return v === null ? fallback : JSON.parse(v); } catch (e) { return fallback; }
        },
        set(key, value) {
            try { localStorage.setItem(key, JSON.stringify(value)); } catch (e) { /* private mode */ }
        },
    };

    const formatSize = (bytes) => {
        if (bytes >= 1048576) { return (bytes / 1048576).toFixed(1) + ' MB'; }
        if (bytes >= 1024) { return Math.round(bytes / 1024) + ' KB'; }
        return bytes + ' B';
    };
    const formatDuration = (ms) => {
        const s = Math.max(0, Math.round((ms || 0) / 1000));
        return `${Math.floor(s / 60)}:${pad(s % 60)}`;
    };

    const fileIcon = (name) => {
        const ext = String(name || '').split('.').pop().toLowerCase();
        if (ext === 'pdf') { return { label: 'PDF', cls: 'is-pdf' }; }
        if (ext === 'docx' || ext === 'doc') { return { label: 'W', cls: 'is-word' }; }
        if (ext === 'xlsx' || ext === 'xls' || ext === 'csv') { return { label: ext === 'csv' ? 'CSV' : 'X', cls: 'is-excel' }; }
        if (ext === 'txt') { return { label: 'TXT', cls: 'is-text' }; }
        return { label: ext.toUpperCase().slice(0, 4) || 'FILE', cls: 'is-file' };
    };

    function messenger(boot) {
        return {
            me: boot.me,
            people: Object.fromEntries((boot.people || []).map((p) => [p.id, p])),
            conversations: boot.conversations || [],
            announcementsUnread: boot.announcementsUnread || 0,
            prefs: boot.prefs,
            stickers: boot.stickers || [],
            categories: boot.categories || [],
            policy: boot.policy,
            urls: boot.urls,
            basePulse: boot.pulse,

            // list
            filter: 'all',
            statusMenu: false,
            connection: { enabled: !!window.sfkRealtime?.enabled, connected: !!window.sfkRealtime?.connected, online: navigator.onLine },
            activeAt: Date.now(),
            expanded: false,
            priority: 0,
            records: { apps: null, app: '', q: '', results: [], loading: false },
            channelList: null,
            info: null,
            links: { items: [], more: false, loaded: false },
            dragId: null,
            query: '',
            // chat
            activeId: null,
            conv: null,
            messages: [],
            more: false,
            newer: false,
            reads: [],
            loadingChat: false,
            loadingOlder: false,
            unreadMarker: null,
            atBottom: true,
            newBelow: 0,
            flashId: null,
            typingIds: [],
            presence: null,
            syncTime: null,
            // composer
            text: '',
            drafts: {},
            replyTo: null,
            editing: null,
            uploads: [],
            mentionIds: [],
            mentionAll: false,
            suggest: { open: false, items: [], index: 0, start: 0 },
            picker: null,
            emojiTab: '顔',
            recentEmoji: storage.get('sfk-messenger-emoji', []),
            recording: null,
            voice: null,
            sending: false,
            // panels and dialogs
            view: 'list',
            details: null,
            detailsOpen: window.innerWidth >= 1400,
            detailsTab: 'info',
            menu: null,
            dialog: null,
            form: {},
            pick: { query: '', selected: [] },
            viewer: null,
            readers: null,
            search: { q: '', user: '', from: '', to: '', files: false, kind: '', conversation: null, results: [], more: false, loading: false, done: false },
            annQuery: '',
            announcements: [],
            announcement: null,
            toastText: '',
            dragging: false,
            // real time
            pulse: { url: null, timer: null, i: null, c: null, r: null, busy: false, lastTyping: 0 },
            live: { conversation: null, off: null, typing: {} },
            notified: {},

            init() {
                this.pulse.url = this.basePulse;
                this.rememberUnread();
                /* no 通知 toast for the conversation the user is looking at (Core notification-pulse-script) */
                this._noticeShown = (url) => {
                    try {
                        const c = Number(new URL(url, location.href).searchParams.get('c'));
                        return this.$root.isConnected && document.visibilityState === 'visible' && c > 0 && c === this.activeId;
                    } catch (e) { return false; }
                };
                window.sfkNoticeShown = this._noticeShown;
                document.querySelector('.fi-layout-sidebar-toggle-btn-ctn')?.classList.add('sfk-m-hide-toggle');
                window.addEventListener('popstate', () => this.onPopState());
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') {
                        this.tick(true);
                        this.markReadSoon();
                    }
                });
                window.addEventListener('focus', () => this.tick(true));
                this.fitViewport();
                window.visualViewport?.addEventListener('resize', () => this.fitViewport());
                window.addEventListener('resize', () => this.fitViewport());

                const open = boot.open || {};
                if (open.a) {
                    this.filter = 'announcements';
                    this.loadAnnouncements().then(() => this.openAnnouncement(open.a));
                } else if (open.c) {
                    this.openConversation(open.c, open.m || null);
                } else if (!isTouch() && window.innerWidth >= 768) {
                    const first = this.visibleConversations[0];
                    if (first) { this.openConversation(first.id); }
                }
                this.schedule(400);
                this.badge();
                this.listen();
                window.sfkRealtime?.onState((connected) => { this.connection.connected = connected; });
                window.addEventListener('online', () => { this.connection.online = true; this.tick(true); });
                window.addEventListener('offline', () => { this.connection.online = false; });
                const active = () => { this.activeAt = Date.now(); };
                ['pointerdown', 'keydown', 'wheel', 'touchstart'].forEach((type) => window.addEventListener(type, active, { passive: true, capture: true }));
            },

            /* groups and channels: names, members, 管理者, rules */
            get shared() { return !!this.conv && this.conv.type !== 'direct'; },
            get isChannel() { return this.conv?.type === 'channel'; },
            get myName() { return this.me.name; },
            get statusLabel() {
                if (this.prefs.status === 'dnd') { return '取り込み中'; }
                if (this.prefs.status === 'away') { return '離席中'; }
                return this.prefs.presence ? 'オンライン' : 'オンライン状態を表示しない';
            },
            get statusTone() { return this.prefs.status === 'dnd' ? 'is-dnd' : this.prefs.status === 'away' ? 'is-away' : 'is-online'; },

            async setStatus(status) {
                this.statusMenu = false;
                await this.savePrefs({ status });
            },

            presenceText(p) {
                if (!p) { return ''; }
                if (p.state === 'online') { return 'オンライン'; }
                if (p.state === 'away') { return '離席中'; }
                if (p.state === 'dnd') { return '取り込み中'; }
                if (!p.seen) { return ''; }
                const minutes = Math.floor((Date.now() / 1000 - p.seen) / 60);
                if (minutes < 60) { return `最終アクセス ${Math.max(1, minutes)}分前`; }
                if (minutes < 1440) { return `最終アクセス ${Math.floor(minutes / 60)}時間前`; }
                return `最終アクセス ${Math.floor(minutes / 1440)}日前`;
            },

            get connectionLine() {
                if (!this.connection.online) { return 'オフラインです。接続が戻ると自動で更新します。'; }
                if (this.connection.enabled && !this.connection.connected) { return 'リアルタイム接続を再開しています…'; }
                return '';
            },

            /* WebSockets: the user's inbox; the open conversation subscribes in openConversation() */
            listen() {
                const realtime = window.sfkRealtime;
                if (!realtime?.enabled || !realtime.user) {
                    return;
                }
                realtime.subscribe('sfk.user.' + realtime.user, 'inbox', (data) => {
                    if (data?.v && data.v !== this.pulse.i) {
                        this.pulse.i = data.v;
                        clearTimeout(this._inboxTimer);
                        this._inboxTimer = setTimeout(() => this.refreshInbox(), 150);
                    }
                });
                /* back after a lost connection: catch up on what was pushed meanwhile */
                realtime.onState((connected) => {
                    if (connected) {
                        this.refreshInbox();
                        if (this.activeId) { this.sync(); this.loadReads(); }
                    }
                });
            },

            liveConversation(id) {
                const realtime = window.sfkRealtime;
                if (this.live.conversation === id || !realtime?.enabled) {
                    return;
                }
                this.live.off?.();
                this.live.conversation = id;
                this.live.typing = {};
                this.live.off = null;
                if (!id) {
                    return;
                }
                const channel = 'sfk.conversation.' + id;
                const offs = [
                    realtime.subscribe(channel, 'changed', (data) => {
                        if (this.activeId === id && data?.v !== this.pulse.c) { this.pulse.c = data?.v ?? null; this.sync(); }
                    }),
                    realtime.subscribe(channel, 'read', (data) => {
                        if (this.activeId === id && data?.v !== this.pulse.r) { this.pulse.r = data?.v ?? null; this.loadReads(); }
                    }),
                    realtime.subscribe(channel, 'typing', (data) => {
                        const user = Number(data?.user);
                        if (this.activeId !== id || !user || user === this.me.id) { return; }
                        clearTimeout(this.live.typing[user]);
                        if (data.typing) {
                            this.live.typing[user] = setTimeout(() => { delete this.live.typing[user]; this.typingIds = this.typingIds.filter((u) => u !== user); }, 6000);
                            if (!this.typingIds.includes(user)) { this.typingIds = [...this.typingIds, user]; }
                        } else {
                            delete this.live.typing[user];
                            this.typingIds = this.typingIds.filter((u) => u !== user);
                        }
                    }),
                ];
                this.live.off = () => offs.forEach((off) => off());
            },

            destroy() {
                if (window.sfkNoticeShown === this._noticeShown) { window.sfkNoticeShown = null; }
                this.liveConversation(null);
                clearTimeout(this.pulse.timer);
                this.pulse.timer = null;
                this.stopRecording(true);
            },

            fitViewport() {
                const h = window.visualViewport ? window.visualViewport.height : window.innerHeight;
                this.$root.style.setProperty('--sfk-m-vh', h + 'px');
            },

            // ---- helpers --------------------------------------------------------------------------------------------
            person(id) { return this.people[id] || { id, name: id ? '退職者' : '', kana: '', position: '', categories: [], active: false }; },
            nameOf(id) { return this.person(id).name; },
            initial(name) { const n = String(name || '').replace(/^[^\p{L}\p{N}]+/u, ''); return (n || String(name || '?')).slice(0, 1); },
            hue(id) { return ((Number(id) || 0) * 47) % 360; },
            /* an object, never a string: a string :style would wipe the display x-show sets */
            avatarStyle(id) { return { background: `hsl(${this.hue(id)} 45% 42%)` }; },
            csrf() { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''; },

            async api(method, path, body = null, opts = {}) {
                const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.csrf() };
                let payload = body;
                if (body !== null && !(body instanceof FormData)) {
                    headers['Content-Type'] = 'application/json';
                    payload = JSON.stringify(body);
                }
                const response = await fetch(this.urls.api + path, { method, headers, body: method === 'GET' ? undefined : payload, credentials: 'same-origin', signal: opts.signal });
                let data = null;
                try { data = await response.json(); } catch (e) { data = null; }
                if (!response.ok) {
                    const message = response.status === 419 ? 'ページの有効期限が切れました。再読み込みしてください。'
                        : response.status === 404 ? '見つかりません。'
                        : (data?.message || (data?.errors ? Object.values(data.errors).flat()[0] : null) || '処理できませんでした。');
                    const error = new Error(message);
                    error.status = response.status;
                    throw error;
                }
                return data;
            },

            toast(text, type = 'danger') {
                if (typeof window.FilamentNotification === 'function') {
                    new window.FilamentNotification().title(escapeHtml(text)).status(type).duration(4000).send();
                } else {
                    this.toastText = text;
                    setTimeout(() => { this.toastText = ''; }, 4000);
                }
            },

            // ---- inbox ----------------------------------------------------------------------------------------------
            get counts() {
                const c = { all: 0, unread: 0, groups: 0, direct: 0, channels: 0, favorites: 0, muted: 0, archived: 0 };
                for (const item of this.conversations) {
                    if (item.archived) { c.archived += item.unread > 0 ? 1 : 0; continue; }
                    if (item.unread > 0) { c.unread++; c.all += item.muted ? 0 : 1; }
                    if (item.muted && item.unread > 0) { c.muted++; }
                    if (item.type === 'group' && item.unread > 0) { c.groups++; }
                    if (item.type === 'channel' && item.unread > 0) { c.channels++; }
                    if (item.type === 'direct' && item.unread > 0) { c.direct++; }
                    if (item.favorite && item.unread > 0) { c.favorites++; }
                }
                return c;
            },

            get visibleConversations() {
                const q = this.query.trim().toLowerCase();
                const list = this.conversations.filter((item) => {
                    if (this.filter === 'archived') { if (!item.archived) { return false; } } else if (item.archived) { return false; }
                    if (this.filter === 'unread' && !(item.unread > 0)) { return false; }
                    if (this.filter === 'groups' && item.type !== 'group') { return false; }
                    if (this.filter === 'direct' && item.type !== 'direct') { return false; }
                    if (this.filter === 'favorites' && !item.favorite) { return false; }
                    if (this.filter === 'channels' && item.type !== 'channel') { return false; }
                    if (this.filter === 'muted' && !item.muted) { return false; }
                    if (q) {
                        const p = item.peer ? this.person(item.peer) : null;
                        const hay = [item.name, p?.kana, p?.position, ...(p?.categories || []), item.last?.text].join(' ').toLowerCase();
                        if (!hay.includes(q)) { return false; }
                    }
                    return true;
                });
                // pinned first, in the user's own order (drag), then the newest
                return list.sort((a, b) => (Number(b.pinned) - Number(a.pinned))
                    || (a.pinned && b.pinned ? ((a.pin_order ?? 0) - (b.pin_order ?? 0)) : 0)
                    || (b.at > a.at ? 1 : b.at < a.at ? -1 : 0));
            },

            get activeItem() { return this.conversations.find((c) => c.id === this.activeId) || null; },

            lastLine(item) {
                if (!item.last) { return item.type !== 'direct' ? `メンバー ${item.members}人` : ''; }
                const who = item.last.user && item.last.user !== this.me.id && item.type !== 'direct' ? this.nameOf(item.last.user) + ': ' : (item.last.user === this.me.id ? 'あなた: ' : '');
                return item.last.type === 'system' ? item.last.text : who + item.last.text;
            },

            listTime(iso) {
                if (!iso) { return ''; }
                const d = new Date(iso);
                const now = new Date();
                if (dayKey(d) === dayKey(now)) { return `${pad(d.getHours())}:${pad(d.getMinutes())}`; }
                const y = new Date(now); y.setDate(now.getDate() - 1);
                if (dayKey(d) === dayKey(y)) { return '昨日'; }
                if (now - d < 6 * 86400000) { return WEEK[d.getDay()] + '曜日'; }
                return d.getFullYear() === now.getFullYear() ? `${d.getMonth() + 1}/${d.getDate()}` : `${d.getFullYear()}/${d.getMonth() + 1}/${d.getDate()}`;
            },

            async refreshInbox() {
                try {
                    const data = await this.api('GET', '/inbox');
                    const before = Object.fromEntries(this.conversations.map((c) => [c.id, c]));
                    this.conversations = data.conversations;
                    this.announcementsUnread = data.announcements ?? this.announcementsUnread;
                    this.notifyNew(before);
                    this.badge();
                    if (this.activeId && !this.conversations.some((c) => c.id === this.activeId)) {
                        // removed from the group / left on another device
                        this.closeChat();
                    }
                } catch (e) { /* the next pulse tries again */ }
            },

            rememberUnread() {
                for (const c of this.conversations) { this.notified[c.id] = c.last?.id || 0; }
            },

            /* a new message in another conversation (or this one while the tab is hidden): toast, sound, browser notification */
            notifyNew(before) {
                const dnd = this.prefs.dnd_until && new Date(this.prefs.dnd_until) > new Date();
                for (const item of this.conversations) {
                    const last = item.last;
                    if (!last || last.user === this.me.id || item.muted || !(item.unread > 0)) { this.notified[item.id] = Math.max(this.notified[item.id] || 0, last?.id || 0); continue; }
                    if ((this.notified[item.id] || 0) >= last.id) { continue; }
                    this.notified[item.id] = last.id;
                    const here = item.id === this.activeId && document.visibilityState === 'visible';
                    if (here || dnd || last.type === 'system') { continue; }
                    const title = item.type !== 'direct' ? `${this.nameOf(last.user)}（${item.name}）` : `${this.nameOf(last.user)}さん`;
                    const body = this.prefs.preview ? last.text : '新しいメッセージがあります';
                    if (this.prefs.sound) { window.sfkMessagingSound?.(); }
                    if (document.visibilityState === 'visible') {
                        if (typeof window.FilamentNotification === 'function') {
                            new window.FilamentNotification().title(escapeHtml(title)).body(escapeHtml(body)).icon('heroicon-o-chat-bubble-left-right').iconColor('primary').duration(5000).send();
                        }
                    } else if (this.prefs.desktop && 'Notification' in window && Notification.permission === 'granted') {
                        try {
                            const n = new Notification(title, { body, tag: 'sfk-message-' + item.id });
                            n.onclick = () => { window.focus(); this.openConversation(item.id); };
                        } catch (e) { /* not supported */ }
                    }
                }
            },

            badge() {
                const total = this.conversations.reduce((sum, c) => sum + (c.muted ? 0 : (c.unread || 0)), 0) + (this.announcementsUnread || 0);
                window.dispatchEvent(new CustomEvent('sfk:messaging-unread', { detail: { count: total } }));
                document.title = (total > 0 ? `(${total > 99 ? '99+' : total}) ` : '') + document.title.replace(/^\(\d+\+?\) /, '');
            },

            setFilter(filter) {
                this.filter = filter;
                if (filter === 'announcements') { this.loadAnnouncements(); }
            },

            // ---- opening a conversation -------------------------------------------------------------------------------
            async openConversation(id, around = null) {
                if (this.text && this.activeId) { this.drafts[this.activeId] = this.text; }
                this.stopTyping();
                this.announcement = null;
                this.activeId = id;
                this.conv = null;
                this.messages = [];
                this.reads = [];
                this.replyTo = null;
                this.editing = null;
                this.uploads = [];
                this.typingIds = [];
                this.presence = null;
                this.newBelow = 0;
                this.text = this.drafts[id] || '';
                this.mentionIds = [];
                this.mentionAll = false;
                this.loadingChat = true;
                this.details = null;
                this.links = { items: [], more: false, loaded: false };
                this.priority = 0;
                this.pulse.c = null;
                this.pulse.r = null;
                this.liveConversation(id);
                this.showChatScreen();
                const item = this.conversations.find((c) => c.id === id);
                this.unreadMarker = null;
                const firstUnread = item && item.unread > 0 ? item.unread : 0;
                try {
                    const data = await this.api('GET', `/conversations/${id}` + (around ? `?around=${around}` : ''));
                    if (this.activeId !== id) { return; }
                    this.conv = data.conversation;
                    this.messages = data.messages;
                    this.more = data.more;
                    this.newer = data.newer;
                    this.reads = data.reads;
                    this.pulse.url = data.pulse;
                    this.syncTime = data.now;
                    if (firstUnread > 0 && !around) {
                        const others = this.messages.filter((m) => m.user !== this.me.id && m.type !== 'system');
                        this.unreadMarker = others.length >= firstUnread ? others[others.length - firstUnread].id : null;
                    }
                    if (item) { item.unread = 0; item.mention = false; }
                    this.badge();
                    this.$nextTick(() => {
                        if (around) {
                            this.jumpTo(around, false);
                        } else if (this.unreadMarker) {
                            this.$refs.log?.querySelector('[data-unread-marker]')?.scrollIntoView({ block: 'start' });
                            this.atBottom = this.isAtBottom();
                        } else {
                            this.scrollToBottom(false);
                        }
                        this.markReadSoon();
                        if (!isTouch()) { this.$refs.input?.focus(); }
                    });
                    if (this.detailsOpen && window.innerWidth >= 1200) { this.loadDetails(); }
                    this.tick(true);
                } catch (e) {
                    this.toast(e.message);
                    if (e.status === 404) { this.closeChat(); this.refreshInbox(); }
                } finally {
                    if (this.activeId === id) { this.loadingChat = false; }
                }
            },

            showChatScreen() {
                if (window.innerWidth < 768 && this.view !== 'chat') {
                    this.view = 'chat';
                    history.pushState({ sfkMessenger: 'chat' }, '');
                }
            },

            closeChat() {
                this.liveConversation(null);
                this.activeId = null;
                this.conv = null;
                this.messages = [];
                this.pulse.url = this.basePulse;
                this.view = 'list';
            },

            back() {
                if (history.state?.sfkMessenger === 'chat') { history.back(); } else { this.view = 'list'; }
            },

            onPopState() {
                if (this.viewer) { this.viewer = null; return; }
                if (this.view === 'chat') { this.view = 'list'; this.stopTyping(); }
            },

            // ---- the message log --------------------------------------------------------------------------------------
            get rows() {
                const out = [];
                let lastDay = null;
                let prev = null;
                for (const m of this.messages) {
                    const d = new Date(m.at);
                    const day = dayKey(d);
                    if (day !== lastDay) {
                        out.push({ key: 'd' + day, kind: 'date', label: this.dateLabel(d) });
                        lastDay = day;
                        prev = null;
                    }
                    if (this.unreadMarker && m.id === this.unreadMarker) { out.push({ key: 'unread', kind: 'unread' }); prev = null; }
                    const system = m.type === 'system';
                    const start = !prev || prev.user !== m.user || prev.type === 'system' || (d - new Date(prev.at)) > 5 * 60000;
                    out.push({ key: m.cid ? 'c' + m.cid : 'm' + m.id, kind: system ? 'system' : 'msg', m, mine: m.user === this.me.id, start });
                    prev = m;
                }
                return out;
            },

            dateLabel(d) {
                const now = new Date();
                if (dayKey(d) === dayKey(now)) { return '今日'; }
                const y = new Date(now); y.setDate(now.getDate() - 1);
                if (dayKey(d) === dayKey(y)) { return '昨日'; }
                return (d.getFullYear() !== now.getFullYear() ? d.getFullYear() + '年' : '') + `${d.getMonth() + 1}月${d.getDate()}日(${WEEK[d.getDay()]})`;
            },

            time(iso) { const d = new Date(iso); return `${pad(d.getHours())}:${pad(d.getMinutes())}`; },
            fullTime(iso) { const d = new Date(iso); return `${d.getFullYear()}/${d.getMonth() + 1}/${d.getDate()} ${pad(d.getHours())}:${pad(d.getMinutes())}`; },

            /* the text of a message as HTML: escaped, line breaks kept, http(s) links, @mentions highlighted */
            renderText(m) {
                let html = escapeHtml(m.body || '');
                html = html.replace(/https?:\/\/[^\s<>"']+/g, (url) => {
                    const clean = url.replace(/[.,);:!?。、]+$/, '');
                    const rest = url.slice(clean.length);
                    return `<a href="${clean}" target="_blank" rel="noopener noreferrer nofollow">${clean}</a>${rest}`;
                });
                const names = [];
                if (m.all) { names.push(['全員', true]); }
                for (const id of m.mentions || []) { names.push([this.nameOf(id), id === this.me.id]); }
                for (const [name, me] of names) {
                    if (!name) { continue; }
                    const token = '@' + escapeHtml(name);
                    html = html.split(token).join(`<span class="sfk-m-mention${me || name === '全員' ? ' is-me' : ''}">${token}</span>`);
                }
                return html;
            },

            isOnlyEmoji(m) {
                const body = (m.body || '').trim();
                return !m.att?.length && body.length > 0 && body.length <= 12 && /^(\p{Extended_Pictographic}|\p{Emoji_Component}|‍|️|\s)+$/u.test(body) && !/^[\d#*\s]+$/.test(body);
            },

            stickerOf(key) { return this.stickers.find((s) => s.key === key) || null; },
            images(m) { return (m.att || []).filter((a) => a.kind === 'image'); },
            files(m) { return (m.att || []).filter((a) => a.kind === 'file'); },
            voices(m) { return (m.att || []).filter((a) => a.kind === 'voice'); },
            fileIcon,
            formatSize,
            formatDuration,

            /* 既読 / 配信済み / 送信済み of one's own message, from the members' watermarks */
            /* the seen-by avatars under the newest message (members who read it; not when 既読 is off) */
            get seenBy() {
                if (!this.policy.receipts || !this.messages.length) { return []; }
                const last = [...this.messages].reverse().find((m) => m.id && m.type !== 'system');
                if (!last) { return []; }
                return this.reads.filter((r) => r.user !== this.me.id && r.user !== last.user && r.read >= last.id).map((r) => r.user);
            },

            status(m) {
                if (m.pending) { return m.failed ? 'failed' : 'sending'; }
                if (!this.policy.receipts) { return '送信済み'; }
                const others = this.reads.filter((r) => r.user !== this.me.id);
                const read = others.filter((r) => r.read >= m.id).length;
                if (this.shared) {
                    if (read > 0) { return '既読 ' + read; }
                } else if (read > 0) {
                    return '既読';
                }
                return others.some((r) => r.delivered >= m.id) ? '配信済み' : '送信済み';
            },

            showReaders(m) {
                if (!this.shared) { return; }
                const others = this.reads.filter((r) => r.user !== this.me.id);
                this.readers = {
                    read: others.filter((r) => r.read >= m.id).map((r) => r.user),
                    unread: others.filter((r) => r.read < m.id).map((r) => r.user),
                };
                this.dialog = 'readers';
            },

            isAtBottom() {
                const log = this.$refs.log;
                return !log || log.scrollHeight - log.scrollTop - log.clientHeight < 80;
            },

            scrollToBottom(smooth = true) {
                if (this.newer) { this.openConversation(this.activeId); return; }
                this.$nextTick(() => {
                    const log = this.$refs.log;
                    if (log) { log.scrollTo({ top: log.scrollHeight, behavior: smooth ? 'smooth' : 'auto' }); }
                    this.atBottom = true;
                    this.newBelow = 0;
                });
            },

            onScroll() {
                const log = this.$refs.log;
                if (!log) { return; }
                this.atBottom = this.isAtBottom();
                if (this.atBottom) { this.newBelow = 0; this.markReadSoon(); }
                if (log.scrollTop < 300 && this.more && !this.loadingOlder) { this.loadOlder(); }
                if (this.newer && log.scrollHeight - log.scrollTop - log.clientHeight < 300 && !this.loadingOlder) { this.loadNewer(); }
            },

            async loadOlder() {
                const first = this.messages.find((m) => m.id);
                if (!first) { return; }
                this.loadingOlder = true;
                const id = this.activeId;
                try {
                    const data = await this.api('GET', `/conversations/${id}/messages?before=${first.id}`);
                    if (this.activeId !== id) { return; }
                    const log = this.$refs.log;
                    const fromBottom = log.scrollHeight - log.scrollTop;
                    this.messages = [...data.messages, ...this.messages];
                    this.more = data.more;
                    this.$nextTick(() => { log.scrollTop = log.scrollHeight - fromBottom; });
                } catch (e) { this.toast(e.message); } finally { this.loadingOlder = false; }
            },

            async loadNewer() {
                const last = [...this.messages].reverse().find((m) => m.id);
                if (!last) { return; }
                this.loadingOlder = true;
                const id = this.activeId;
                try {
                    const data = await this.api('GET', `/conversations/${id}/messages?after=${last.id}`);
                    if (this.activeId !== id) { return; }
                    this.merge(data.messages);
                    this.newer = data.newer;
                } catch (e) { this.toast(e.message); } finally { this.loadingOlder = false; }
            },

            /* new / changed messages into the log (by id; an own pending one by its client id) */
            merge(list) {
                if (!list?.length) { return 0; }
                let added = 0;
                const messages = [...this.messages];
                for (const m of list) {
                    let index = messages.findIndex((x) => x.id === m.id || (m.cid && x.cid === m.cid));
                    if (index >= 0) {
                        messages[index] = { ...m, local: messages[index].local };
                    } else {
                        messages.push(m);
                        added++;
                    }
                }
                messages.sort((a, b) => (a.id || Infinity) - (b.id || Infinity));
                this.messages = messages;
                return added;
            },

            jumpTo(id, load = true) {
                const el = this.$refs.log?.querySelector(`[data-id="${id}"]`);
                if (el) {
                    el.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    this.flashId = id;
                    setTimeout(() => { if (this.flashId === id) { this.flashId = null; } }, 1600);
                    this.atBottom = this.isAtBottom();
                } else if (load) {
                    this.openConversation(this.activeId, id);
                }
            },

            markReadSoon() {
                clearTimeout(this._readTimer);
                this._readTimer = setTimeout(() => this.markRead(), 400);
            },

            async markRead() {
                if (!this.activeId || document.visibilityState !== 'visible' || !this.atBottom || this.newer) { return; }
                const last = [...this.messages].reverse().find((m) => m.id && !m.pending);
                const mine = this.reads.find((r) => r.user === this.me.id);
                if (!last || (mine && mine.read >= last.id)) { return; }
                if (mine) { mine.read = last.id; }
                const item = this.activeItem;
                if (item) { item.unread = 0; item.mention = false; this.badge(); }
                try { await this.api('POST', `/conversations/${this.activeId}/read`, { message: last.id }); } catch (e) { /* the next one */ }
            },

            // ---- real time ------------------------------------------------------------------------------------------
            schedule(ms) {
                clearTimeout(this.pulse.timer);
                this.pulse.timer = setTimeout(() => this.tick(), ms);
            },

            async tick(now = false) {
                if (this.pulse.busy) { if (!now) { this.schedule(this.policy.active); } return; }
                this.pulse.busy = true;
                const url = this.pulse.url;
                const id = this.activeId;
                try {
                    const idle = Date.now() - this.activeAt > 5 * 60 * 1000 ? (url.includes('?') ? '&' : '?') + 'idle=1' : '';
                    const response = await fetch(url + idle, { cache: 'no-store', credentials: 'omit', headers: { Accept: 'application/json' } });
                    if (response.ok && url === this.pulse.url) {
                        const p = await response.json();
                        if (this.pulse.i !== null && p.i !== this.pulse.i) { this.refreshInbox(); }
                        this.pulse.i = p.i;
                        if (id && id === this.activeId) {
                            if (p.c !== this.pulse.c) { const first = this.pulse.c === null; this.pulse.c = p.c; if (!first || this.syncTime) { await this.sync(); } }
                            if (p.r !== this.pulse.r) { const first = this.pulse.r === null; this.pulse.r = p.r; if (!first) { this.loadReads(); } }
                            if (!window.sfkRealtime?.connected) {
                                this.typingIds = (p.t || []).map(Number).filter((uid) => uid !== this.me.id);
                            }
                            this.presence = p.o;
                        }
                    }
                } catch (e) {
                    /* offline: try again */
                } finally {
                    this.pulse.busy = false;
                    if (this.$root.isConnected) {
                        // pushed over WebSockets: the pulse only refreshes the presence and catches what a push missed
                        const pushed = window.sfkRealtime?.connected === true;
                        this.schedule(pushed ? 15000 : (document.visibilityState === 'visible' ? this.policy.active : this.policy.background));
                    }
                }
            },

            async sync() {
                const id = this.activeId;
                if (!id || !this.conv) { return; }
                const ids = this.messages.filter((m) => m.id).map((m) => m.id);
                const after = ids.length ? Math.max(...ids) : 0;
                const from = ids.length ? Math.min(...ids) : 0;
                try {
                    const data = await this.api('GET', `/conversations/${id}/sync?after=${after}&from=${from}&since=${encodeURIComponent(this.syncTime || '')}`);
                    if (this.activeId !== id) { return; }
                    if (data.gap) { this.openConversation(id); return; }
                    this.syncTime = data.now;
                    const wasBottom = this.atBottom;
                    this.merge(data.changed);
                    const added = this.merge(data.messages);
                    const fromOthers = data.messages.filter((m) => m.user !== this.me.id).length;
                    if (added > 0) {
                        if (wasBottom) { this.scrollToBottom(); this.markReadSoon(); } else { this.newBelow += fromOthers; }
                        if (fromOthers > 0 && this.prefs.sound && document.visibilityState !== 'visible') { window.sfkMessagingSound?.(); }
                    }
                } catch (e) {
                    if (e.status === 404) { this.toast('このトークは表示できなくなりました。'); this.closeChat(); this.refreshInbox(); }
                }
            },

            async loadReads() {
                const id = this.activeId;
                try {
                    const data = await this.api('GET', `/conversations/${id}/reads`);
                    if (this.activeId === id) { this.reads = data.reads; }
                } catch (e) { /* next time */ }
            },

            get typingLine() {
                if (!this.typingIds.length) { return ''; }
                const names = this.typingIds.map((uid) => this.nameOf(uid)).filter(Boolean);
                if (!names.length) { return ''; }
                return names.length > 2 ? `${names[0]}さん他${names.length - 1}人が入力中...` : names.map((n) => n + 'さん').join('、') + 'が入力中...';
            },

            get presenceLine() {
                if (!this.conv || this.conv.type !== 'direct') {
                    const size = this.activeItem ? `メンバー ${this.activeItem.members}人` : '';
                    return this.conv?.archived ? size + '・アーカイブ' : size;
                }
                if (this.activeItem?.inactive) { return '退職済み'; }
                return this.presenceText(this.presence);
            },

            typingPing(on) {
                if (!this.activeId || !this.pulse.url || this.pulse.url === this.basePulse) { return; }
                const now = Date.now();
                if (on && now - this.pulse.lastTyping < 3000) { return; }
                if (!on && this.pulse.lastTyping === 0) { return; }
                this.pulse.lastTyping = on ? now : 0;
                fetch(this.pulse.url, { method: 'POST', credentials: 'omit', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ typing: on }), keepalive: !on }).catch(() => {});
            },

            stopTyping() { this.typingPing(false); },

            // ---- composer -------------------------------------------------------------------------------------------
            onInput(event) {
                this.autosize();
                if (this.text.trim() !== '') { this.typingPing(true); } else { this.stopTyping(); }
                this.updateSuggest(event.target);
            },

            autosize() {
                const input = this.$refs.input;
                if (!input) { return; }
                input.style.height = 'auto';
                input.style.height = Math.min(input.scrollHeight, 160) + 'px';
                // a scrollbar only once the text is taller than the box may grow
                input.style.overflowY = input.scrollHeight > 160 ? 'auto' : 'hidden';
            },

            onKeydown(event) {
                if (this.suggest.open) {
                    if (event.key === 'ArrowDown') { event.preventDefault(); this.suggest.index = (this.suggest.index + 1) % this.suggest.items.length; return; }
                    if (event.key === 'ArrowUp') { event.preventDefault(); this.suggest.index = (this.suggest.index - 1 + this.suggest.items.length) % this.suggest.items.length; return; }
                    if ((event.key === 'Enter' || event.key === 'Tab') && !event.isComposing) { event.preventDefault(); this.chooseMention(this.suggest.items[this.suggest.index]); return; }
                    if (event.key === 'Escape') { this.suggest.open = false; return; }
                }
                if (event.key === 'Escape' && (this.editing || this.replyTo)) { this.cancelCompose(); return; }
                // Enter sends on a computer (not while the IME is converting); Shift+Enter is a new line; phones send with the button
                if (event.key === 'Enter' && !event.shiftKey && !event.isComposing && event.keyCode !== 229 && !isTouch()) {
                    event.preventDefault();
                    this.submit();
                }
                if (event.key === 'ArrowUp' && !this.text && !this.editing) {
                    const own = [...this.messages].reverse().find((m) => m.user === this.me.id && m.id && !m.unsent && ['text', 'image', 'file'].includes(m.type));
                    if (own && this.canEdit(own)) { event.preventDefault(); this.startEdit(own); }
                }
            },

            onPaste(event) {
                const files = [...(event.clipboardData?.files || [])];
                if (files.length) { event.preventDefault(); this.addFiles(files); }
            },

            onDrop(event) {
                this.dragging = false;
                const files = [...(event.dataTransfer?.files || [])];
                if (files.length && this.activeId) { this.addFiles(files); }
            },

            get members() {
                return this.reads.map((r) => r.user).filter((id) => id !== this.me.id);
            },

            get canMentionAll() {
                if (!this.shared) { return false; }
                return this.conv.settings?.mention_all === 'everyone' || this.conv.me.role === 'admin';
            },

            updateSuggest(input) {
                const caret = input.selectionStart ?? this.text.length;
                const before = this.text.slice(0, caret);
                const match = before.match(/(^|[\s　])@([^\s　@]{0,20})$/u);
                if (!match || !this.shared) { this.suggest.open = false; return; }
                const q = match[2].toLowerCase();
                const items = [];
                if (this.canMentionAll && ('全員'.includes(q) || 'all'.startsWith(q) || 'ぜんいん'.startsWith(q))) { items.push({ id: 'all', name: '全員' }); }
                for (const id of this.members) {
                    const p = this.person(id);
                    if (!q || p.name.toLowerCase().includes(q) || (p.kana || '').toLowerCase().includes(q)) { items.push({ id, name: p.name }); }
                    if (items.length >= 8) { break; }
                }
                this.suggest = { open: items.length > 0, items, index: 0, start: caret - match[2].length - 1 };
            },

            chooseMention(item) {
                if (!item) { return; }
                const input = this.$refs.input;
                const caret = input.selectionStart ?? this.text.length;
                const token = '@' + item.name + ' ';
                this.text = this.text.slice(0, this.suggest.start) + token + this.text.slice(caret);
                if (item.id === 'all') { this.mentionAll = true; } else if (!this.mentionIds.includes(item.id)) { this.mentionIds.push(item.id); }
                this.suggest.open = false;
                this.$nextTick(() => {
                    const at = this.suggest.start + token.length;
                    input.focus();
                    input.setSelectionRange(at, at);
                    this.autosize();
                });
            },

            insert(value) {
                const input = this.$refs.input;
                const start = input?.selectionStart ?? this.text.length;
                const end = input?.selectionEnd ?? this.text.length;
                this.text = this.text.slice(0, start) + value + this.text.slice(end);
                this.$nextTick(() => {
                    if (!input) { return; }
                    if (!isTouch()) { input.focus(); }
                    input.setSelectionRange(start + value.length, start + value.length);
                    this.autosize();
                });
            },

            pickEmoji(emoji) {
                this.insert(emoji);
                this.recentEmoji = [emoji, ...this.recentEmoji.filter((e) => e !== emoji)].slice(0, 24);
                storage.set('sfk-messenger-emoji', this.recentEmoji);
            },

            emojiTabs() { return Object.keys(EMOJI); },
            emojiList(tab) { return tab === 'よく使う' ? (this.recentEmoji.length ? this.recentEmoji : QUICK_REACTIONS) : EMOJI[tab]; },
            quickReactions: QUICK_REACTIONS,

            togglePicker(name) { this.picker = this.picker === name ? null : name; },

            cancelCompose() {
                if (this.editing) { this.text = ''; }
                this.editing = null;
                this.replyTo = null;
                this.$nextTick(() => this.autosize());
            },

            get canSend() {
                return !this.sending && (this.text.trim() !== '' || this.uploads.some((u) => u.id && !u.error)) && !this.uploads.some((u) => !u.id && !u.error);
            },

            async submit() {
                if (this.editing) { return this.saveEdit(); }
                if (!this.canSend || !this.activeId) { return; }
                const body = this.text.trim();
                const ready = this.uploads.filter((u) => u.id && !u.error);
                const mentions = this.mentionIds.filter((id) => body.includes('@' + this.nameOf(id)));
                const payload = {
                    body,
                    client_id: uuid(),
                    reply_to_id: this.replyTo?.id || null,
                    attachment_ids: ready.map((u) => u.id),
                    mentions,
                    mention_all: this.mentionAll && body.includes('@全員'),
                    priority: this.priority,
                };
                const local = ready.map((u) => ({ id: u.id, kind: u.kind, name: u.name, size: u.size, url: u.preview || '', w: u.w, h: u.h, dur: u.dur }));
                this.text = '';
                this.drafts[this.activeId] = '';
                this.uploads = [];
                this.replyTo = null;
                this.mentionIds = [];
                this.mentionAll = false;
                this.picker = null;
                this.priority = 0;
                this.expanded = false;
                this.$nextTick(() => this.autosize());
                this.stopTyping();
                this.pushPending(payload, local);
            },

            sendSticker(key) {
                this.picker = null;
                this.pushPending({ body: '', client_id: uuid(), sticker: key, reply_to_id: this.replyTo?.id || null }, []);
                this.replyTo = null;
            },

            pushPending(payload, local) {
                const type = payload.sticker ? 'sticker' : (local[0] ? local[0].kind : 'text');
                const pending = {
                    id: null, cid: payload.client_id, user: this.me.id, type, body: payload.body || null, sticker: payload.sticker || null,
                    att: local, rx: [], cards: [], mentions: payload.mentions || [], all: payload.mention_all || false,
                    reply: payload.reply_to_id ? this.quoteOf(payload.reply_to_id) : null,
                    priority: payload.priority || 0, record: payload.record ? { state: 'pending', app: payload.recordLabel || '' } : null,
                    at: new Date().toISOString(), pending: true, failed: false, payload,
                };
                this.messages = [...this.messages, pending];
                this.scrollToBottom();
                this.deliver(pending);
            },

            async deliver(pending) {
                const id = this.activeId;
                pending.failed = false;
                try {
                    const data = await this.api('POST', `/conversations/${id}/messages`, pending.payload);
                    if (this.activeId === id) { this.merge([data.message]); }
                    const item = this.conversations.find((c) => c.id === id);
                    if (item) {
                        item.last = { id: data.message.id, user: this.me.id, type: data.message.type, text: data.message.body || (data.message.type === 'sticker' ? 'スタンプを送信しました' : '') };
                        item.at = data.message.at;
                        this.notified[id] = data.message.id;
                    }
                } catch (e) {
                    const index = this.messages.findIndex((m) => m.cid === pending.cid);
                    if (index >= 0) { this.messages[index] = { ...this.messages[index], failed: true }; }
                    this.toast(e.message);
                }
            },

            retry(m) {
                const index = this.messages.findIndex((x) => x.cid === m.cid);
                if (index >= 0) { this.messages[index] = { ...m, failed: false }; this.deliver(this.messages[index]); }
            },

            discardPending(m) { this.messages = this.messages.filter((x) => x.cid !== m.cid); },

            quoteOf(id) {
                const m = this.messages.find((x) => x.id === id);
                if (!m) { return null; }
                return { id, user: m.user, text: m.type === 'sticker' ? 'スタンプ' : (m.body || (m.att?.[0]?.kind === 'image' ? '画像' : m.att?.[0]?.name || '')), thumb: m.att?.[0]?.kind === 'image' ? m.att[0].url : null };
            },

            // ---- attachments ----------------------------------------------------------------------------------------
            chooseFiles(images = false) {
                const input = images ? this.$refs.images : this.$refs.files;
                input.value = '';
                input.click();
            },

            addFiles(fileList) {
                const files = [...fileList];
                if (this.uploads.length + files.length > this.policy.maxFiles) {
                    this.toast(`一度に送れるファイルは${this.policy.maxFiles}件までです。`);
                    return;
                }
                for (const file of files) {
                    const ext = (file.name.split('.').pop() || '').toLowerCase();
                    const upload = { key: uuid(), id: null, name: file.name, size: file.size, kind: file.type.startsWith('image/') ? 'image' : 'file', progress: 0, error: null, preview: null, w: null, h: null };
                    if (!this.policy.types.includes(ext)) {
                        upload.error = 'この形式は送信できません';
                    } else if (file.size > this.policy.maxKb * 1024) {
                        upload.error = `${Math.round(this.policy.maxKb / 1024)}MBを超えています`;
                    }
                    if (upload.kind === 'image' && !upload.error) {
                        upload.preview = URL.createObjectURL(file);
                        const img = new Image();
                        img.onload = () => { upload.w = img.naturalWidth; upload.h = img.naturalHeight; };
                        img.src = upload.preview;
                    }
                    this.uploads.push(upload);
                    if (!upload.error) { this.upload(this.uploads[this.uploads.length - 1], file); }
                }
                this.picker = null;
            },

            upload(upload, file, extra = {}) {
                return new Promise((resolve) => {
                    const form = new FormData();
                    form.append('file', file, file.name || 'voice.webm');
                    for (const [k, v] of Object.entries(extra)) { form.append(k, v); }
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', `${this.urls.api}/conversations/${this.activeId}/attachments`);
                    xhr.setRequestHeader('Accept', 'application/json');
                    xhr.setRequestHeader('X-CSRF-TOKEN', this.csrf());
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    xhr.withCredentials = true;
                    xhr.upload.onprogress = (e) => { if (e.lengthComputable) { upload.progress = Math.round((e.loaded / e.total) * 100); } };
                    xhr.onload = () => {
                        let data = null;
                        try { data = JSON.parse(xhr.responseText); } catch (e) { data = null; }
                        if (xhr.status >= 200 && xhr.status < 300 && data?.attachment) {
                            upload.id = data.attachment.id;
                            upload.progress = 100;
                            upload.dur = data.attachment.dur;
                            if (!upload.preview) { upload.preview = data.attachment.url; }
                        } else {
                            upload.error = data?.message || (data?.errors ? Object.values(data.errors).flat()[0] : null) || 'アップロードできませんでした';
                        }
                        resolve(upload);
                    };
                    xhr.onerror = () => { upload.error = '通信できませんでした'; resolve(upload); };
                    xhr.send(form);
                });
            },

            removeUpload(upload) {
                this.uploads = this.uploads.filter((u) => u.key !== upload.key);
                if (upload.id) { this.api('DELETE', `/attachments/${upload.id}`).catch(() => {}); }
                if (upload.preview?.startsWith('blob:')) { URL.revokeObjectURL(upload.preview); }
            },

            // ---- voice messages -------------------------------------------------------------------------------------
            get canRecord() { return !!(navigator.mediaDevices?.getUserMedia && window.MediaRecorder); },

            async startRecording() {
                if (!this.canRecord) { this.toast('このブラウザでは録音できません。'); return; }
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    const type = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'].find((t) => MediaRecorder.isTypeSupported?.(t)) || '';
                    const recorder = new MediaRecorder(stream, type ? { mimeType: type } : undefined);
                    const chunks = [];
                    recorder.ondataavailable = (e) => { if (e.data.size) { chunks.push(e.data); } };
                    const started = Date.now();
                    this.recording = { recorder, stream, started, elapsed: 0, timer: setInterval(() => {
                        this.recording.elapsed = Date.now() - started;
                        if (this.recording.elapsed >= this.policy.voiceSeconds * 1000) { this.stopRecording(); }
                    }, 200) };
                    recorder.onstop = () => {
                        stream.getTracks().forEach((t) => t.stop());
                        if (this.recording?.cancelled) { this.recording = null; return; }
                        const mime = recorder.mimeType || type || 'audio/webm';
                        const ext = mime.includes('mp4') ? 'm4a' : mime.includes('ogg') ? 'ogg' : 'webm';
                        const blob = new Blob(chunks, { type: mime.split(';')[0] });
                        this.voice = { blob, ext, url: URL.createObjectURL(blob), duration: Date.now() - started };
                        this.recording = null;
                    };
                    recorder.start(250);
                } catch (e) {
                    this.toast('マイクを使用できません。ブラウザの設定でマイクを許可してください。');
                }
            },

            stopRecording(cancel = false) {
                if (!this.recording) { return; }
                clearInterval(this.recording.timer);
                this.recording.cancelled = cancel;
                try { this.recording.recorder.stop(); } catch (e) { this.recording = null; }
            },

            discardVoice() {
                if (this.voice?.url) { URL.revokeObjectURL(this.voice.url); }
                this.voice = null;
            },

            async sendVoice() {
                if (!this.voice || !this.activeId) { return; }
                const voice = this.voice;
                this.voice = null;
                const file = new File([voice.blob], `voice.${voice.ext}`, { type: voice.blob.type });
                const upload = { key: uuid(), id: null, name: file.name, size: file.size, kind: 'voice', progress: 0, error: null, preview: voice.url };
                const done = await this.upload(upload, file, { voice: '1', duration: String(Math.round(voice.duration)) });
                if (done.error) { this.toast(done.error); return; }
                this.pushPending({ body: '', client_id: uuid(), attachment_ids: [done.id], reply_to_id: this.replyTo?.id || null }, [{ id: done.id, kind: 'voice', name: file.name, size: file.size, url: voice.url, dur: Math.round(voice.duration) }]);
                this.replyTo = null;
            },

            // ---- message actions ------------------------------------------------------------------------------------
            openMenu(m, event) {
                if (m.pending || m.type === 'system') { return; }
                const rect = event.currentTarget.getBoundingClientRect();
                this.menu = { m, x: Math.min(rect.left, window.innerWidth - 240), y: rect.bottom + 4, up: rect.bottom > window.innerHeight - 320 };
                if (this.menu.up) { this.menu.y = rect.top - 4; }
            },

            longPress(m, event) {
                if (!isTouch() || m.pending || m.type === 'system') { return; }
                clearTimeout(this._press);
                const target = event.currentTarget;
                this._press = setTimeout(() => {
                    navigator.vibrate?.(10);
                    this.menu = { m, sheet: true };
                    target.blur?.();
                }, 450);
            },
            cancelPress() { clearTimeout(this._press); },

            canEdit(m) {
                if (m.user !== this.me.id || m.unsent || m.pending || !['text', 'image', 'file'].includes(m.type)) { return false; }
                return this.policy.edit === null || (Date.now() - new Date(m.at)) <= this.policy.edit * 60000;
            },
            canUnsend(m) {
                if (m.user !== this.me.id || m.unsent || m.pending) { return false; }
                return this.policy.unsend === null || (Date.now() - new Date(m.at)) <= this.policy.unsend * 60000;
            },
            get canPin() {
                if (!this.conv) { return false; }
                return this.conv.type !== 'group' || this.conv.settings?.pin_messages === 'everyone' || this.conv.me.role === 'admin';
            },

            async react(m, emoji) {
                this.menu = null;
                const id = this.activeId;
                // drawn at once, corrected by the answer
                const rx = (m.rx || []).map((r) => ({ ...r, users: [...r.users] }));
                const mine = rx.find((r) => r.mine);
                if (mine) { mine.n--; mine.mine = false; mine.users = mine.users.filter((u) => u !== this.me.id); }
                if (!mine || mine.e !== emoji) {
                    const target = rx.find((r) => r.e === emoji);
                    if (target) { target.n++; target.mine = true; target.users.push(this.me.id); } else { rx.push({ e: emoji, n: 1, mine: true, users: [this.me.id] }); }
                }
                this.replaceMessage({ ...m, rx: rx.filter((r) => r.n > 0) });
                try {
                    const data = await this.api('POST', `/messages/${m.id}/react`, { emoji });
                    if (this.activeId === id) { this.replaceMessage(data.message); }
                } catch (e) { this.toast(e.message); this.replaceMessage(m); }
            },

            reactionTitle(r) { return r.users.map((u) => this.nameOf(u)).join('、'); },

            replaceMessage(m) {
                const index = this.messages.findIndex((x) => x.id === m.id);
                if (index >= 0) { this.messages[index] = { ...this.messages[index], ...m }; }
            },

            reply(m) {
                this.menu = null;
                this.editing = null;
                this.replyTo = this.quoteOf(m.id);
                this.$nextTick(() => this.$refs.input?.focus());
            },

            async copy(m) {
                this.menu = null;
                try {
                    await navigator.clipboard.writeText(m.body || '');
                    this.toast('コピーしました', 'success');
                } catch (e) { this.toast('コピーできませんでした'); }
            },

            startEdit(m) {
                this.menu = null;
                this.replyTo = null;
                this.editing = m;
                this.text = m.body || '';
                this.$nextTick(() => { this.autosize(); this.$refs.input?.focus(); });
            },

            async saveEdit() {
                const m = this.editing;
                const body = this.text.trim();
                if (!m) { return; }
                if (body === (m.body || '')) { this.cancelCompose(); return; }
                this.editing = null;
                this.text = '';
                this.$nextTick(() => this.autosize());
                this.replaceMessage({ ...m, body, edited: true });
                try {
                    const data = await this.api('PATCH', `/messages/${m.id}`, { body });
                    this.replaceMessage(data.message);
                } catch (e) { this.toast(e.message); this.replaceMessage(m); }
            },

            async unsend(m) {
                this.menu = null;
                if (!confirm('このメッセージの送信を取り消しますか？\n全員の画面から削除されます。')) { return; }
                try {
                    const data = await this.api('POST', `/messages/${m.id}/unsend`);
                    this.replaceMessage(data.message);
                } catch (e) { this.toast(e.message); }
            },

            async hideMessage(m) {
                this.menu = null;
                this.messages = this.messages.filter((x) => x.id !== m.id);
                try { await this.api('POST', `/messages/${m.id}/hide`); } catch (e) { this.toast(e.message); }
            },

            async pinMessage(m, pin) {
                this.menu = null;
                try {
                    const data = await this.api('POST', `/messages/${m.id}/pin`, { pin });
                    this.replaceMessage(data.message);
                    if (this.details) { this.loadDetails(); }
                    this.toast(pin ? 'ピン留めしました' : 'ピン留めを外しました', 'success');
                } catch (e) { this.toast(e.message); }
            },

            forward(m) {
                this.menu = null;
                this.form = { message: m };
                this.pick = { query: '', selected: [] };
                this.dialog = 'forward';
            },

            async sendForward() {
                if (!this.pick.selected.length) { return; }
                try {
                    await this.api('POST', `/messages/${this.form.message.id}/forward`, { conversations: this.pick.selected });
                    this.dialog = null;
                    this.toast('転送しました', 'success');
                    this.refreshInbox();
                } catch (e) { this.toast(e.message); }
            },

            togglePick(id) {
                const i = this.pick.selected.indexOf(id);
                if (i >= 0) { this.pick.selected.splice(i, 1); } else { this.pick.selected.push(id); }
            },

            get forwardTargets() {
                const q = this.pick.query.trim().toLowerCase();
                return this.conversations.filter((c) => !q || c.name.toLowerCase().includes(q)).slice(0, 100);
            },

            openImage(list, index) {
                this.viewer = { list, index };
                if (window.innerWidth < 768) { history.pushState({ sfkMessenger: 'viewer' }, ''); }
            },
            closeViewer() {
                if (history.state?.sfkMessenger === 'viewer') { history.back(); } else { this.viewer = null; }
            },
            viewerStep(step) {
                if (!this.viewer) { return; }
                const n = this.viewer.list.length;
                this.viewer.index = (this.viewer.index + step + n) % n;
            },

            // ---- people pickers, new chats and groups ----------------------------------------------------------------
            peopleMatching(query, exclude = []) {
                const q = query.trim().toLowerCase();
                return Object.values(this.people)
                    .filter((p) => p.active && p.id !== this.me.id && !exclude.includes(p.id))
                    .filter((p) => !q || [p.name, p.kana, p.position, ...(p.categories || [])].join(' ').toLowerCase().includes(q))
                    .sort((a, b) => (a.kana || a.name).localeCompare(b.kana || b.name, 'ja'))
                    .slice(0, 200);
            },

            openDialog(name, form = {}) {
                this.menu = null;
                this.form = form;
                this.pick = { query: '', selected: [] };
                this.dialog = name;
            },

            async startDirect(userId) {
                try {
                    const data = await this.api('POST', '/conversations/direct', { user: userId });
                    this.dialog = null;
                    await this.refreshInbox();
                    this.filter = 'all';
                    this.openConversation(data.id);
                } catch (e) { this.toast(e.message); }
            },

            async createGroup() {
                if (!this.form.name?.trim()) { this.toast('グループ名を入力してください。'); return; }
                try {
                    const data = await this.api('POST', '/conversations', { name: this.form.name, description: this.form.description || null, members: this.pick.selected });
                    this.dialog = null;
                    await this.refreshInbox();
                    this.filter = 'all';
                    this.openConversation(data.id);
                } catch (e) { this.toast(e.message); }
            },

            // ---- conversation state and details --------------------------------------------------------------------------
            async setState(item, state, value) {
                this.menu = null;
                if (state === 'hidden' && !confirm('トークを削除しますか？\nあなたのトーク履歴だけが削除されます。相手の履歴は残ります。')) { return; }
                try {
                    await this.api('POST', `/conversations/${item.id}/state`, { state, value });
                    if (state === 'hidden' && item.id === this.activeId) { this.closeChat(); }
                    await this.refreshInbox();
                    if (this.conv && item.id === this.activeId) {
                        const key = state === 'muted' ? 'muted' : state;
                        if (key in this.conv.me) { this.conv.me[key] = !!value; }
                    }
                } catch (e) { this.toast(e.message); }
            },

            toggleDetails() {
                this.detailsOpen = !this.detailsOpen;
                if (this.detailsOpen && !this.details) { this.loadDetails(); }
            },

            async loadDetails() {
                const id = this.activeId;
                if (!id) { return; }
                try {
                    const data = await this.api('GET', `/conversations/${id}/details`);
                    if (this.activeId === id) { this.details = data; this.conv = { ...this.conv, ...data.conversation }; }
                } catch (e) { this.toast(e.message); }
            },

            get memberRows() {
                return this.reads.map((r) => ({ ...this.person(r.user), role: r.role })).sort((a, b) => (a.role === 'admin' ? 0 : 1) - (b.role === 'admin' ? 0 : 1));
            },
            get isAdmin() { return this.conv?.me?.role === 'admin'; },
            get canEditInfo() { return this.shared && (this.conv.settings?.edit_info === 'everyone' || this.isAdmin); },
            get canAddMembers() { return this.shared && (this.conv.settings?.add_members === 'everyone' || this.isAdmin); },

            async saveInfo() {
                try {
                    await this.api('POST', `/conversations/${this.activeId}/info`, { name: this.form.name, description: this.form.description || null });
                    this.dialog = null;
                    this.refreshInbox();
                    this.loadDetails();
                } catch (e) { this.toast(e.message); }
            },

            async uploadAvatar(event) {
                const file = event.target.files?.[0];
                if (!file) { return; }
                const form = new FormData();
                form.append('avatar', file);
                try {
                    await this.api('POST', `/conversations/${this.activeId}/avatar`, form);
                    this.refreshInbox();
                    this.loadDetails();
                } catch (e) { this.toast(e.message); }
            },

            async saveSettings(key, value) {
                try {
                    await this.api('POST', `/conversations/${this.activeId}/settings`, { [key]: value });
                    this.conv.settings[key] = value;
                } catch (e) { this.toast(e.message); }
            },

            async addMembers() {
                if (!this.pick.selected.length) { return; }
                try {
                    await this.api('POST', `/conversations/${this.activeId}/members`, { users: this.pick.selected });
                    this.dialog = null;
                    this.loadReads();
                    this.refreshInbox();
                } catch (e) { this.toast(e.message); }
            },

            async removeMember(userId) {
                if (!confirm(`${this.nameOf(userId)}さんをグループから削除しますか？`)) { return; }
                try {
                    await this.api('DELETE', `/conversations/${this.activeId}/members/${userId}`);
                    this.loadReads();
                } catch (e) { this.toast(e.message); }
            },

            async setRole(userId, role) {
                try {
                    await this.api('POST', `/conversations/${this.activeId}/members/${userId}/role`, { role });
                    this.loadReads();
                } catch (e) { this.toast(e.message); }
            },

            async leave() {
                if (!confirm('このグループを退出しますか？\n退出するとトーク履歴は表示されなくなります。')) { return; }
                try {
                    await this.api('POST', `/conversations/${this.activeId}/leave`);
                    this.closeChat();
                    this.refreshInbox();
                } catch (e) { this.toast(e.message); }
            },

            async loadShared(kind) {
                try {
                    const data = await this.api('GET', `/conversations/${this.activeId}/shared?kind=${kind}`);
                    this.details[kind === 'files' ? 'files' : 'media'] = data.items;
                } catch (e) { this.toast(e.message); }
            },

            // ---- search -----------------------------------------------------------------------------------------------
            openSearch(scoped = false) {
                this.search = { q: '', user: '', from: '', to: '', files: false, conversation: scoped ? this.activeId : null, results: [], more: false, loading: false, done: false };
                this.dialog = 'search';
                this.$nextTick(() => this.$refs.searchInput?.focus());
            },

            async runSearch(more = false) {
                const s = this.search;
                if (!s.q.trim() && !s.user && !s.from && !s.to && !s.kind) { s.results = []; s.done = false; return; }
                s.loading = true;
                const params = new URLSearchParams();
                if (s.q.trim()) { params.set('q', s.q.trim()); }
                if (s.user) { params.set('user', s.user); }
                if (s.from) { params.set('from', s.from); }
                if (s.to) { params.set('to', s.to); }
                if (s.files) { params.set('files', '1'); }
                if (s.kind) { params.set('kind', s.kind); }
                if (s.conversation) { params.set('conversation', s.conversation); }
                if (more && s.results.length) { params.set('before', s.results[s.results.length - 1].id); }
                try {
                    const data = await this.api('GET', '/search?' + params.toString());
                    s.results = more ? [...s.results, ...data.results] : data.results;
                    s.more = data.more;
                    s.done = true;
                } catch (e) { this.toast(e.message); } finally { s.loading = false; }
            },

            highlight(text) {
                let html = escapeHtml(text);
                for (const word of this.search.q.trim().split(/[\s　]+/).filter(Boolean)) {
                    const safe = escapeHtml(word).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    html = html.replace(new RegExp(safe, 'gi'), (x) => `<mark>${x}</mark>`);
                }
                return html;
            },

            openResult(result) {
                this.dialog = null;
                this.filter = this.conversations.find((c) => c.id === result.conversation)?.archived ? 'archived' : this.filter === 'announcements' ? 'all' : this.filter;
                if (result.conversation === this.activeId && this.messages.some((m) => m.id === result.id)) {
                    this.jumpTo(result.id);
                } else {
                    this.openConversation(result.conversation, result.id);
                }
            },

            // ---- announcements --------------------------------------------------------------------------------------
            async loadAnnouncements() {
                try {
                    const data = await this.api('GET', '/announcements' + (this.annQuery.trim() ? '?q=' + encodeURIComponent(this.annQuery.trim()) : ''));
                    this.announcements = data.announcements;
                } catch (e) { this.toast(e.message); }
            },

            async openAnnouncement(id) {
                this.activeId = null;
                this.conv = null;
                this.pulse.url = this.basePulse;
                this.showChatScreen();
                try {
                    const data = await this.api('GET', `/announcements/${id}`);
                    this.announcement = data.announcement;
                    const row = this.announcements.find((a) => a.id === id);
                    if (row && !row.read) { row.read = true; this.announcementsUnread = Math.max(0, this.announcementsUnread - 1); this.badge(); }
                } catch (e) { this.toast(e.message); }
            },

            async confirmAnnouncement() {
                try {
                    await this.api('POST', `/announcements/${this.announcement.id}/confirm`);
                    this.announcement.confirmed = true;
                    const row = this.announcements.find((a) => a.id === this.announcement.id);
                    if (row) { row.confirmed = true; }
                    this.toast('確認しました', 'success');
                } catch (e) { this.toast(e.message); }
            },

            async publishAnnouncement() {
                const f = this.form;
                const data = new FormData();
                data.append('title', f.title || '');
                data.append('body', f.body || '');
                data.append('priority', f.priority || 'normal');
                if (f.expires) { data.append('expires_at', f.expires); }
                data.append('requires_confirmation', f.confirm ? '1' : '0');
                data.append('audience', f.audience || 'all');
                for (const id of (f.audience === 'categories' ? f.categories || [] : f.audience === 'groups' ? f.groups || [] : f.audience === 'users' ? this.pick.selected : [])) { data.append('audience_ids[]', id); }
                if (f.at) { data.append('published_at', f.at); }
                for (const file of this.$refs.announcementFiles?.files || []) { data.append('files[]', file); }
                try {
                    f.busy = true;
                    const result = await this.api('POST', '/announcements', data);
                    this.dialog = null;
                    this.filter = 'announcements';
                    await this.loadAnnouncements();
                    this.openAnnouncement(result.id);
                } catch (e) { this.toast(e.message); } finally { f.busy = false; }
            },

            // ---- settings -------------------------------------------------------------------------------------------
            async savePrefs(changes) {
                if (changes.desktop && 'Notification' in window && Notification.permission !== 'granted') {
                    const permission = await Notification.requestPermission();
                    if (permission !== 'granted') { this.toast('ブラウザの通知が許可されていません。'); changes.desktop = false; }
                }
                try {
                    const data = await this.api('POST', '/preferences', changes);
                    this.prefs = data.prefs;
                } catch (e) { this.toast(e.message); }
            },

            async addSticker(event) {
                const file = event.target.files?.[0];
                if (!file) { return; }
                const name = prompt('スタンプ名', file.name.replace(/\.[^.]+$/, ''));
                if (!name) { return; }
                const form = new FormData();
                form.append('name', name);
                form.append('file', file);
                try {
                    const data = await this.api('POST', '/stickers', form);
                    this.stickers = data.stickers;
                } catch (e) { this.toast(e.message); }
            },

            async removeSticker(sticker) {
                if (!confirm(`スタンプ「${sticker.name}」を削除しますか？`)) { return; }
                try {
                    const data = await this.api('DELETE', `/stickers/${sticker.key.slice(2)}`);
                    this.stickers = data.stickers;
                } catch (e) { this.toast(e.message); }
            },

            // ---- organisation ------------------------------------------------------------------------------------------
            async readAll() {
                this.menu = null;
                try {
                    await this.api('POST', '/read-all');
                    this.conversations.forEach((c) => { c.unread = 0; c.mention = false; });
                    this.badge();
                    this.toast('すべて既読にしました', 'success');
                } catch (e) { this.toast(e.message); }
            },

            async markUnread(item) {
                this.menu = null;
                try {
                    await this.api('POST', `/conversations/${item.id}/unread`);
                    if (item.id === this.activeId) { this.closeChat(); }
                    await this.refreshInbox();
                } catch (e) { this.toast(e.message); }
            },

            async setLevel(item, level) {
                this.menu = null;
                try {
                    await this.api('POST', `/conversations/${item.id}/state`, { state: 'level', value: level });
                    if (this.conv && item.id === this.activeId) { this.conv.me.level = level; }
                    await this.refreshInbox();
                } catch (e) { this.toast(e.message); }
            },

            /* pinned conversations keep the user's own order: drag one onto another */
            dragStart(item, event) {
                if (!item.pinned) { event.preventDefault(); return; }
                this.dragId = item.id;
                event.dataTransfer.effectAllowed = 'move';
            },
            async dropOn(item) {
                const from = this.dragId;
                this.dragId = null;
                if (!from || from === item.id || !item.pinned) { return; }
                const pinned = this.visibleConversations.filter((c) => c.pinned).map((c) => c.id);
                const order = pinned.filter((id) => id !== from);
                order.splice(order.indexOf(item.id), 0, from);
                order.forEach((id, index) => { const c = this.conversations.find((x) => x.id === id); if (c) { c.pin_order = index + 1; } });
                try { await this.api('POST', '/pins/order', { ids: order }); } catch (e) { this.toast(e.message); }
            },

            // ---- ERP records ------------------------------------------------------------------------------------------
            async openRecordShare() {
                this.picker = null;
                this.records = { apps: this.records.apps, app: this.records.app, q: '', results: [], loading: false };
                this.dialog = 'record';
                if (this.records.apps === null) {
                    try {
                        const data = await this.api('GET', '/records/apps');
                        this.records.apps = data.apps;
                        if (!this.records.app && data.apps[0]) { this.records.app = data.apps[0].code; }
                    } catch (e) { this.toast(e.message); this.records.apps = []; }
                }
                this.searchRecords();
            },
            async searchRecords() {
                if (!this.records.app) { return; }
                this.records.loading = true;
                try {
                    const data = await this.api('GET', '/records/search?app=' + encodeURIComponent(this.records.app) + '&q=' + encodeURIComponent(this.records.q.trim()));
                    this.records.results = data.records;
                } catch (e) { this.toast(e.message); } finally { this.records.loading = false; }
            },
            shareRecord(record) {
                const app = (this.records.apps || []).find((a) => a.code === this.records.app);
                this.dialog = null;
                this.pushPending({ body: this.text.trim(), client_id: uuid(), record: { app: this.records.app, id: record.id }, recordLabel: (app?.name || '') + ' ' + record.title, reply_to_id: this.replyTo?.id || null, priority: this.priority }, []);
                this.text = '';
                this.replyTo = null;
                this.priority = 0;
                this.$nextTick(() => this.autosize());
            },

            // ---- channels -------------------------------------------------------------------------------------------
            async openChannels() {
                this.menu = null;
                this.dialog = 'channels';
                this.pick = { query: '', selected: [] };
                try { this.channelList = (await this.api('GET', '/channels')).channels; } catch (e) { this.toast(e.message); this.channelList = []; }
            },
            async joinChannel(channel) {
                try {
                    const data = await this.api('POST', `/channels/${channel.id}/join`);
                    this.dialog = null;
                    await this.refreshInbox();
                    this.filter = 'all';
                    this.openConversation(data.id);
                } catch (e) { this.toast(e.message); }
            },
            async createChannel() {
                const f = this.form;
                if (!f.name?.trim()) { this.toast('チャンネル名を入力してください。'); return; }
                try {
                    const data = await this.api('POST', '/channels', { name: f.name, description: f.description || null, kind: f.kind, public: !!f.public, post: f.post, members: this.pick.selected, categories: f.categories || [] });
                    this.dialog = null;
                    await this.refreshInbox();
                    this.filter = 'all';
                    this.openConversation(data.id);
                } catch (e) { this.toast(e.message); }
            },
            async archiveChannel(archive) {
                if (archive && !confirm('このチャンネルをアーカイブしますか？\nメンバーは引き続き閲覧できますが、投稿できなくなります。')) { return; }
                try {
                    await this.api('POST', `/channels/${this.activeId}/archive`, { archive });
                    await this.loadDetails();
                    await this.refreshInbox();
                    this.conv.archived = archive;
                    this.conv.me.post = !archive && this.conv.me.post;
                } catch (e) { this.toast(e.message); }
            },
            get myGroups() { return this.conversations.filter((c) => c.type !== 'direct'); },

            // ---- message information, moderation, date, links ----------------------------------------------------------
            async showInfo(m) {
                this.menu = null;
                this.info = null;
                this.dialog = 'info';
                try { this.info = (await this.api('GET', `/messages/${m.id}/info`)).info; } catch (e) { this.dialog = null; this.toast(e.message); }
            },
            async moderate(m) {
                this.menu = null;
                if (!confirm(`${this.nameOf(m.user)}さんのメッセージを管理者として削除しますか？\n全員の画面から削除され、操作は監査ログに記録されます。`)) { return; }
                try {
                    const data = await this.api('POST', `/messages/${m.id}/moderate`);
                    this.replaceMessage(data.message);
                } catch (e) { this.toast(e.message); }
            },
            async jumpToDate(date) {
                this.dialog = null;
                if (!date || !this.activeId) { return; }
                const id = this.activeId;
                try {
                    const data = await this.api('GET', `/conversations/${id}/date?date=${encodeURIComponent(date)}`);
                    if (this.activeId !== id) { return; }
                    if (!data.target) { this.toast('この日以降のメッセージはありません。', 'warning'); return; }
                    this.messages = data.messages;
                    this.more = data.more;
                    this.newer = data.newer;
                    this.$nextTick(() => this.jumpTo(data.target, false));
                } catch (e) { this.toast(e.message); }
            },
            async loadLinks(more = false) {
                const before = more && this.links.items.length ? this.links.items[this.links.items.length - 1].message : null;
                try {
                    const data = await this.api('GET', `/conversations/${this.activeId}/links` + (before ? `?before=${before}` : ''));
                    this.links = { items: more ? [...this.links.items, ...data.items] : data.items, more: data.more, loaded: true };
                } catch (e) { this.toast(e.message); }
            },
            hostOf(url) { try { return new URL(url).host; } catch (e) { return url; } },
            priorityLabel(p) { return p === 2 ? '至急' : p === 1 ? '重要' : ''; },
        };
    }

    document.addEventListener('alpine:init', () => {
        window.Alpine.data('sfkMessenger', messenger);
    });
    // loaded after Alpine started (e.g. an in-place page swap): register now
    if (window.Alpine && typeof window.Alpine.data === 'function') {
        window.Alpine.data('sfkMessenger', messenger);
    }
})();
