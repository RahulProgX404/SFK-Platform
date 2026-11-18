{{--
    変更履歴 tab of the record panel (core::filament.record-sidebar), in the cards of the コメント tab
    (comment-design.png): the versions newest first — who (avatar + name with the ユーザー情報 popup), how long ago (the
    date and time on hover), バージョン N with what happened (作成 / 更新 / バージョン N に戻す), the changed fields —
    with 更新内容を表示 / このバージョンに戻す (初版を表示 / 初版に戻す for the first one); the shown version is
    framed and, while it is shown, 最新の内容を表示 stays above the list.
--}}
@php
    use Filament\Support\Icons\Heroicon;
    use Modules\Core\Filament\Support\UserPopup;
    use Modules\Core\Models\RecordVersion;

    $page = $getLivewire();
    $versions = $page->historyVersions();
    $latest = $versions->first()?->version;
    $shown = $page->shownVersion();
@endphp

<div class="sfk-history-panel">

    @if ($shown !== null)
        <div class="sfk-history-shown">
            <span>バージョン {{ $shown->version }}</span>
            <button type="button" class="sfk-filter-btn sfk-history-latest" wire:click="showLatest">最新の内容を表示</button>
        </div>
    @endif

    @if ($versions->isEmpty())
        <p class="sfk-history-empty">変更履歴はありません。</p>
    @else
        <ol class="sfk-history-list">
            @foreach ($versions as $version)
                @php
                    [$event, $tone] = match ($version->event) {
                        RecordVersion::EVENT_CREATED => ['作成', 'is-created'],
                        RecordVersion::EVENT_RESTORED => ['バージョン '.$version->restored_from.' に戻す', 'is-restored'],
                        default => ['更新', 'is-updated'],
                    };
                @endphp
                <li wire:key="version-{{ $version->id }}">
                    <article @class(['sfk-comment-card', 'sfk-history-item', 'is-shown' => $shown?->version === $version->version])>
                        <header class="sfk-comment-head">
                            <span class="sfk-comment-author">
                                @if ($version->user !== null)
                                    {{ UserPopup::html($version->user) }}
                                @else
                                    <span class="sfk-history-unknown">—</span>
                                @endif
                                @if ($version->created_at !== null)
                                    <time datetime="{{ $version->created_at->toIso8601String() }}" class="sfk-comment-time" title="{{ $version->created_at->format('Y-m-d H:i') }}">{{ $version->created_at->locale('ja')->diffForHumans() }}</time>
                                @endif
                            </span>
                        </header>
                        <p class="sfk-history-meta">
                            <span class="sfk-history-version">バージョン {{ $version->version }}</span>
                            <span class="sfk-history-event {{ $tone }}">{{ $event }}</span>
                        </p>
                        @if ($version->changed !== [])
                            <ul class="sfk-history-fields">
                                @foreach ($version->changed as $field)
                                    <li>{{ $page->historyFieldLabel($field) }}</li>
                                @endforeach
                            </ul>
                        @endif
                        <div class="sfk-history-actions">
                            @if ($shown?->version !== $version->version)
                                <button type="button" class="sfk-comment-action" wire:click="showVersion({{ $version->version }})">
                                    <x-filament::icon :icon="Heroicon::Eye" class="sfk-comment-action-icon" />{{ $version->version === 1 ? '初版を表示' : '更新内容を表示' }}
                                </button>
                            @endif
                            {{-- only for those who may edit the record (an action drawn here would show disabled when hidden) --}}
                            @if ($version->version !== $latest && ($restore = ($page->restoreVersionAction)(['version' => $version->version]))->isVisible())
                                {{ $restore }}
                            @endif
                        </div>
                    </article>
                </li>
            @endforeach
        </ol>
    @endif
</div>
