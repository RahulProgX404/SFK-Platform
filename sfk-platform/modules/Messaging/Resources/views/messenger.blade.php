{{--
    メッセージ (Modules\Messaging\Filament\Pages\MessengerPage): トーク一覧 | chat | details, in the look of
    ../messenger-design.png (user 2026-11-18). Drawn once with $boot; the Alpine component sfkMessenger
    (Resources/assets/messenger.js) does the rest through the JSON API. Every text from users is put in with x-text, or
    as HTML only after the script escaped it (renderText / highlight).
--}}
@php
    use Filament\Support\Icons\Heroicon;
@endphp
<x-filament-panels::page>
<div class="sfk-messenger" x-data="sfkMessenger(@js($boot))" x-cloak
    :class="{ 'is-chat': view === 'chat', 'has-details': detailsOpen && (activeId || false), 'is-dragging': dragging }"
    @keydown.escape.window="if (viewer) { closeViewer() } else if (menu) { menu = null } else if (dialog) { dialog = null } else if (picker) { picker = null } else if (statusMenu) { statusMenu = false }"
    @click.window="if (menu && !$event.target.closest('.sfk-m-menu, [data-menu-trigger]')) { menu = null }">

    {{-- ===== トーク一覧 ===== --}}
    <aside class="sfk-m-list">
        <header class="sfk-m-me">
            {{-- the sidebar stays closed here (AppSidebarState::collapseOn): 🏠 goes back to the dashboard (user 2026-11-18) --}}
            <a href="{{ filament()->getCurrentOrDefaultPanel()->getUrl() }}" wire:navigate class="sfk-m-icon-btn sfk-m-sidebar-btn" title="ホーム" aria-label="ホーム">
                <x-filament::icon :icon="Heroicon::OutlinedHome" class="sfk-m-ico" />
            </a>
            <div class="sfk-m-me-status" @click.outside="statusMenu = false">
                <button type="button" class="sfk-m-me-btn" @click="statusMenu = !statusMenu" :title="statusLabel">
                    <span class="sfk-m-avatar is-sm" :style="avatarStyle(me.id)"><span x-text="initial(me.name)"></span><span class="sfk-m-dot" :class="statusTone"></span></span>
                    <span class="sfk-m-me-name" x-text="me.name"></span>
                    <x-filament::icon :icon="Heroicon::ChevronDown" class="sfk-m-ico is-xs" />
                </button>
                <div class="sfk-m-pop sfk-m-status-menu" x-show="statusMenu" x-transition.opacity.duration.100ms>
                    <button type="button" @click="setStatus('auto')"><span class="sfk-m-dot is-online"></span>自動（オンライン）<x-filament::icon :icon="Heroicon::Check" class="sfk-m-ico is-xs sfk-m-grow-left" x-show="prefs.status === 'auto'" /></button>
                    <button type="button" @click="setStatus('away')"><span class="sfk-m-dot is-away"></span>離席中<x-filament::icon :icon="Heroicon::Check" class="sfk-m-ico is-xs sfk-m-grow-left" x-show="prefs.status === 'away'" /></button>
                    <button type="button" @click="setStatus('dnd')"><span class="sfk-m-dot is-dnd"></span>取り込み中<x-filament::icon :icon="Heroicon::Check" class="sfk-m-ico is-xs sfk-m-grow-left" x-show="prefs.status === 'dnd'" /></button>
                    <hr>
                    <button type="button" @click="statusMenu = false; openDialog('settings')"><x-filament::icon :icon="Heroicon::OutlinedCog6Tooth" class="sfk-m-ico" />メッセージ設定</button>
                    <a x-show="urls.admin" :href="urls.admin"><x-filament::icon :icon="Heroicon::OutlinedShieldCheck" class="sfk-m-ico" />メッセージ管理</a>
                </div>
            </div>
            <div class="sfk-m-head-actions">
                <button type="button" class="sfk-m-icon-btn" @click="openDialog('direct')" title="社員名簿">
                    <x-filament::icon :icon="Heroicon::OutlinedIdentification" class="sfk-m-ico" />
                </button>
                <div class="sfk-m-new" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" class="sfk-m-icon-btn" @click="open = !open" title="新規">
                        <x-filament::icon :icon="Heroicon::OutlinedPencilSquare" class="sfk-m-ico" />
                    </button>
                    <div class="sfk-m-pop sfk-m-new-menu" x-show="open" x-transition.opacity.duration.100ms>
                        <button type="button" @click="open = false; openDialog('direct')"><x-filament::icon :icon="Heroicon::OutlinedUser" class="sfk-m-ico" />新しいトーク</button>
                        <button type="button" x-show="me.groups" @click="open = false; openDialog('group', { name: '', description: '' })"><x-filament::icon :icon="Heroicon::OutlinedUserGroup" class="sfk-m-ico" />グループを作成</button>
                        <button type="button" x-show="me.channels" @click="open = false; openDialog('channel', { name: '', description: '', kind: 'project', public: true, post: 'everyone', categories: [] })"><x-filament::icon :icon="Heroicon::OutlinedHashtag" class="sfk-m-ico" />チャンネルを作成</button>
                        <button type="button" @click="open = false; openChannels()"><x-filament::icon :icon="Heroicon::OutlinedMagnifyingGlassCircle" class="sfk-m-ico" />チャンネルを探す</button>
                        <button type="button" x-show="me.announce" @click="open = false; openDialog('announce', { title: '', body: '', audience: 'all', categories: [], groups: [], priority: 'normal', confirm: false, at: '', expires: '' })"><x-filament::icon :icon="Heroicon::OutlinedMegaphone" class="sfk-m-ico" />全社アナウンスを投稿</button>
                        <hr>
                        <button type="button" @click="open = false; readAll()"><x-filament::icon :icon="Heroicon::OutlinedCheckBadge" class="sfk-m-ico" />すべて既読にする</button>
                    </div>
                </div>
            </div>
        </header>

        <div class="sfk-m-search">
            <x-filament::icon :icon="Heroicon::OutlinedMagnifyingGlass" class="sfk-m-ico" />
            <input type="search" x-model="query" placeholder="トーク・社員を検索" aria-label="トーク・社員を検索">
            <button type="button" class="sfk-m-search-all" @click="openSearch()" title="メッセージを検索"><x-filament::icon :icon="Heroicon::OutlinedDocumentMagnifyingGlass" class="sfk-m-ico" /></button>
        </div>

        <nav class="sfk-m-filters" aria-label="絞り込み"
            @wheel="if ($el.scrollWidth > $el.clientWidth && Math.abs($event.deltaY) > Math.abs($event.deltaX)) { $el.scrollLeft += $event.deltaY; $event.preventDefault() }">
            <template x-for="[key, label] in [['all', 'すべて'], ['unread', '未読'], ['favorites', 'お気に入り'], ['direct', '個人'], ['groups', 'グループ'], ['channels', 'チャンネル'], ['announcements', 'アナウンス'], ['muted', '通知オフ'], ['archived', 'アーカイブ']]" :key="key">
                <button type="button" class="sfk-m-tab" :class="{ 'is-active': filter === key }" @click="setFilter(key)">
                    <span x-text="label"></span>
                    <span class="sfk-m-tab-count" x-show="key === 'announcements' ? announcementsUnread > 0 : (counts[key] || 0) > 0 && key !== 'all'"
                        x-text="key === 'announcements' ? announcementsUnread : counts[key]"></span>
                </button>
            </template>
        </nav>

        <div class="sfk-m-connection" x-show="connectionLine" x-text="connectionLine" role="status"></div>

        {{-- conversations --}}
        <ul class="sfk-m-conversations" x-show="filter !== 'announcements'" role="list">
            <template x-for="item in visibleConversations" :key="item.id">
                <li :draggable="item.pinned ? 'true' : 'false'" @dragstart="dragStart(item, $event)" @dragover.prevent="" @drop.prevent="dropOn(item)" :class="{ 'is-drop': dragId && item.pinned && dragId !== item.id }">
                    <button type="button" class="sfk-m-conv" :class="{ 'is-active': item.id === activeId, 'is-unread': item.unread > 0, 'is-muted': item.muted }"
                        @click="openConversation(item.id)" @contextmenu.prevent="menu = { conv: item, x: Math.min($event.clientX, innerWidth - 240), y: Math.min($event.clientY, innerHeight - 360) }">
                        <span class="sfk-m-avatar" :class="{ 'is-group': item.type !== 'direct' }" :style="item.avatar || item.type !== 'direct' ? {} : avatarStyle(item.peer)">
                            <template x-if="item.avatar"><img :src="item.avatar" alt=""></template>
                            <template x-if="!item.avatar && item.type === 'group'"><x-filament::icon :icon="Heroicon::UserGroup" class="sfk-m-ico" /></template>
                            <template x-if="!item.avatar && item.type === 'channel'"><x-filament::icon :icon="Heroicon::Hashtag" class="sfk-m-ico" /></template>
                            <template x-if="!item.avatar && item.type === 'direct'"><span x-text="initial(item.name)"></span></template>
                            <span class="sfk-m-dot" x-show="item.presence && item.presence !== 'offline'" :class="'is-' + item.presence"></span>
                        </span>
                        <span class="sfk-m-conv-body">
                            <span class="sfk-m-conv-top">
                                <span class="sfk-m-conv-name">
                                    <span x-text="item.name"></span>
                                    <span class="sfk-m-conv-size" x-show="item.type !== 'direct'" x-text="'(' + item.members + ')'"></span>
                                </span>
                                <span class="sfk-m-conv-time" x-text="listTime(item.at)"></span>
                            </span>
                            <span class="sfk-m-conv-bottom">
                                <span class="sfk-m-conv-last">
                                    <span class="sfk-m-prio" x-show="item.last && item.last.priority > 0" :class="'is-' + item.last?.priority" x-text="priorityLabel(item.last?.priority)"></span>
                                    <span x-text="lastLine(item)"></span>
                                </span>
                                <span class="sfk-m-conv-flags">
                                    <x-filament::icon :icon="Heroicon::OutlinedBellSlash" class="sfk-m-flag" x-show="item.muted" />
                                    <x-filament::icon :icon="Heroicon::OutlinedLockClosed" class="sfk-m-flag" x-show="item.readonly" />
                                    <span class="sfk-m-at" x-show="item.mention">@</span>
                                    <span class="sfk-m-unread" x-show="item.unread > 0" x-text="item.unread > 99 ? '99+' : item.unread"></span>
                                    <x-filament::icon :icon="Heroicon::Star" class="sfk-m-flag is-fav" x-show="item.favorite && !(item.unread > 0)" />
                                    <x-filament::icon :icon="Heroicon::MapPin" class="sfk-m-flag is-pin" x-show="item.pinned && !(item.unread > 0) && !item.favorite" />
                                </span>
                            </span>
                        </span>
                    </button>
                </li>
            </template>
        </ul>

        {{-- 全社アナウンス --}}
        <div class="sfk-m-ann-list" x-show="filter === 'announcements'">
            <div class="sfk-m-search is-inline">
                <x-filament::icon :icon="Heroicon::OutlinedMagnifyingGlass" class="sfk-m-ico" />
                <input type="search" x-model="annQuery" @input.debounce.400ms="loadAnnouncements()" placeholder="アナウンスを検索" aria-label="アナウンスを検索">
            </div>
            <ul class="sfk-m-conversations" role="list">
                <template x-for="a in announcements" :key="a.id">
                    <li>
                        <button type="button" class="sfk-m-conv sfk-m-ann-row" :class="{ 'is-active': announcement && announcement.id === a.id, 'is-unread': !a.read }" @click="openAnnouncement(a.id)">
                            <span class="sfk-m-avatar sfk-m-ann-icon" :class="'is-' + a.priority">
                                <x-filament::icon :icon="Heroicon::Megaphone" class="sfk-m-ico" />
                            </span>
                            <span class="sfk-m-conv-body">
                                <span class="sfk-m-conv-top">
                                    <span class="sfk-m-conv-name">
                                        <span class="sfk-m-prio is-1" x-show="a.priority === 'important'">重要</span>
                                        <span class="sfk-m-prio is-2" x-show="a.priority === 'urgent'">至急</span>
                                        <span class="sfk-m-tag" x-show="a.scheduled">予約</span>
                                        <span class="sfk-m-tag" x-show="a.expired">掲載終了</span>
                                        <span x-text="a.title"></span>
                                    </span>
                                    <span class="sfk-m-conv-time" x-text="listTime(a.at)"></span>
                                </span>
                                <span class="sfk-m-conv-bottom">
                                    <span class="sfk-m-conv-last" x-text="a.preview"></span>
                                    <span class="sfk-m-conv-flags">
                                        <span class="sfk-m-tag is-warning" x-show="a.confirm && a.recipient && !a.confirmed">要確認</span>
                                        <span class="sfk-m-unread is-dot" x-show="!a.read"></span>
                                    </span>
                                </span>
                            </span>
                        </button>
                    </li>
                </template>
            </ul>
        </div>
    </aside>

    {{-- ===== chat ===== --}}
    <main class="sfk-m-chat" @dragover.prevent="if (activeId && $event.dataTransfer?.types?.includes('Files')) dragging = true" @dragleave.self="dragging = false" @drop.prevent="onDrop($event)">
        <div class="sfk-m-empty" x-show="!activeId && !announcement">
            <x-filament::icon :icon="Heroicon::OutlinedChatBubbleLeftRight" class="sfk-m-empty-ico" />
        </div>

        {{-- announcement --}}
        <template x-if="announcement && !activeId">
            <section class="sfk-m-ann">
                <header class="sfk-m-chat-head">
                    <button type="button" class="sfk-m-icon-btn sfk-m-back" @click="back(); announcement = null" title="戻る">
                        <x-filament::icon :icon="Heroicon::OutlinedChevronLeft" class="sfk-m-ico" />
                    </button>
                    <div class="sfk-m-chat-title"><strong>全社アナウンス</strong></div>
                </header>
                <article class="sfk-m-ann-body" :class="'is-' + announcement.priority">
                    <div class="sfk-m-ann-tags">
                        <span class="sfk-m-prio is-1" x-show="announcement.priority === 'important'">重要</span>
                        <span class="sfk-m-prio is-2" x-show="announcement.priority === 'urgent'">至急</span>
                        <span class="sfk-m-tag" x-show="announcement.scheduled" x-text="'予約: ' + fullTime(announcement.at)"></span>
                        <span class="sfk-m-tag" x-show="announcement.expires" x-text="(announcement.expired ? '掲載終了: ' : '掲載終了予定: ') + (announcement.expires ? fullTime(announcement.expires) : '')"></span>
                    </div>
                    <h2 class="sfk-m-ann-title" x-text="announcement.title"></h2>
                    <p class="sfk-m-ann-meta"><span x-text="nameOf(announcement.by)"></span>・<span x-text="fullTime(announcement.at)"></span></p>
                    <div class="sfk-m-ann-text" x-html="renderText({ body: announcement.body, mentions: [] })"></div>
                    <div class="sfk-m-files" x-show="announcement.files.length">
                        <template x-for="f in announcement.files" :key="f.id">
                            <a class="sfk-m-file" :href="f.url + '?download=1'">
                                <span class="sfk-m-file-icon" :class="fileIcon(f.name).cls" x-text="fileIcon(f.name).label"></span>
                                <span class="sfk-m-file-text"><span class="sfk-m-file-name" x-text="f.name"></span><span class="sfk-m-file-size" x-text="formatSize(f.size)"></span></span>
                            </a>
                        </template>
                    </div>
                    <div class="sfk-m-ann-confirm" x-show="announcement.confirm && announcement.recipient">
                        <button type="button" class="sfk-m-btn is-primary" x-show="!announcement.confirmed" @click="confirmAnnouncement()">確認しました</button>
                        <span class="sfk-m-confirmed" x-show="announcement.confirmed"><x-filament::icon :icon="Heroicon::CheckCircle" class="sfk-m-ico" />確認済み</span>
                    </div>
                    <template x-if="announcement.stats">
                        <section class="sfk-m-ann-stats">
                            <div class="sfk-m-stat"><span>対象</span><strong x-text="announcement.stats.total + '人'"></strong></div>
                            <div class="sfk-m-stat"><span>既読</span><strong x-text="announcement.stats.read + ' / ' + announcement.stats.total"></strong></div>
                            <div class="sfk-m-stat"><span>未読</span><strong x-text="announcement.stats.total - announcement.stats.read"></strong></div>
                            <div class="sfk-m-stat" x-show="announcement.confirm"><span>確認</span><strong x-text="announcement.stats.confirmed + ' / ' + announcement.stats.total"></strong></div>
                            <table class="sfk-m-ann-people">
                                <thead><tr><th>従業員</th><th>既読</th><th x-show="announcement.confirm">確認</th></tr></thead>
                                <tbody>
                                    <template x-for="p in announcement.stats.people" :key="p.user">
                                        <tr :class="{ 'is-pending': !p.read_at || (announcement.confirm && !p.confirmed_at) }">
                                            <td x-text="nameOf(p.user)"></td>
                                            <td x-text="p.read_at ? fullTime(p.read_at) : '未読'"></td>
                                            <td x-show="announcement.confirm" x-text="p.confirmed_at ? fullTime(p.confirmed_at) : '未確認'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </section>
                    </template>
                </article>
            </section>
        </template>

        {{-- conversation --}}
        <section class="sfk-m-conversation" x-show="activeId">
            <header class="sfk-m-chat-head">
                <button type="button" class="sfk-m-icon-btn sfk-m-back" @click="back()" title="戻る">
                    <x-filament::icon :icon="Heroicon::OutlinedChevronLeft" class="sfk-m-ico" />
                </button>
                <button type="button" class="sfk-m-chat-who" @click="toggleDetails()">
                    <span class="sfk-m-avatar is-sm" :class="{ 'is-group': activeItem && activeItem.type !== 'direct' }" :style="activeItem?.avatar || activeItem?.type !== 'direct' ? {} : avatarStyle(activeItem?.peer)">
                        <template x-if="activeItem?.avatar"><img :src="activeItem.avatar" alt=""></template>
                        <template x-if="!activeItem?.avatar && activeItem?.type === 'group'"><x-filament::icon :icon="Heroicon::UserGroup" class="sfk-m-ico" /></template>
                        <template x-if="!activeItem?.avatar && activeItem?.type === 'channel'"><x-filament::icon :icon="Heroicon::Hashtag" class="sfk-m-ico" /></template>
                        <template x-if="!activeItem?.avatar && activeItem?.type === 'direct'"><span x-text="initial(activeItem?.name)"></span></template>
                        <span class="sfk-m-dot" x-show="presence && presence.state && presence.state !== 'offline'" :class="'is-' + (presence?.state || '')"></span>
                    </span>
                    <span class="sfk-m-chat-title">
                        <strong x-text="activeItem?.name || ''"></strong>
                        <small :class="{ 'is-online': presence?.state === 'online', 'is-typing': typingLine }" x-text="typingLine || presenceLine"></small>
                    </span>
                </button>
                <div class="sfk-m-head-actions">
                    <div class="sfk-m-new" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" class="sfk-m-icon-btn" :class="{ 'is-on': conv?.me?.level !== 'all' || conv?.me?.muted }" @click="open = !open" title="通知設定">
                            <x-filament::icon :icon="Heroicon::OutlinedBell" class="sfk-m-ico" x-show="!conv?.me?.muted" />
                            <x-filament::icon :icon="Heroicon::OutlinedBellSlash" class="sfk-m-ico" x-show="conv?.me?.muted" />
                        </button>
                        <div class="sfk-m-pop" x-show="open" x-transition.opacity.duration.100ms>
                            <template x-for="[level, label] in [['all', 'すべてのメッセージ'], ['mentions', 'メンション・返信・重要のみ'], ['none', '通知しない']]" :key="level">
                                <button type="button" @click="open = false; setLevel(activeItem, level)"><span x-text="label"></span><x-filament::icon :icon="Heroicon::Check" class="sfk-m-ico is-xs sfk-m-grow-left" x-show="conv?.me?.level === level" /></button>
                            </template>
                            <hr>
                            <template x-for="[hours, label] in [[1, '1時間オフ'], [8, '8時間オフ'], [24, '24時間オフ']]" :key="hours">
                                <button type="button" @click="open = false; setState(activeItem, 'muted', hours); conv.me.muted = true" x-text="label"></button>
                            </template>
                            <button type="button" x-show="conv?.me?.muted_until" @click="open = false; setState(activeItem, 'muted', false); conv.me.muted = false; conv.me.muted_until = null">一時オフを解除</button>
                        </div>
                    </div>
                    <button type="button" class="sfk-m-icon-btn" @click="openSearch(true)" title="トーク内を検索">
                        <x-filament::icon :icon="Heroicon::OutlinedMagnifyingGlass" class="sfk-m-ico" />
                    </button>
                    <button type="button" class="sfk-m-icon-btn" data-menu-trigger @click="menu = menu?.conv ? null : { conv: activeItem, x: Math.min($event.clientX - 200, innerWidth - 250), y: $event.clientY + 16 }" title="その他">
                        <x-filament::icon :icon="Heroicon::OutlinedEllipsisHorizontal" class="sfk-m-ico" />
                    </button>
                    <button type="button" class="sfk-m-icon-btn" :class="{ 'is-on': detailsOpen }" @click="toggleDetails()" title="詳細">
                        <x-filament::icon :icon="Heroicon::OutlinedInformationCircle" class="sfk-m-ico" />
                    </button>
                </div>
            </header>

            <div class="sfk-m-log" x-ref="log" @scroll.passive="onScroll()" role="log" aria-live="polite">
                <div class="sfk-m-loading" x-show="loadingChat || loadingOlder"><span></span><span></span><span></span></div>
                <template x-for="row in rows" :key="row.key">
                    <div class="sfk-m-row-wrap">
                        <template x-if="row.kind === 'date'"><div class="sfk-m-date"><span x-text="row.label"></span></div></template>
                        <template x-if="row.kind === 'unread'"><div class="sfk-m-unread-line" data-unread-marker><span>ここから未読</span></div></template>
                        <template x-if="row.kind === 'system'"><div class="sfk-m-system" :data-id="row.m.id"><span x-text="row.m.body"></span></div></template>
                        <template x-if="row.kind === 'msg'">
                            <div class="sfk-m-row" :class="{ 'is-mine': row.mine, 'is-start': row.start, 'is-flash': flashId === row.m.id }" :data-id="row.m.id">
                                <template x-if="!row.mine && row.start && shared">
                                    <span class="sfk-m-avatar is-xs" :style="avatarStyle(row.m.user)"><span x-text="initial(nameOf(row.m.user))"></span></span>
                                </template>
                                <span class="sfk-m-avatar-gap" x-show="!row.mine && !row.start && shared"></span>
                                <div class="sfk-m-msg">
                                    <div class="sfk-m-sender" x-show="!row.mine && row.start && shared" x-text="nameOf(row.m.user)"></div>
                                    <div class="sfk-m-bubble-wrap" @touchstart.passive="longPress(row.m, $event)" @touchend="cancelPress()" @touchmove="cancelPress()" @contextmenu="if (!row.m.pending) { $event.preventDefault(); menu = { m: row.m, x: Math.min($event.clientX, innerWidth - 250), y: Math.min($event.clientY, innerHeight - 440) } }">
                                        <template x-if="row.m.unsent">
                                            <div class="sfk-m-bubble is-unsent"><x-filament::icon :icon="Heroicon::OutlinedNoSymbol" class="sfk-m-ico" /><span x-text="row.m.moderated ? '管理者によって削除されました' : (row.mine ? 'メッセージの送信を取り消しました' : 'メッセージが送信取消されました')"></span></div>
                                        </template>
                                        <template x-if="!row.m.unsent && row.m.type === 'sticker'">
                                            <div class="sfk-m-sticker">
                                                <template x-if="stickerOf(row.m.sticker)?.url"><img :src="stickerOf(row.m.sticker).url" :alt="stickerOf(row.m.sticker).name" loading="lazy"></template>
                                                <template x-if="stickerOf(row.m.sticker) && !stickerOf(row.m.sticker).url">
                                                    <span class="sfk-m-sticker-card"><span class="sfk-m-sticker-emoji" x-text="stickerOf(row.m.sticker).emoji"></span><span class="sfk-m-sticker-text" x-text="stickerOf(row.m.sticker).text"></span></span>
                                                </template>
                                                <template x-if="!stickerOf(row.m.sticker)"><span class="sfk-m-bubble is-unsent">スタンプ</span></template>
                                                <span class="sfk-m-sticker-time" x-text="time(row.m.at)"></span>
                                            </div>
                                        </template>
                                        <template x-if="!row.m.unsent && row.m.type !== 'sticker'">
                                            <div class="sfk-m-bubble" :class="{ 'is-emoji': isOnlyEmoji(row.m), 'is-prio-1': row.m.priority === 1, 'is-prio-2': row.m.priority === 2, 'is-media': !row.m.body && !row.m.reply && !row.m.record }">
                                                <span class="sfk-m-prio" x-show="row.m.priority > 0" :class="'is-' + row.m.priority" x-text="priorityLabel(row.m.priority)"></span>
                                                <div class="sfk-m-fwd" x-show="row.m.fwd"><x-filament::icon :icon="Heroicon::OutlinedArrowUturnRight" class="sfk-m-ico" /><span x-text="row.m.fwd ? row.m.fwd.name + 'さんのメッセージを転送' : ''"></span></div>
                                                <button type="button" class="sfk-m-quote" x-show="row.m.reply" @click="row.m.reply && jumpTo(row.m.reply.id)">
                                                    <img x-show="row.m.reply?.thumb" :src="row.m.reply?.thumb || ''" alt="">
                                                    <span><strong x-text="row.m.reply ? nameOf(row.m.reply.user) : ''"></strong><span x-text="row.m.reply?.text || ''"></span></span>
                                                </button>
                                                <div class="sfk-m-images" x-show="images(row.m).length" :class="'n' + Math.min(images(row.m).length, 4)">
                                                    <template x-for="(img, index) in images(row.m).slice(0, 4)" :key="img.id || index">
                                                        <button type="button" class="sfk-m-image" @click="openImage(images(row.m), index)" :style="images(row.m).length === 1 && img.w ? { aspectRatio: img.w + ' / ' + img.h } : {}">
                                                            <img :src="img.url" alt="" loading="lazy">
                                                            <span class="sfk-m-image-more" x-show="index === 3 && images(row.m).length > 4" x-text="'+' + (images(row.m).length - 4)"></span>
                                                        </button>
                                                    </template>
                                                </div>
                                                <template x-for="f in files(row.m)" :key="f.id">
                                                    <a class="sfk-m-file" :href="f.url ? f.url + '?download=1' : null" :class="{ 'is-pending': row.m.pending }">
                                                        <span class="sfk-m-file-icon" :class="fileIcon(f.name).cls" x-text="fileIcon(f.name).label"></span>
                                                        <span class="sfk-m-file-text"><span class="sfk-m-file-name" x-text="f.name"></span><span class="sfk-m-file-size" x-text="formatSize(f.size) + '・' + fileIcon(f.name).label"></span></span>
                                                    </a>
                                                </template>
                                                <template x-for="v in voices(row.m)" :key="v.id">
                                                    <div class="sfk-m-voice" x-data="{ playing: false, at: 0 }">
                                                        <button type="button" class="sfk-m-voice-btn" @click="const a = $refs.audio; a.paused ? a.play() : a.pause()" :title="playing ? '一時停止' : '再生'">
                                                            <x-filament::icon :icon="Heroicon::Play" class="sfk-m-ico" x-show="!playing" />
                                                            <x-filament::icon :icon="Heroicon::Pause" class="sfk-m-ico" x-show="playing" />
                                                        </button>
                                                        <span class="sfk-m-voice-bar"><span :style="{ width: (v.dur ? Math.min(100, at / v.dur * 100) : 0) + '%' }"></span></span>
                                                        <span class="sfk-m-voice-time" x-text="formatDuration(playing || at ? at : v.dur)"></span>
                                                        <audio x-ref="audio" :src="v.url" preload="none" @play="playing = true" @pause="playing = false" @ended="playing = false; at = 0" @timeupdate="at = $event.target.currentTime * 1000"></audio>
                                                    </div>
                                                </template>
                                                {{-- a shared ERP record, as this reader may see it --}}
                                                <template x-if="row.m.record">
                                                    <div class="sfk-m-record" :class="'is-' + row.m.record.state">
                                                        <template x-if="row.m.record.state === 'ok'">
                                                            <div>
                                                                <div class="sfk-m-record-head"><x-filament::icon :icon="Heroicon::OutlinedRectangleStack" class="sfk-m-ico" /><span x-text="row.m.record.app"></span></div>
                                                                <strong class="sfk-m-record-title" x-text="row.m.record.title"></strong>
                                                                <dl class="sfk-m-record-fields">
                                                                    <template x-for="field in row.m.record.fields" :key="field.label"><div><dt x-text="field.label"></dt><dd x-text="field.value"></dd></div></template>
                                                                </dl>
                                                                <a class="sfk-m-record-open" :href="row.m.record.url">ERPで詳細を開く<x-filament::icon :icon="Heroicon::OutlinedArrowTopRightOnSquare" class="sfk-m-ico is-xs" /></a>
                                                            </div>
                                                        </template>
                                                        <template x-if="row.m.record.state === 'pending'"><div class="sfk-m-record-note" x-text="row.m.record.app"></div></template>
                                                        <template x-if="row.m.record.state === 'denied'"><div class="sfk-m-record-note"><x-filament::icon :icon="Heroicon::OutlinedLockClosed" class="sfk-m-ico" /><span x-text="row.m.record.app + 'のレコード（閲覧権限がありません）'"></span></div></template>
                                                        <template x-if="row.m.record.state === 'missing'"><div class="sfk-m-record-note"><x-filament::icon :icon="Heroicon::OutlinedExclamationTriangle" class="sfk-m-ico" /><span x-text="row.m.record.app + 'のレコードは削除されたか、表示できません'"></span></div></template>
                                                    </div>
                                                </template>
                                                <div class="sfk-m-text" x-show="row.m.body" x-html="renderText(row.m)"></div>
                                                <template x-for="card in row.m.cards || []" :key="card.url">
                                                    <a class="sfk-m-card" :href="card.url">
                                                        <x-filament::icon :icon="Heroicon::OutlinedDocumentText" class="sfk-m-ico" />
                                                        <span><small x-text="card.app"></small><strong x-text="card.title"></strong></span>
                                                    </a>
                                                </template>
                                                <span class="sfk-m-stamp">
                                                    <span x-show="row.m.edited">編集済み</span>
                                                    <x-filament::icon :icon="Heroicon::Bookmark" class="sfk-m-pin-mark" x-show="row.m.pinned" />
                                                    <template x-if="row.mine">
                                                        <button type="button" class="sfk-m-status" :class="'is-' + (row.m.pending ? (row.m.failed ? 'failed' : 'sending') : (status(row.m).startsWith('既読') ? 'read' : status(row.m) === '配信済み' ? 'delivered' : 'sent'))"
                                                            @click="row.m.failed ? retry(row.m) : showReaders(row.m)" :title="row.m.failed ? '送信失敗・再送' : status(row.m)">
                                                            <span x-show="row.m.failed">再送</span>
                                                            <span x-show="!row.m.failed && row.m.pending" class="sfk-m-clock"></span>
                                                            <span x-show="!row.m.pending && shared && status(row.m).startsWith('既読')" x-text="status(row.m)"></span>
                                                            <svg x-show="!row.m.pending && !(shared && status(row.m).startsWith('既読'))" viewBox="0 0 20 12" class="sfk-m-ticks" aria-hidden="true"><path d="M1 6.5 4.5 10 11 2.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path x-show="status(row.m) !== '送信済み'" d="M8 9.5 8.5 10 15 2.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                        </button>
                                                    </template>
                                                    <time :datetime="row.m.at" :title="fullTime(row.m.at)" x-text="time(row.m.at)"></time>
                                                </span>
                                            </div>
                                        </template>
                                        <div class="sfk-m-reactions" x-show="row.m.rx?.length">
                                            <template x-for="r in row.m.rx || []" :key="r.e">
                                                <button type="button" class="sfk-m-reaction" :class="{ 'is-mine': r.mine }" @click="react(row.m, r.e)" :title="reactionTitle(r)"><span x-text="r.e"></span><span x-text="r.n"></span></button>
                                            </template>
                                        </div>
                                        <div class="sfk-m-hover" x-show="!row.m.pending && !row.m.unsent">
                                            <template x-for="emoji in quickReactions.slice(0, 3)" :key="emoji"><button type="button" @click="react(row.m, emoji)" x-text="emoji"></button></template>
                                            <button type="button" @click="reply(row.m)" title="返信"><x-filament::icon :icon="Heroicon::OutlinedArrowUturnLeft" class="sfk-m-ico" /></button>
                                            <button type="button" data-menu-trigger @click="openMenu(row.m, $event)" title="その他"><x-filament::icon :icon="Heroicon::OutlinedListBullet" class="sfk-m-ico" /></button>
                                        </div>
                                    </div>
                                    <button type="button" class="sfk-m-link" x-show="row.m.failed" @click="discardPending(row.m)">削除</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
                {{-- who read the newest message --}}
                <div class="sfk-m-seen" x-show="seenBy.length && shared" @click="messages.length && showReaders([...messages].reverse().find((m) => m.id && m.type !== 'system'))" title="既読">
                    <template x-for="uid in seenBy.slice(0, 7)" :key="'seen' + uid"><span class="sfk-m-avatar is-seen" :style="avatarStyle(uid)" :title="nameOf(uid)"><span x-text="initial(nameOf(uid))"></span></span></template>
                    <span class="sfk-m-seen-more" x-show="seenBy.length > 7" x-text="'+' + (seenBy.length - 7)"></span>
                </div>
            </div>

            <button type="button" class="sfk-m-to-bottom" x-show="!atBottom || newer" x-transition.opacity @click="scrollToBottom()" title="最新のメッセージへ">
                <x-filament::icon :icon="Heroicon::OutlinedChevronDown" class="sfk-m-ico" />
                <span class="sfk-m-unread" x-show="newBelow > 0" x-text="newBelow"></span>
            </button>

            {{-- composer --}}
            <footer class="sfk-m-composer" x-show="conv">
                <div class="sfk-m-readonly" x-show="conv && !conv.me.post">
                    <x-filament::icon :icon="Heroicon::OutlinedLockClosed" class="sfk-m-ico" />
                    <span x-text="conv?.archived ? 'このチャンネルはアーカイブされています' : 'このチャンネルは管理者のみ投稿できます'"></span>
                </div>
                <div x-show="conv && conv.me.post">
                    <div class="sfk-m-compose-banner" x-show="replyTo || editing">
                        <x-filament::icon :icon="Heroicon::OutlinedArrowUturnLeft" class="sfk-m-ico" x-show="replyTo" />
                        <x-filament::icon :icon="Heroicon::OutlinedPencil" class="sfk-m-ico" x-show="editing" />
                        <span class="sfk-m-compose-banner-text">
                            <strong x-text="editing ? 'メッセージを編集' : (replyTo ? nameOf(replyTo.user) + 'さんに返信' : '')"></strong>
                            <span x-text="replyTo ? replyTo.text : ''"></span>
                        </span>
                        <button type="button" class="sfk-m-icon-btn" @click="cancelCompose()" title="キャンセル"><x-filament::icon :icon="Heroicon::OutlinedXMark" class="sfk-m-ico" /></button>
                    </div>
                    <div class="sfk-m-compose-banner is-prio" x-show="priority > 0 && !editing">
                        <span class="sfk-m-prio" :class="'is-' + priority" x-text="priorityLabel(priority)"></span>
                        <span class="sfk-m-compose-banner-text"><span x-text="priority === 2 ? '至急として送信（通知オフでも届きます）' : '重要として送信'"></span></span>
                        <button type="button" class="sfk-m-icon-btn" @click="priority = 0" title="通常に戻す"><x-filament::icon :icon="Heroicon::OutlinedXMark" class="sfk-m-ico" /></button>
                    </div>

                    <div class="sfk-m-uploads" x-show="uploads.length">
                        <template x-for="u in uploads" :key="u.key">
                            <div class="sfk-m-upload" :class="{ 'has-error': u.error }">
                                <template x-if="u.kind === 'image' && u.preview"><img :src="u.preview" alt=""></template>
                                <template x-if="u.kind !== 'image' || !u.preview"><span class="sfk-m-file-icon" :class="fileIcon(u.name).cls" x-text="fileIcon(u.name).label"></span></template>
                                <span class="sfk-m-upload-name" x-text="u.error || u.name" :title="u.name"></span>
                                <span class="sfk-m-progress" x-show="!u.id && !u.error"><span :style="{ width: u.progress + '%' }"></span></span>
                                <button type="button" class="sfk-m-upload-x" @click="removeUpload(u)" title="取り消す"><x-filament::icon :icon="Heroicon::XMark" class="sfk-m-ico" /></button>
                            </div>
                        </template>
                    </div>

                    <div class="sfk-m-recorder" x-show="recording || voice">
                        <template x-if="recording">
                            <div class="sfk-m-recording">
                                <span class="sfk-m-rec-dot"></span><span x-text="formatDuration(recording.elapsed)"></span><span class="sfk-m-grow"></span>
                                <button type="button" class="sfk-m-btn" @click="stopRecording(true)">キャンセル</button>
                                <button type="button" class="sfk-m-btn is-primary" @click="stopRecording()">停止</button>
                            </div>
                        </template>
                        <template x-if="voice">
                            <div class="sfk-m-recording">
                                <audio :src="voice.url" controls preload="metadata"></audio><span class="sfk-m-grow"></span>
                                <button type="button" class="sfk-m-btn" @click="discardVoice()">削除</button>
                                <button type="button" class="sfk-m-btn is-primary" @click="sendVoice()">送信</button>
                            </div>
                        </template>
                    </div>

                    <div class="sfk-m-compose" x-show="!recording && !voice">
                        <div class="sfk-m-input-wrap" :class="{ 'is-expanded': expanded }">
                            <button type="button" class="sfk-m-icon-btn sfk-m-emoji-btn" :class="{ 'is-on': picker === 'emoji' }" @click="togglePicker('emoji')" title="絵文字"><x-filament::icon :icon="Heroicon::OutlinedFaceSmile" class="sfk-m-ico" /></button>
                            <textarea x-ref="input" rows="1" x-model="text" :maxlength="policy.maxLength" placeholder="メッセージを入力" aria-label="メッセージ"
                                @input="onInput($event)" @keydown="onKeydown($event)" @paste="onPaste($event)" @blur="stopTyping()" enterkeyhint="enter"></textarea>
                            <button type="button" class="sfk-m-icon-btn sfk-m-expand-btn" @click="expanded = !expanded; $nextTick(() => $refs.input.focus())" :title="expanded ? '縮小' : '拡大'">
                                <x-filament::icon :icon="Heroicon::OutlinedArrowsPointingOut" class="sfk-m-ico" x-show="!expanded" />
                                <x-filament::icon :icon="Heroicon::OutlinedArrowsPointingIn" class="sfk-m-ico" x-show="expanded" />
                            </button>
                            <div class="sfk-m-suggest" x-show="suggest.open">
                                <template x-for="(item, index) in suggest.items" :key="item.id">
                                    <button type="button" :class="{ 'is-active': index === suggest.index }" @mousedown.prevent="chooseMention(item)">
                                        <span class="sfk-m-avatar is-xs" :style="item.id === 'all' ? {} : avatarStyle(item.id)"><span x-text="item.id === 'all' ? '@' : initial(item.name)"></span></span>
                                        <span x-text="item.name"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <button type="button" class="sfk-m-icon-btn" @click="chooseFiles(false)" title="ファイルを添付"><x-filament::icon :icon="Heroicon::OutlinedPaperClip" class="sfk-m-ico" /></button>
                        <button type="button" class="sfk-m-icon-btn" :class="{ 'is-on': picker === 'more' }" @click="togglePicker('more')" title="その他"><x-filament::icon :icon="Heroicon::OutlinedPlus" class="sfk-m-ico" /></button>
                        <button type="button" class="sfk-m-send" x-show="text.trim() || uploads.length || editing" :disabled="!editing && !canSend" @click="submit()" :title="editing ? '保存' : '送信'">
                            <x-filament::icon :icon="Heroicon::PaperAirplane" class="sfk-m-ico" x-show="!editing" />
                            <x-filament::icon :icon="Heroicon::Check" class="sfk-m-ico" x-show="editing" />
                        </button>
                        <button type="button" class="sfk-m-icon-btn sfk-m-mic" x-show="!text.trim() && !uploads.length && !editing && canRecord" @click="startRecording()" title="ボイスメッセージ">
                            <x-filament::icon :icon="Heroicon::OutlinedMicrophone" class="sfk-m-ico" />
                        </button>
                    </div>

                    {{-- ＋ --}}
                    <div class="sfk-m-picker is-more" x-show="picker === 'more'" @click.outside="if (!$event.target.closest('[title=その他]')) picker = null">
                        <button type="button" @click="picker = null; chooseFiles(true)"><x-filament::icon :icon="Heroicon::OutlinedPhoto" class="sfk-m-ico" />画像</button>
                        <button type="button" @click="picker = null; chooseFiles(false)"><x-filament::icon :icon="Heroicon::OutlinedDocument" class="sfk-m-ico" />ファイル</button>
                        <button type="button" @click="togglePicker('sticker')"><x-filament::icon :icon="Heroicon::OutlinedFaceSmile" class="sfk-m-ico" />スタンプ</button>
                        <button type="button" @click="openRecordShare()"><x-filament::icon :icon="Heroicon::OutlinedRectangleStack" class="sfk-m-ico" />ERPレコード</button>
                        <button type="button" @click="picker = null; priority = 1"><span class="sfk-m-prio is-1">重要</span>重要として送信</button>
                        <button type="button" @click="picker = null; priority = 2"><span class="sfk-m-prio is-2">至急</span>至急として送信</button>
                    </div>
                    <div class="sfk-m-picker" x-show="picker === 'emoji'" @click.outside="if (!$event.target.closest('.sfk-m-emoji-btn')) picker = null">
                        <div class="sfk-m-picker-tabs">
                            <template x-for="tab in emojiTabs()" :key="tab"><button type="button" :class="{ 'is-active': emojiTab === tab }" @click="emojiTab = tab" x-text="tab"></button></template>
                        </div>
                        <div class="sfk-m-emoji-grid">
                            <template x-for="emoji in emojiList(emojiTab)" :key="emoji"><button type="button" @click="pickEmoji(emoji)" x-text="emoji"></button></template>
                        </div>
                    </div>
                    <div class="sfk-m-picker is-stickers" x-show="picker === 'sticker'" @click.outside="if (!$event.target.closest('.sfk-m-picker')) picker = null">
                        <div class="sfk-m-sticker-grid">
                            <template x-for="s in stickers" :key="s.key">
                                <div class="sfk-m-sticker-pick">
                                    <button type="button" @click="sendSticker(s.key)" :title="s.text || s.name">
                                        <template x-if="s.url"><img :src="s.url" :alt="s.name" loading="lazy"></template>
                                        <template x-if="!s.url"><span class="sfk-m-sticker-card is-sm"><span class="sfk-m-sticker-emoji" x-text="s.emoji"></span><span class="sfk-m-sticker-text" x-text="s.text"></span></span></template>
                                    </button>
                                    <button type="button" class="sfk-m-sticker-x" x-show="me.stickers && s.url" @click="removeSticker(s)" title="削除"><x-filament::icon :icon="Heroicon::XMark" class="sfk-m-ico" /></button>
                                </div>
                            </template>
                            <label class="sfk-m-sticker-add" x-show="me.stickers" title="スタンプを追加">
                                <x-filament::icon :icon="Heroicon::OutlinedPlus" class="sfk-m-ico" />
                                <input type="file" accept="image/png,image/webp,image/gif,image/jpeg" class="sfk-m-hidden" @change="addSticker($event)">
                            </label>
                        </div>
                    </div>
                    <input type="file" x-ref="files" multiple class="sfk-m-hidden" @change="addFiles($event.target.files)" accept=".pdf,.docx,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp,.gif">
                    <input type="file" x-ref="images" multiple class="sfk-m-hidden" @change="addFiles($event.target.files)" accept="image/jpeg,image/png,image/webp,image/gif">
                </div>
            </footer>
            <div class="sfk-m-drop" x-show="dragging"><x-filament::icon :icon="Heroicon::OutlinedArrowUpTray" class="sfk-m-ico" /><span>ドロップして添付</span></div>
        </section>
    </main>

    {{-- ===== details ===== --}}
    <aside class="sfk-m-details" x-show="activeId && detailsOpen" x-transition.opacity.duration.100ms>
        <header class="sfk-m-details-head">
            <strong x-text="isChannel ? 'チャンネル情報' : (shared ? 'グループ情報' : 'プロフィール')"></strong>
            <button type="button" class="sfk-m-icon-btn" @click="detailsOpen = false" title="閉じる"><x-filament::icon :icon="Heroicon::OutlinedXMark" class="sfk-m-ico" /></button>
        </header>
        <template x-if="!details"><div class="sfk-m-loading"><span></span><span></span><span></span></div></template>
        <template x-if="details && conv">
            <div class="sfk-m-details-body">
                <div class="sfk-m-profile">
                    <label class="sfk-m-avatar is-lg" :class="{ 'is-editable': canEditInfo, 'is-group': shared }" :style="conv.avatar || shared ? {} : avatarStyle(conv.profile?.user)">
                        <template x-if="conv.avatar"><img :src="conv.avatar" alt=""></template>
                        <template x-if="!conv.avatar && conv.type === 'group'"><x-filament::icon :icon="Heroicon::UserGroup" class="sfk-m-ico" /></template>
                        <template x-if="!conv.avatar && conv.type === 'channel'"><x-filament::icon :icon="Heroicon::Hashtag" class="sfk-m-ico" /></template>
                        <template x-if="!conv.avatar && !shared"><span x-text="initial(activeItem?.name)"></span></template>
                        <input type="file" accept="image/*" class="sfk-m-hidden" x-show="canEditInfo" :disabled="!canEditInfo" @change="uploadAvatar($event)">
                    </label>
                    <h2 x-text="activeItem?.name"></h2>
                    <p class="sfk-m-presence" x-show="!shared && presenceLine" x-text="presenceLine"></p>
                    <div class="sfk-m-ann-tags" x-show="isChannel">
                        <span class="sfk-m-tag" x-text="conv.settings?.kind === 'department' ? '部署チャンネル' : 'プロジェクトチャンネル'"></span>
                        <span class="sfk-m-tag" x-text="conv.settings?.public ? '公開' : '招待のみ'"></span>
                        <span class="sfk-m-tag" x-show="conv.settings?.post === 'admins'">管理者のみ投稿</span>
                        <span class="sfk-m-tag is-warning" x-show="conv.archived">アーカイブ</span>
                    </div>
                    <template x-if="conv.profile">
                        <dl class="sfk-m-profile-list">
                            <template x-if="conv.profile.kana"><div><dt>フリガナ</dt><dd x-text="conv.profile.kana"></dd></div></template>
                            <template x-if="conv.profile.position"><div><dt>役職</dt><dd x-text="conv.profile.position"></dd></div></template>
                            <template x-if="conv.profile.categories"><div><dt>従業員カテゴリ</dt><dd x-text="conv.profile.categories.join('・')"></dd></div></template>
                            <template x-if="conv.profile.email"><div><dt>メール</dt><dd x-text="conv.profile.email"></dd></div></template>
                            <template x-if="conv.profile.phone"><div><dt>電話</dt><dd x-text="conv.profile.phone"></dd></div></template>
                        </dl>
                    </template>
                    <p class="sfk-m-description" x-show="conv.description" x-text="conv.description"></p>
                    <button type="button" class="sfk-m-btn" x-show="canEditInfo" @click="openDialog('info-edit', { name: conv.name, description: conv.description || '' })" x-text="isChannel ? 'チャンネル情報を編集' : 'グループ情報を編集'"></button>
                </div>

                <div class="sfk-m-quick">
                    <button type="button" @click="setState(activeItem, 'pinned', !activeItem.pinned)"><x-filament::icon :icon="Heroicon::OutlinedMapPin" class="sfk-m-ico" /><span x-text="activeItem?.pinned ? 'ピン解除' : 'ピン留め'"></span></button>
                    <button type="button" @click="setState(activeItem, 'favorite', !activeItem.favorite)"><x-filament::icon :icon="Heroicon::OutlinedStar" class="sfk-m-ico" /><span x-text="activeItem?.favorite ? 'お気に入り解除' : 'お気に入り'"></span></button>
                    <button type="button" @click="openSearch(true)"><x-filament::icon :icon="Heroicon::OutlinedMagnifyingGlass" class="sfk-m-ico" /><span>トーク内検索</span></button>
                    <button type="button" @click="openDialog('date', { date: '' })"><x-filament::icon :icon="Heroicon::OutlinedCalendarDays" class="sfk-m-ico" /><span>日付へ移動</span></button>
                </div>

                <section class="sfk-m-notify">
                    <span>通知</span>
                    <select @change="setLevel(activeItem, $event.target.value)">
                        <option value="all" :selected="conv.me.level === 'all'">すべてのメッセージ</option>
                        <option value="mentions" :selected="conv.me.level === 'mentions'">メンション・返信・重要のみ</option>
                        <option value="none" :selected="conv.me.level === 'none'">通知しない</option>
                    </select>
                </section>

                <nav class="sfk-m-tabs">
                    <button type="button" :class="{ 'is-active': detailsTab === 'info' }" @click="detailsTab = 'info'" x-text="shared ? 'メンバー' : 'ピン'"></button>
                    <button type="button" :class="{ 'is-active': detailsTab === 'pins' }" @click="detailsTab = 'pins'" x-show="shared">ピン</button>
                    <button type="button" :class="{ 'is-active': detailsTab === 'media' }" @click="detailsTab = 'media'">写真</button>
                    <button type="button" :class="{ 'is-active': detailsTab === 'files' }" @click="detailsTab = 'files'">ファイル</button>
                    <button type="button" :class="{ 'is-active': detailsTab === 'links' }" @click="detailsTab = 'links'; links.loaded || loadLinks()">リンク</button>
                    <button type="button" :class="{ 'is-active': detailsTab === 'settings' }" @click="detailsTab = 'settings'" x-show="shared && isAdmin">設定</button>
                </nav>

                <section x-show="detailsTab === 'info' && shared" class="sfk-m-members">
                    <button type="button" class="sfk-m-member is-add" x-show="canAddMembers && !conv.archived" @click="openDialog('members')">
                        <span class="sfk-m-avatar is-sm is-plain"><x-filament::icon :icon="Heroicon::OutlinedUserPlus" class="sfk-m-ico" /></span><span>メンバーを追加</span>
                    </button>
                    <template x-for="p in memberRows" :key="p.id">
                        <div class="sfk-m-member">
                            <span class="sfk-m-avatar is-sm" :style="avatarStyle(p.id)"><span x-text="initial(p.name)"></span></span>
                            <span class="sfk-m-member-text"><strong x-text="p.name + (p.id === me.id ? '（あなた）' : '')"></strong><small x-text="[p.position, ...(p.categories || [])].filter(Boolean).join('・')"></small></span>
                            <span class="sfk-m-tag" x-show="p.role === 'admin'">管理者</span>
                            <div class="sfk-m-member-actions" x-show="isAdmin && p.id !== me.id" x-data="{ open: false }" @click.outside="open = false">
                                <button type="button" class="sfk-m-icon-btn" @click="open = !open" title="操作"><x-filament::icon :icon="Heroicon::OutlinedEllipsisHorizontal" class="sfk-m-ico" /></button>
                                <div class="sfk-m-pop" x-show="open">
                                    <button type="button" x-show="p.role !== 'admin'" @click="open = false; setRole(p.id, 'admin')">管理者にする</button>
                                    <button type="button" x-show="p.role === 'admin'" @click="open = false; setRole(p.id, 'member')">管理者から外す</button>
                                    <button type="button" class="is-danger" @click="open = false; removeMember(p.id)" x-text="isChannel ? 'チャンネルから削除' : 'グループから削除'"></button>
                                </div>
                            </div>
                        </div>
                    </template>
                    <button type="button" class="sfk-m-btn is-danger-text sfk-m-leave" @click="leave()" x-text="isChannel ? 'チャンネルを退出' : 'グループを退出'"></button>
                </section>

                <section x-show="detailsTab === 'pins' || (detailsTab === 'info' && !shared)" class="sfk-m-pins">
                    <template x-for="p in details.pins" :key="p.id">
                        <button type="button" class="sfk-m-pin" @click="jumpTo(p.id)">
                            <strong x-text="nameOf(p.user)"></strong>
                            <span x-text="p.body || (p.att?.[0]?.name) || (p.record ? p.record.app : '') || 'スタンプ'"></span>
                            <time x-text="fullTime(p.at)"></time>
                        </button>
                    </template>
                </section>
                <section x-show="detailsTab === 'media'" class="sfk-m-media">
                    <template x-for="(img, index) in details.media" :key="img.id"><button type="button" @click="openImage(details.media, index)"><img :src="img.url" alt="" loading="lazy"></button></template>
                </section>
                <section x-show="detailsTab === 'files'" class="sfk-m-shared-files">
                    <template x-for="f in details.files" :key="f.id">
                        <a class="sfk-m-file" :href="f.url + '?download=1'">
                            <span class="sfk-m-file-icon" :class="fileIcon(f.name).cls" x-text="fileIcon(f.name).label"></span>
                            <span class="sfk-m-file-text"><span class="sfk-m-file-name" x-text="f.name"></span><span class="sfk-m-file-size" x-text="formatSize(f.size) + '・' + nameOf(f.user) + '・' + listTime(f.at)"></span></span>
                        </a>
                    </template>
                </section>
                <section x-show="detailsTab === 'links'" class="sfk-m-links">
                    <template x-for="(l, i) in links.items" :key="l.message + '-' + i">
                        <div class="sfk-m-link-row">
                            <a :href="l.url" target="_blank" rel="noopener noreferrer nofollow"><strong x-text="l.host"></strong><span x-text="l.url"></span></a>
                            <button type="button" @click="jumpTo(l.message)"><small x-text="nameOf(l.user) + '・' + listTime(l.at)"></small></button>
                        </div>
                    </template>
                    <button type="button" class="sfk-m-btn sfk-m-more" x-show="links.more" @click="loadLinks(true)">さらに表示</button>
                </section>
                <section x-show="detailsTab === 'settings'" class="sfk-m-settings">
                    <template x-for="[key, label] in (isChannel ? [['post', '投稿'], ['add_members', 'メンバーの追加'], ['edit_info', 'チャンネル情報の変更'], ['pin_messages', 'メッセージのピン留め'], ['mention_all', '@全員']] : [['add_members', 'メンバーの追加'], ['edit_info', 'グループ情報の変更'], ['pin_messages', 'メッセージのピン留め'], ['mention_all', '@全員']])" :key="key">
                        <label class="sfk-m-setting">
                            <span x-text="label"></span>
                            <select :value="conv.settings?.[key]" @change="saveSettings(key, $event.target.value)">
                                <option value="everyone">全メンバー</option>
                                <option value="admins">管理者のみ</option>
                            </select>
                        </label>
                    </template>
                    <label class="sfk-m-setting" x-show="isChannel"><span>公開（誰でも参加できる）</span><input type="checkbox" class="sfk-ui-check" :checked="conv.settings?.public" @change="saveSettings('public', $event.target.checked)"></label>
                    <label class="sfk-m-setting"><span>新しいメンバーに過去のメッセージを表示</span><input type="checkbox" class="sfk-ui-check" :checked="conv.settings?.share_history" @change="saveSettings('share_history', $event.target.checked)"></label>
                    <button type="button" class="sfk-m-btn" x-show="isChannel" @click="archiveChannel(!conv.archived)" x-text="conv.archived ? 'チャンネルを再開' : 'チャンネルをアーカイブ'"></button>
                </section>
            </div>
        </template>
    </aside>

    {{-- ===== menus ===== --}}
    <div class="sfk-m-menu" x-show="menu && !menu.sheet" x-transition.opacity.duration.80ms :style="menu && !menu.sheet ? (menu.up ? { left: menu.x + 'px', bottom: (innerHeight - menu.y) + 'px' } : { left: menu.x + 'px', top: menu.y + 'px' }) : {}">
        <template x-if="menu?.m">
            <div>
                <div class="sfk-m-menu-reactions">
                    <template x-for="emoji in quickReactions" :key="emoji"><button type="button" @click="react(menu.m, emoji)" x-text="emoji"></button></template>
                </div>
                <button type="button" @click="reply(menu.m)"><x-filament::icon :icon="Heroicon::OutlinedArrowUturnLeft" class="sfk-m-ico" />返信</button>
                <button type="button" x-show="menu.m.body" @click="copy(menu.m)"><x-filament::icon :icon="Heroicon::OutlinedClipboard" class="sfk-m-ico" />コピー</button>
                <button type="button" @click="forward(menu.m)"><x-filament::icon :icon="Heroicon::OutlinedArrowUturnRight" class="sfk-m-ico" />転送</button>
                <button type="button" x-show="canPin" @click="pinMessage(menu.m, !menu.m.pinned)"><x-filament::icon :icon="Heroicon::OutlinedBookmark" class="sfk-m-ico" /><span x-text="menu.m.pinned ? 'ピン留めを外す' : 'ピン留め'"></span></button>
                <button type="button" @click="showInfo(menu.m)"><x-filament::icon :icon="Heroicon::OutlinedInformationCircle" class="sfk-m-ico" />メッセージ情報</button>
                <button type="button" x-show="canEdit(menu.m)" @click="startEdit(menu.m)"><x-filament::icon :icon="Heroicon::OutlinedPencil" class="sfk-m-ico" />編集</button>
                <button type="button" x-show="canUnsend(menu.m)" class="is-danger" @click="unsend(menu.m)"><x-filament::icon :icon="Heroicon::OutlinedNoSymbol" class="sfk-m-ico" />送信取消</button>
                <button type="button" x-show="conv?.me?.moderate && menu.m.user !== me.id" class="is-danger" @click="moderate(menu.m)"><x-filament::icon :icon="Heroicon::OutlinedShieldExclamation" class="sfk-m-ico" />管理者として削除</button>
                <button type="button" class="is-danger" @click="hideMessage(menu.m)"><x-filament::icon :icon="Heroicon::OutlinedTrash" class="sfk-m-ico" />自分のみ削除</button>
            </div>
        </template>
        <template x-if="menu?.conv">
            <div>
                <button type="button" @click="markUnread(menu.conv)"><x-filament::icon :icon="Heroicon::OutlinedEnvelope" class="sfk-m-ico" />未読にする</button>
                <button type="button" @click="setState(menu.conv, 'pinned', !menu.conv.pinned)"><x-filament::icon :icon="Heroicon::OutlinedMapPin" class="sfk-m-ico" /><span x-text="menu.conv.pinned ? 'ピン留めを外す' : 'ピン留め'"></span></button>
                <button type="button" @click="setState(menu.conv, 'favorite', !menu.conv.favorite)"><x-filament::icon :icon="Heroicon::OutlinedStar" class="sfk-m-ico" /><span x-text="menu.conv.favorite ? 'お気に入りから外す' : 'お気に入りに追加'"></span></button>
                <button type="button" @click="setState(menu.conv, 'muted', !menu.conv.muted)"><x-filament::icon :icon="Heroicon::OutlinedBellSlash" class="sfk-m-ico" /><span x-text="menu.conv.muted ? '通知をオン' : '通知をオフ'"></span></button>
                <button type="button" @click="setState(menu.conv, 'archived', !menu.conv.archived)"><x-filament::icon :icon="Heroicon::OutlinedArchiveBox" class="sfk-m-ico" /><span x-text="menu.conv.archived ? 'アーカイブから戻す' : 'アーカイブ'"></span></button>
                <button type="button" x-show="menu.conv.id === activeId" @click="menu = null; openDialog('date', { date: '' })"><x-filament::icon :icon="Heroicon::OutlinedCalendarDays" class="sfk-m-ico" />日付へ移動</button>
                <button type="button" class="is-danger" @click="setState(menu.conv, 'hidden', true)"><x-filament::icon :icon="Heroicon::OutlinedTrash" class="sfk-m-ico" />トークを削除</button>
            </div>
        </template>
    </div>

    <div class="sfk-m-sheet-backdrop" x-show="menu?.sheet" x-transition.opacity @click.self="menu = null">
        <div class="sfk-m-sheet" x-show="menu?.sheet" x-transition:enter-start="is-off" x-transition:leave-end="is-off">
            <template x-if="menu?.sheet && menu.m">
                <div>
                    <div class="sfk-m-menu-reactions">
                        <template x-for="emoji in quickReactions" :key="emoji"><button type="button" @click="react(menu.m, emoji)" x-text="emoji"></button></template>
                    </div>
                    <button type="button" @click="reply(menu.m)">返信</button>
                    <button type="button" x-show="menu.m.body" @click="copy(menu.m)">コピー</button>
                    <button type="button" @click="forward(menu.m)">転送</button>
                    <button type="button" x-show="canPin" @click="pinMessage(menu.m, !menu.m.pinned)" x-text="menu.m.pinned ? 'ピン留めを外す' : 'ピン留め'"></button>
                    <button type="button" @click="showInfo(menu.m)">メッセージ情報</button>
                    <button type="button" x-show="canEdit(menu.m)" @click="startEdit(menu.m)">編集</button>
                    <button type="button" x-show="canUnsend(menu.m)" class="is-danger" @click="unsend(menu.m)">送信取消</button>
                    <button type="button" x-show="conv?.me?.moderate && menu.m.user !== me.id" class="is-danger" @click="moderate(menu.m)">管理者として削除</button>
                    <button type="button" class="is-danger" @click="hideMessage(menu.m)">自分のみ削除</button>
                    <button type="button" class="is-cancel" @click="menu = null">キャンセル</button>
                </div>
            </template>
        </div>
    </div>

    {{-- ===== dialogs ===== --}}
    <div class="sfk-m-backdrop" x-show="dialog" x-transition.opacity.duration.100ms @click.self="dialog = null">
        <div class="sfk-m-dialog" role="dialog" aria-modal="true" :class="'is-' + dialog">

            {{-- 社員名簿 / new direct conversation / add members --}}
            <template x-if="dialog === 'direct' || dialog === 'members'">
                <div class="sfk-m-dialog-inner">
                    <header><strong x-text="dialog === 'direct' ? '社員名簿' : 'メンバーを追加'"></strong><button type="button" class="sfk-m-icon-btn" @click="dialog = null" title="閉じる"><x-filament::icon :icon="Heroicon::OutlinedXMark" class="sfk-m-ico" /></button></header>
                    <div class="sfk-m-search is-dialog"><x-filament::icon :icon="Heroicon::OutlinedMagnifyingGlass" class="sfk-m-ico" /><input type="search" x-model="pick.query" placeholder="名前・フリガナ・部署で検索" x-init="$nextTick(() => $el.focus())"></div>
                    <div class="sfk-m-chips" x-show="dialog === 'direct' && categories.length">
                        <template x-for="c in categories" :key="c.id"><button type="button" class="sfk-m-chip" :class="{ 'is-active': pick.query === c.name }" @click="pick.query = pick.query === c.name ? '' : c.name" x-text="c.name"></button></template>
                    </div>
                    <div class="sfk-m-people">
                        <template x-for="p in peopleMatching(pick.query, dialog === 'members' ? reads.map((r) => r.user) : [])" :key="p.id">
                            <button type="button" class="sfk-m-person" :class="{ 'is-selected': pick.selected.includes(p.id) }" @click="dialog === 'direct' ? startDirect(p.id) : togglePick(p.id)">
                                <span class="sfk-m-avatar is-sm" :style="avatarStyle(p.id)"><span x-text="initial(p.name)"></span></span>
                                <span class="sfk-m-member-text"><strong x-text="p.name"></strong><small x-text="[p.position, ...(p.categories || [])].filter(Boolean).join('・')"></small></span>
                                <span class="sfk-m-person-go" x-show="dialog === 'direct'"><x-filament::icon :icon="Heroicon::OutlinedChatBubbleOvalLeft" class="sfk-m-ico" /></span>
                                <span class="sfk-m-check" x-show="dialog !== 'direct'"><x-filament::icon :icon="Heroicon::Check" class="sfk-m-ico" /></span>
                            </button>
                        </template>
                    </div>
                    <footer x-show="dialog === 'members'">
                        <span x-text="pick.selected.length + '人を選択'"></span>
                        <button type="button" class="sfk-m-btn" @click="dialog = null">キャンセル</button>
                        <button type="button" class="sfk-m-btn is-primary" :disabled="!pick.selected.length" @click="addMembers()">追加</button>
                    </footer>
                </div>
            </template>

            {{-- new group / channel --}}
            <template x-if="dialog === 'group' || dialog === 'channel'">
                <div class="sfk-m-dialog-inner">
                    <header><strong x-text="dialog === 'group' ? 'グループを作成' : 'チャンネルを作成'"></strong><button type="button" class="sfk-m-icon-btn" @click="dialog = null" title="閉じる"><x-filament::icon :icon="Heroicon::OutlinedXMark" class="sfk-m-ico" /></button></header>
                    <div class="sfk-m-form is-scroll">
                        <label><span x-text="dialog === 'group' ? 'グループ名' : 'チャンネル名'"></span><input type="text" x-model="form.name" maxlength="100" x-init="$nextTick(() => $el.focus())"></label>
                        <label><span>説明</span><textarea x-model="form.description" rows="2" maxlength="1000"></textarea></label>
                        <template x-if="dialog === 'channel'">
                            <div class="sfk-m-form">
                                <div class="sfk-m-field"><span>種類</span><div class="sfk-m-radios"><label><input type="radio" class="sfk-ui-radio" value="department" x-model="form.kind"><span>部署</span></label><label><input type="radio" class="sfk-ui-radio" value="project" x-model="form.kind"><span>プロジェクト</span></label></div></div>
                                <div class="sfk-m-field"><span>投稿</span><div class="sfk-m-radios"><label><input type="radio" class="sfk-ui-radio" value="everyone" x-model="form.post"><span>全メンバー</span></label><label><input type="radio" class="sfk-ui-radio" value="admins" x-model="form.post"><span>管理者のみ（お知らせ用）</span></label></div></div>
                                <label class="sfk-m-inline-check"><input type="checkbox" class="sfk-ui-check" x-model="form.public"><span>公開（社員が探して参加できる）</span></label>
                                <div class="sfk-m-field"><span>従業員カテゴリのメンバーを追加</span>
                                    <div class="sfk-m-chips">
                                        <template x-for="c in categories" :key="c.id"><button type="button" class="sfk-m-chip" :class="{ 'is-active': form.categories.includes(c.id) }" @click="form.categories.includes(c.id) ? form.categories.splice(form.categories.indexOf(c.id), 1) : form.categories.push(c.id)" x-text="c.name"></button></template>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <div class="sfk-m-chips" x-show="pick.selected.length">
                            <template x-for="id in pick.selected" :key="id"><button type="button" class="sfk-m-chip is-active" @click="togglePick(id)"><span x-text="nameOf(id)"></span><x-filament::icon :icon="Heroicon::XMark" class="sfk-m-ico" /></button></template>
                        </div>
                        <div class="sfk-m-search is-dialog"><x-filament::icon :icon="Heroicon::OutlinedMagnifyingGlass" class="sfk-m-ico" /><input type="search" x-model="pick.query" placeholder="メンバーを検索"></div>
                        <div class="sfk-m-people is-short">
                            <template x-for="p in peopleMatching(pick.query)" :key="p.id">
                                <button type="button" class="sfk-m-person" :class="{ 'is-selected': pick.selected.includes(p.id) }" @click="togglePick(p.id)">
                                    <span class="sfk-m-avatar is-sm" :style="avatarStyle(p.id)"><span x-text="initial(p.name)"></span></span>
                                    <span class="sfk-m-member-text"><strong x-text="p.name"></strong><small x-text="[p.position, ...(p.categories || [])].filter(Boolean).join('・')"></small></span>
                                    <span class="sfk-m-check"><x-filament::icon :icon="Heroicon::Check" class="sfk-m-ico" /></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <footer>
                        <span x-text="pick.selected.length + '人を選択'"></span>
                        <button type="button" class="sfk-m-btn" @click="dialog = null">キャンセル</button>
                        <button type="button" class="sfk-m-btn is-primary" :disabled="!form.name?.trim()" @click="dialog === 'group' ? createGroup() : createChannel()">作成</button>
                    </footer>
                </div>
            </template>

            {{-- チャンネルを探す --}}
            <template x-if="dialog === 'channels'">
                <div class="sfk-m-dialog-inner">
                    <header><strong>チャンネルを探す</strong><button type="button" class="sfk-m-icon-btn" @click="dialog = null" title="閉じる"><x-filament::icon :icon="Heroicon::OutlinedXMark" class="sfk-m-ico" /></button></header>
                    <div class="sfk-m-search is-dialog"><x-filament::icon :icon="Heroicon::OutlinedMagnifyingGlass" class="sfk-m-ico" /><input type="search" x-model="pick.query" placeholder="チャンネル名で検索"></div>
                    <div class="sfk-m-people">
                        <div class="sfk-m-loading" x-show="channelList === null"><span></span><span></span><span></span></div>
                        <template x-for="ch in (channelList || []).filter((c) => !pick.query.trim() || c.name.toLowerCase().includes(pick.query.trim().toLowerCase()))" :key="ch.id">
                            <div class="sfk-m-person">
                                <span class="sfk-m-avatar is-sm is-group"><x-filament::icon :icon="Heroicon::Hashtag" class="sfk-m-ico" /></span>
                                <span class="sfk-m-member-text"><strong x-text="ch.name"></strong><small x-text="(ch.kind === 'department' ? '部署' : 'プロジェクト') + '・メンバー ' + ch.members + '人' + (ch.readonly ? '・管理者のみ投稿' : '') + (ch.description ? '・' + ch.description : '')"></small></span>
                                <button type="button" class="sfk-m-btn" :class="{ 'is-primary': !ch.joined }" @click="ch.joined ? (dialog = null, openConversation(ch.id)) : joinChannel(ch)" x-text="ch.joined ? '開く' : '参加'"></button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            {{-- group / channel info --}}
            <template x-if="dialog === 'info-edit'">
                <div class="sfk-m-dialog-inner is-small">
                    <header><strong x-text="isChannel ? 'チャンネル情報' : 'グループ情報'"></strong><button type="button" class="sfk-m-icon-btn" @click="dialog = null" title="閉じる"><x-filament::icon :icon="Heroicon::OutlinedXMark" class="sfk-m-ico" /></button></header>
                    <div class="sfk-m-form">
                        <label><span>名前</span><input type="text" x-model="form.name" maxlength="100"></label>
                        <label><span>説明</span><textarea x-model="form.description" rows="3" maxlength="1000"></textarea></label>
                    </div>
                    <footer>
                        <button type="button" class="sfk-m-btn" @click="dialog = null">キャンセル</button>
                        <button type="button" class="sfk-m-btn is-primary" @click="saveInfo()">保存</button>
                    </footer>
                </div>
            </template>

            {{-- ERPレコードを共有 --}}
            <template x-if="dialog === 'record'">
                <div class="sfk-m-dialog-inner">
                    <header><strong>ERPレコードを共有</strong><button type="button" class="sfk-m-icon-btn" @click="dialog = null" title="閉じる"><x-filament::icon :icon="Heroicon::OutlinedXMark" class="sfk-m-ico" /></button></header>
                    <div class="sfk-m-record-pick">
                        <select x-model="records.app" @change="searchRecords()" aria-label="アプリ">
                            <template x-for="a in records.apps || []" :key="a.code"><option :value="a.code" x-text="a.name"></option></template>
                        </select>
                        <div class="sfk-m-search is-dialog"><x-filament::icon :icon="Heroicon::OutlinedMagnifyingGlass" class="sfk-m-ico" /><input type="search" x-model="records.q" @input.debounce.350ms="searchRecords()" placeholder="番号・名称で検索"></div>
                    </div>
                    <div class="sfk-m-people">
                        <div class="sfk-m-loading" x-show="records.loading"><span></span><span></span><span></span></div>
                        <template x-for="r in records.results" :key="r.id">
                            <button type="button" class="sfk-m-person" @click="shareRecord(r)">
                                <span class="sfk-m-avatar is-sm is-plain"><x-filament::icon :icon="Heroicon::OutlinedRectangleStack" class="sfk-m-ico" /></span>
                                <span class="sfk-m-member-text"><strong x-text="r.title"></strong><small x-text="r.detail"></small></span>
                            </button>
                        </template>
                        <p class="sfk-m-none" x-show="!records.loading && records.apps !== null && !records.results.length">見つかりませんでした</p>
                    </div>
                </div>
            </template>

            {{-- メッセージ情報 --}}
            <template x-if="dialog === 'info'">
                <div class="sfk-m-dialog-inner is-small">
                    <header><strong>メッセージ情報</strong><button type="button" class="sfk-m-icon-btn" @click="dialog = null" title="閉じる"><x-filament::icon :icon="Heroicon::OutlinedXMark" class="sfk-m-ico" /></button></header>
                    <div class="sfk-m-loading" x-show="!info"><span></span><span></span><span></span></div>
                    <template x-if="info">
                        <div class="sfk-m-readers">
                            <dl class="sfk-m-profile-list">
                                <div><dt>送信者</dt><dd x-text="nameOf(info.user)"></dd></div>
                                <div><dt>送信日時</dt><dd x-text="fullTime(info.at)"></dd></div>
                                <div x-show="info.edited"><dt>最終編集</dt><dd x-text="info.edited ? fullTime(info.edited) + '（' + info.edits + '回）' : ''"></dd></div>
                                <div x-show="info.priority > 0"><dt>重要度</dt><dd x-text="priorityLabel(info.priority)"></dd></div>
                            </dl>
                            <template x-if="info.mine">
                                <div>
                                    <h3 x-show="policy.receipts" x-text="'既読 ' + info.read.length"></h3>
                                    <template x-for="id in info.read" :key="'r' + id"><div class="sfk-m-member"><span class="sfk-m-avatar is-sm" :style="avatarStyle(id)"><span x-text="initial(nameOf(id))"></span></span><strong x-text="nameOf(id)"></strong></div></template>
                                    <h3 x-text="(policy.receipts ? '配信済み・未読 ' : '配信済み ') + info.delivered.length"></h3>
                                    <template x-for="id in info.delivered" :key="'d' + id"><div class="sfk-m-member is-muted"><span class="sfk-m-avatar is-sm" :style="avatarStyle(id)"><span x-text="initial(nameOf(id))"></span></span><strong x-text="nameOf(id)"></strong></div></template>
                                    <h3 x-text="'未配信 ' + info.waiting.length"></h3>
                                    <template x-for="id in info.waiting" :key="'w' + id"><div class="sfk-m-member is-muted"><span class="sfk-m-avatar is-sm" :style="avatarStyle(id)"><span x-text="initial(nameOf(id))"></span></span><strong x-text="nameOf(id)"></strong></div></template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- 日付へ移動 --}}
            <template x-if="dialog === 'date'">
                <div class="sfk-m-dialog-inner is-small">
                    <header><strong>日付へ移動</strong><button type="button" class="sfk-m-icon-btn" @click="dialog = null" title="閉じる"><x-filament::icon :icon="Heroicon::OutlinedXMark" class="sfk-m-ico" /></button></header>
                    <div class="sfk-m-form"><label><span>日付</span><input type="date" x-model="form.date" x-init="$nextTick(() => $el.focus())" @keydown.enter="jumpToDate(form.date)"></label></div>
                    <footer>
                        <button type="button" class="sfk-m-btn" @click="dialog = null">キャンセル</button>
                        <button type="button" class="sfk-m-btn is-primary" :disabled="!form.date" @click="jumpToDate(form.date)">移動</button>
                    </footer>
                </div>
            </template>

            {{-- forward --}}
            <template x-if="dialog === 'forward'">
                <div class="sfk-m-dialog-inner">
                    <header><strong>転送</strong><button type="button" class="sfk-m-icon-btn" @click="dialog = null" title="閉じる"><x-filament::icon :icon="Heroicon::OutlinedXMark" class="sfk-m-ico" /></button></header>
                    <div class="sfk-m-search is-dialog"><x-filament::icon :icon="Heroicon::OutlinedMagnifyingGlass" class="sfk-m-ico" /><input type="search" x-model="pick.query" placeholder="トークを検索"></div>
                    <div class="sfk-m-people">
                        <template x-for="c in forwardTargets.filter((c) => !c.readonly)" :key="c.id">
                            <button type="button" class="sfk-m-person" :class="{ 'is-selected': pick.selected.includes(c.id) }" @click="pick.selected.includes(c.id) || pick.selected.length < 10 ? togglePick(c.id) : null">
                                <span class="sfk-m-avatar is-sm" :class="{ 'is-group': c.type !== 'direct' }" :style="c.avatar || c.type !== 'direct' ? {} : avatarStyle(c.peer)">
                                    <template x-if="c.avatar"><img :src="c.avatar" alt=""></template>
                                    <template x-if="!c.avatar"><span x-text="c.type === 'channel' ? '#' : initial(c.name)"></span></template>
                                </span>
                                <span class="sfk-m-member-text"><strong x-text="c.name"></strong></span>
                                <span class="sfk-m-check"><x-filament::icon :icon="Heroicon::Check" class="sfk-m-ico" /></span>
                            </button>
                        </template>
                    </div>
                    <footer>
                        <span x-text="pick.selected.length + '件を選択'"></span>
                        <button type="button" class="sfk-m-btn" @click="dialog = null">キャンセル</button>
                        <button type="button" class="sfk-m-btn is-primary" :disabled="!pick.selected.length" @click="sendForward()">転送</button>
                    </footer>
                </div>
            </template>

            {{-- readers --}}
            <template x-if="dialog === 'readers' && readers">
                <div class="sfk-m-dialog-inner is-small">
                    <header><strong>既読者</strong><button type="button" class="sfk-m-icon-btn" @click="dialog = null" title="閉じる"><x-filament::icon :icon="Heroicon::OutlinedXMark" class="sfk-m-ico" /></button></header>
                    <div class="sfk-m-readers">
                        <h3 x-text="'既読 ' + readers.read.length"></h3>
                        <template x-for="id in readers.read" :key="'r' + id"><div class="sfk-m-member"><span class="sfk-m-avatar is-sm" :style="avatarStyle(id)"><span x-text="initial(nameOf(id))"></span></span><strong x-text="nameOf(id)"></strong></div></template>
                        <h3 x-text="'未読 ' + readers.unread.length"></h3>
                        <template x-for="id in readers.unread" :key="'u' + id"><div class="sfk-m-member is-muted"><span class="sfk-m-avatar is-sm" :style="avatarStyle(id)"><span x-text="initial(nameOf(id))"></span></span><strong x-text="nameOf(id)"></strong></div></template>
                    </div>
                </div>
            </template>

            {{-- search --}}
            <template x-if="dialog === 'search'">
                <div class="sfk-m-dialog-inner is-search">
                    <header><strong x-text="search.conversation ? 'トーク内を検索' : 'メッセージを検索'"></strong><button type="button" class="sfk-m-icon-btn" @click="dialog = null" title="閉じる"><x-filament::icon :icon="Heroicon::OutlinedXMark" class="sfk-m-ico" /></button></header>
                    <form class="sfk-m-search-form" @submit.prevent="runSearch()">
                        <div class="sfk-m-search is-dialog"><x-filament::icon :icon="Heroicon::OutlinedMagnifyingGlass" class="sfk-m-ico" /><input type="search" x-ref="searchInput" x-model="search.q" placeholder="キーワード・ファイル名" @input.debounce.400ms="runSearch()"></div>
                        <div class="sfk-m-chips">
                            <template x-for="[kind, label] in [['', 'すべて'], ['direct', '個人'], ['group', 'グループ'], ['channel', 'チャンネル'], ['images', '画像'], ['documents', 'ドキュメント'], ['links', 'リンク'], ['pinned', 'ピン留め']]" :key="kind">
                                <button type="button" class="sfk-m-chip" :class="{ 'is-active': search.kind === kind }" @click="search.kind = kind; runSearch()" x-text="label"></button>
                            </template>
                        </div>
                        <div class="sfk-m-search-filters">
                            <select x-model="search.user" @change="runSearch()" aria-label="送信者">
                                <option value="">送信者</option>
                                <template x-for="p in Object.values(people).sort((a, b) => (a.kana || a.name).localeCompare(b.kana || b.name, 'ja'))" :key="p.id"><option :value="p.id" x-text="p.name"></option></template>
                            </select>
                            <input type="date" x-model="search.from" @change="runSearch()" aria-label="開始日">
                            <span>〜</span>
                            <input type="date" x-model="search.to" @change="runSearch()" aria-label="終了日">
                            <label class="sfk-m-inline-check" x-show="activeId"><input type="checkbox" class="sfk-ui-check" :checked="!!search.conversation" @change="search.conversation = $event.target.checked ? activeId : null; runSearch()"><span>このトークのみ</span></label>
                        </div>
                    </form>
                    <div class="sfk-m-results">
                        <template x-for="r in search.results" :key="r.id">
                            <button type="button" class="sfk-m-result" @click="openResult(r)">
                                <span class="sfk-m-avatar is-sm" :style="avatarStyle(r.user)"><span x-text="initial(nameOf(r.user))"></span></span>
                                <span class="sfk-m-result-text">
                                    <span class="sfk-m-result-top"><strong x-text="nameOf(r.user)"></strong><small x-text="(r.conversation_type === 'channel' ? '# ' : '') + r.conversation_name"></small><time x-text="fullTime(r.at)"></time></span>
                                    <span class="sfk-m-result-body" x-html="highlight(r.text)"></span>
                                    <span class="sfk-m-result-file" x-show="r.file"><x-filament::icon :icon="Heroicon::OutlinedPaperClip" class="sfk-m-ico is-xs" /><span x-text="r.file"></span></span>
                                </span>
                            </button>
                        </template>
                        <p class="sfk-m-none" x-show="search.done && !search.results.length && !search.loading">見つかりませんでした</p>
                        <button type="button" class="sfk-m-btn sfk-m-more" x-show="search.more" @click="runSearch(true)">さらに表示</button>
                        <div class="sfk-m-loading" x-show="search.loading"><span></span><span></span><span></span></div>
                    </div>
                </div>
            </template>

            {{-- settings --}}
            <template x-if="dialog === 'settings'">
                <div class="sfk-m-dialog-inner is-small">
                    <header><strong>メッセージ設定</strong><button type="button" class="sfk-m-icon-btn" @click="dialog = null" title="閉じる"><x-filament::icon :icon="Heroicon::OutlinedXMark" class="sfk-m-ico" /></button></header>
                    <div class="sfk-m-settings">
                        <label class="sfk-m-setting">
                            <span>在席状況</span>
                            <select @change="savePrefs({ status: $event.target.value })">
                                <option value="auto" :selected="prefs.status === 'auto'">自動</option>
                                <option value="away" :selected="prefs.status === 'away'">離席中</option>
                                <option value="dnd" :selected="prefs.status === 'dnd'">取り込み中</option>
                            </select>
                        </label>
                        <label class="sfk-m-setting"><span>通知音</span><input type="checkbox" class="sfk-ui-check" :checked="prefs.sound" @change="savePrefs({ sound: $event.target.checked })"></label>
                        <label class="sfk-m-setting"><span>ブラウザの通知</span><input type="checkbox" class="sfk-ui-check" :checked="prefs.desktop" @change="savePrefs({ desktop: $event.target.checked })"></label>
                        <label class="sfk-m-setting"><span>通知にメッセージ本文を表示</span><input type="checkbox" class="sfk-ui-check" :checked="prefs.preview" @change="savePrefs({ preview: $event.target.checked })"></label>
                        <label class="sfk-m-setting"><span>オンライン状態を表示</span><input type="checkbox" class="sfk-ui-check" :checked="prefs.presence" @change="savePrefs({ presence: $event.target.checked })"></label>
                        <label class="sfk-m-setting">
                            <span>おやすみモード</span>
                            <select @change="savePrefs({ dnd_hours: Number($event.target.value) })">
                                <option value="0" :selected="!prefs.dnd_until">オフ</option>
                                <option value="1">1時間</option>
                                <option value="3">3時間</option>
                                <option value="8">8時間</option>
                                <option value="24">24時間</option>
                                <option value="0" disabled :selected="!!prefs.dnd_until" x-show="prefs.dnd_until" x-text="prefs.dnd_until ? fullTime(prefs.dnd_until) + ' まで' : ''"></option>
                            </select>
                        </label>
                    </div>
                </div>
            </template>

            {{-- announcement --}}
            <template x-if="dialog === 'announce'">
                <div class="sfk-m-dialog-inner">
                    <header><strong>全社アナウンスを投稿</strong><button type="button" class="sfk-m-icon-btn" @click="dialog = null" title="閉じる"><x-filament::icon :icon="Heroicon::OutlinedXMark" class="sfk-m-ico" /></button></header>
                    <div class="sfk-m-form is-scroll">
                        <label><span>タイトル</span><input type="text" x-model="form.title" maxlength="150"></label>
                        <label><span>本文</span><textarea x-model="form.body" rows="6" maxlength="20000"></textarea></label>
                        <label><span>添付ファイル</span><input type="file" x-ref="announcementFiles" multiple accept=".pdf,.docx,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp,.gif"></label>
                        <div class="sfk-m-field">
                            <span>重要度</span>
                            <div class="sfk-m-radios">
                                <label><input type="radio" class="sfk-ui-radio" value="normal" x-model="form.priority"><span>通常</span></label>
                                <label><input type="radio" class="sfk-ui-radio" value="important" x-model="form.priority"><span>重要</span></label>
                                <label><input type="radio" class="sfk-ui-radio" value="urgent" x-model="form.priority"><span>至急</span></label>
                            </div>
                        </div>
                        <div class="sfk-m-field">
                            <span>対象</span>
                            <div class="sfk-m-radios">
                                <label><input type="radio" class="sfk-ui-radio" value="all" x-model="form.audience"><span>全社員</span></label>
                                <label><input type="radio" class="sfk-ui-radio" value="categories" x-model="form.audience"><span>従業員カテゴリ</span></label>
                                <label><input type="radio" class="sfk-ui-radio" value="groups" x-model="form.audience"><span>グループ・チャンネル</span></label>
                                <label><input type="radio" class="sfk-ui-radio" value="users" x-model="form.audience"><span>個人</span></label>
                            </div>
                        </div>
                        <div class="sfk-m-chips" x-show="form.audience === 'categories'">
                            <template x-for="c in categories" :key="c.id"><button type="button" class="sfk-m-chip" :class="{ 'is-active': form.categories.includes(c.id) }" @click="form.categories.includes(c.id) ? form.categories.splice(form.categories.indexOf(c.id), 1) : form.categories.push(c.id)" x-text="c.name"></button></template>
                        </div>
                        <div class="sfk-m-chips" x-show="form.audience === 'groups'">
                            <template x-for="g in myGroups" :key="g.id"><button type="button" class="sfk-m-chip" :class="{ 'is-active': form.groups.includes(g.id) }" @click="form.groups.includes(g.id) ? form.groups.splice(form.groups.indexOf(g.id), 1) : form.groups.push(g.id)" x-text="(g.type === 'channel' ? '# ' : '') + g.name"></button></template>
                        </div>
                        <div x-show="form.audience === 'users'">
                            <div class="sfk-m-search is-dialog"><x-filament::icon :icon="Heroicon::OutlinedMagnifyingGlass" class="sfk-m-ico" /><input type="search" x-model="pick.query" placeholder="従業員を検索"></div>
                            <div class="sfk-m-people is-short">
                                <template x-for="p in peopleMatching(pick.query)" :key="p.id">
                                    <button type="button" class="sfk-m-person" :class="{ 'is-selected': pick.selected.includes(p.id) }" @click="togglePick(p.id)">
                                        <span class="sfk-m-avatar is-sm" :style="avatarStyle(p.id)"><span x-text="initial(p.name)"></span></span>
                                        <span class="sfk-m-member-text"><strong x-text="p.name"></strong></span>
                                        <span class="sfk-m-check"><x-filament::icon :icon="Heroicon::Check" class="sfk-m-ico" /></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <label class="sfk-m-inline-check"><input type="checkbox" class="sfk-ui-check" x-model="form.confirm"><span>確認を求める</span></label>
                        <div class="sfk-m-field is-row">
                            <label><span>公開日時</span><input type="datetime-local" x-model="form.at"></label>
                            <label><span>掲載終了</span><input type="datetime-local" x-model="form.expires"></label>
                        </div>
                    </div>
                    <footer>
                        <button type="button" class="sfk-m-btn" @click="dialog = null">キャンセル</button>
                        <button type="button" class="sfk-m-btn is-primary" :disabled="form.busy || !form.title?.trim() || !form.body?.trim()" @click="publishAnnouncement()">投稿</button>
                    </footer>
                </div>
            </template>
        </div>
    </div>

    {{-- image viewer --}}
    <div class="sfk-m-viewer" x-show="viewer" x-transition.opacity.duration.120ms @click.self="closeViewer()" @keydown.left.window="viewer && viewerStep(-1)" @keydown.right.window="viewer && viewerStep(1)">
        <template x-if="viewer">
            <div class="sfk-m-viewer-inner" @click.self="closeViewer()">
                <img :src="viewer.list[viewer.index].url" alt="">
                <div class="sfk-m-viewer-bar">
                    <span x-text="(viewer.index + 1) + ' / ' + viewer.list.length"></span>
                    <a class="sfk-m-icon-btn" :href="viewer.list[viewer.index].url + '?download=1'" title="ダウンロード"><x-filament::icon :icon="Heroicon::OutlinedArrowDownTray" class="sfk-m-ico" /></a>
                    <button type="button" class="sfk-m-icon-btn" @click="closeViewer()" title="閉じる"><x-filament::icon :icon="Heroicon::OutlinedXMark" class="sfk-m-ico" /></button>
                </div>
                <button type="button" class="sfk-m-viewer-nav is-prev" x-show="viewer.list.length > 1" @click="viewerStep(-1)" title="前へ"><x-filament::icon :icon="Heroicon::OutlinedChevronLeft" class="sfk-m-ico" /></button>
                <button type="button" class="sfk-m-viewer-nav is-next" x-show="viewer.list.length > 1" @click="viewerStep(1)" title="次へ"><x-filament::icon :icon="Heroicon::OutlinedChevronRight" class="sfk-m-ico" /></button>
            </div>
        </template>
    </div>
</div>
</x-filament-panels::page>
