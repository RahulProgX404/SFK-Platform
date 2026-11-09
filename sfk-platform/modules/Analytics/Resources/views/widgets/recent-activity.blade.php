{{-- 最近のアクティビティ (Modules\Analytics\Filament\Widgets\RecentActivityCard): a timeline, in two columns on wide screens --}}
@php
    $kinds = [
        'notice' => ['tone' => 'blue', 'icon' => 'heroicon-o-bell'],
        'created' => ['tone' => 'green', 'icon' => 'heroicon-o-document-plus'],
        'updated' => ['tone' => 'purple', 'icon' => 'heroicon-o-pencil-square'],
        'comment' => ['tone' => 'amber', 'icon' => 'heroicon-o-chat-bubble-left-ellipsis'],
    ];
    // two columns over the whole width; one when the card has half the row (個人)
    $columns = $half ? [$items] : array_chunk($items, max(1, (int) ceil(count($items) / 2)));
@endphp

<div class="sfk-dash-cell sfk-dash-span-{{ $half ? 6 : 12 }}">
    <style>
        .sfk-activity { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 var(--sfk-space-8); }
        .sfk-activity ol { margin: 0; padding: 0; list-style: none; }
        .sfk-activity-item { position: relative; display: flex; gap: var(--sfk-space-3); padding-bottom: var(--sfk-space-4); }
        .sfk-activity-item::before { content: ""; position: absolute; left: 15px; top: 34px; bottom: 2px; width: 2px; border-radius: 2px; background: linear-gradient(180deg, var(--sfk-tone-soft), transparent); }
        .sfk-activity-item:last-child::before { display: none; }
        .sfk-activity-icon { position: relative; z-index: 1; display: grid; place-items: center; width: 32px; height: 32px; flex: none; border-radius: 9999px; background: var(--sfk-tone); color: #fff; }
        .sfk-activity-icon .fi-icon { width: 16px; height: 16px; }
        .sfk-activity-text { flex: 1; min-width: 0; padding-top: 4px; }
        .sfk-activity-head { display: flex; align-items: baseline; justify-content: space-between; gap: var(--sfk-space-3); }
        .sfk-activity-head strong { min-width: 0; color: var(--sfk-ink); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-semibold); overflow-wrap: anywhere; }
        .sfk-activity-head time { flex: none; color: var(--sfk-muted); font-size: var(--sfk-fs-badge); white-space: nowrap; }
        .sfk-activity-body { display: block; margin-top: 2px; color: var(--sfk-text); font-size: var(--sfk-fs-small); overflow-wrap: anywhere; }
        a.sfk-activity-text { color: inherit; text-decoration: none; }
        a.sfk-activity-text:hover strong { color: var(--sfk-primary); }
        .sfk-activity.is-single { grid-template-columns: minmax(0, 1fr); }
        @media (max-width: 1023px) { .sfk-activity { grid-template-columns: minmax(0, 1fr); } }
    </style>

    <section class="sfk-dash-card" aria-labelledby="sfk-activity-title">
        <header class="sfk-dash-card-head">
            <h2 class="sfk-dash-card-title" id="sfk-activity-title">最近のアクティビティ</h2>
        </header>

        @if ($items === [])
            <p class="sfk-dash-empty">まだアクティビティはありません。</p>
        @else
            <div @class(['sfk-activity', 'is-single' => $half])>
                @foreach ($columns as $column)
                    <ol>
                        @foreach ($column as $item)
                            @php($kind = $kinds[$item['kind']] ?? $kinds['notice'])
                            <li class="sfk-activity-item sfk-tone-{{ $kind['tone'] }}">
                                <span class="sfk-activity-icon" aria-hidden="true"><x-filament::icon :icon="$kind['icon']" /></span>
                                @if (filled($item['url']))
                                    <a href="{{ $item['url'] }}" class="sfk-activity-text">
                                @else
                                    <div class="sfk-activity-text">
                                @endif
                                    <span class="sfk-activity-head">
                                        <strong>{{ $item['title'] }}</strong>
                                        <time datetime="{{ $item['at']->toIso8601String() }}" title="{{ $item['at']->format('Y/m/d H:i') }}">{{ $item['at']->locale('ja')->diffForHumans() }}</time>
                                    </span>
                                    @if (filled($item['body']))
                                        <span class="sfk-activity-body">{{ $item['body'] }}</span>
                                    @endif
                                @if (filled($item['url']))
                                    </a>
                                @else
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endforeach
            </div>
        @endif
    </section>
</div>
