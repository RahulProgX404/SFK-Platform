{{--
    変更履歴 tab of the record panel (core::filament.record-sidebar), legacy layout: the versions newest first
    (バージョン, date, who with the ユーザー情報 popup, what changed) with 更新内容を表示 / このバージョンに戻す
    (初版を表示 / 初版に戻す for the first one) and, while a version is shown, 最新の内容を表示.
--}}
@php
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
                <li @class(['sfk-history-item', 'is-shown' => $shown?->version === $version->version]) wire:key="version-{{ $version->id }}">
                    <div class="sfk-history-item-head">
                        <span class="sfk-history-version">バージョン {{ $version->version }}</span>
                        <time datetime="{{ $version->created_at?->toIso8601String() }}" class="sfk-history-date">{{ $version->created_at?->format('Y-m-d H:i') }}</time>
                    </div>
                    <p class="sfk-history-who">
                        @if ($version->user !== null)
                            {{ UserPopup::html($version->user) }}
                        @else
                            <span class="sfk-history-unknown">—</span>
                        @endif
                        <span class="sfk-history-event">
                            @switch($version->event)
                                @case(RecordVersion::EVENT_CREATED) 作成 @break
                                @case(RecordVersion::EVENT_RESTORED) バージョン {{ $version->restored_from }} に戻す @break
                                @default 更新
                            @endswitch
                        </span>
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
                            <button type="button" class="sfk-filter-link" wire:click="showVersion({{ $version->version }})">
                                {{ $version->version === 1 ? '初版を表示' : '更新内容を表示' }}
                            </button>
                        @endif
                        {{-- only for those who may edit the record (an action drawn here would show disabled when hidden) --}}
                        @if ($version->version !== $latest && ($restore = ($page->restoreVersionAction)(['version' => $version->version]))->isVisible())
                            {{ $restore }}
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</div>
