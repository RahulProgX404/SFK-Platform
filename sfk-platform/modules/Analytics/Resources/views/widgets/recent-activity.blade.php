{{-- 最近のアクティビティ (Modules\Analytics\Filament\Widgets\RecentActivityCard): a timeline, in two columns on wide screens;
     the card keeps the height of all its entries (ghost rows; user 2026-11-17), each entry one line of title with its time
     and one of text (the whole text on hover) --}}
@php
    $kinds = [
        'notice' => ['tone' => 'blue', 'icon' => 'heroicon-o-bell'],
        'created' => ['tone' => 'green', 'icon' => 'heroicon-o-document-plus'],
        'updated' => ['tone' => 'purple', 'icon' => 'heroicon-o-pencil-square'],
        'comment' => ['tone' => 'amber', 'icon' => 'heroicon-o-chat-bubble-left-ellipsis'],
    ];
    // two columns over the whole width; one when the card has half the row (個人, 全体) or a third (without 財務分析を表示)
    $single = $span < 12;
    $columns = $single ? [$items] : array_chunk($items, max(1, (int) ceil(count($items) / 2)));
@endphp

{{-- tablets: a third of the row becomes a row of its own under 最近表示した項目 · 休暇・勤怠予定 --}}
<div @class(['sfk-dash-cell', 'sfk-dash-span-'.$span, 'is-fit', 'sfk-dash-t-full' => $span === 4])>
    {{-- live: redrawn when what it reads changes (DashboardCard::liveTables(), DataPulse) --}}
    @include('core::filament.live-data', ['tables' => $this::liveTables()])
    <section class="sfk-dash-card" aria-labelledby="sfk-activity-title">
        <header class="sfk-dash-card-head">
            <h2 class="sfk-dash-card-title" id="sfk-activity-title">最近のアクティビティ</h2>
        </header>

        <div class="sfk-dash-stack">
            @include('analytics::widgets.partials.ghost', ['kind' => 'activity', 'rows' => \Modules\Analytics\Filament\Widgets\RecentActivityCard::LIMIT, 'columns' => $single ? 1 : 2])
            @if ($items === [])
                <p class="sfk-dash-empty">まだアクティビティはありません。</p>
            @else
                <div @class(['sfk-activity', 'is-single' => $single])>
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
                                            <strong title="{{ $item['title'] }}">{{ $item['title'] }}</strong>
                                            <time datetime="{{ $item['at']->toIso8601String() }}">{{ $item['at']->locale('ja')->diffForHumans() }}<small>{{ $item['at']->format('n/j H:i') }}</small></time>
                                        </span>
                                        {{-- always there (empty: a space), so every entry has the same height --}}
                                        <span class="sfk-activity-body" @if (filled($item['body'])) title="{{ $item['body'] }}" @endif>{!! filled($item['body']) ? e($item['body']) : '&nbsp;' !!}</span>
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
        </div>
    </section>
</div>
