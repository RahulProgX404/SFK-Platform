{{--
    The room of a list card's full length (user 2026-11-17: 上位の売上先, 売れ筋 TOP3, 担当者別売上, 作業者ランキング,
    最近表示した項目, 休暇・勤怠予定 and 最近のアクティビティ keep the height they have with all their rows — also with fewer
    rows or none). An invisible copy of the card's rows — the same markup and classes, no text — that lies under the real
    content in the same grid cell (`.sfk-dash-stack`, core::filament.portal): the cell is as high as the full list, the
    real rows fill it from the top and a 「…はありません」 text sits in its middle.

    $kind  ranking | people | products | recent | activity | absences
    $rows  rows of the full list (activity: all entries; absences: people per day)
    $class extra classes of the list (ranking: sfk-workers; people: is-compact)
    $columns  activity: 1 or 2
--}}
@php
    $rows = max(1, (int) $rows);
    $class ??= '';
@endphp
<div class="sfk-dash-ghost" aria-hidden="true" inert>
    @switch($kind)
        @case('ranking')
            <ol class="sfk-dash-ranking {{ $class }}">
                @for ($i = 0; $i < $rows; $i++)
                    <li @class(['sfk-workers-row' => str_contains($class, 'sfk-workers')])>
                        <span class="sfk-dash-rankno">0</span>
                        <span class="sfk-dash-ranking-name"><strong>&nbsp;</strong><small>&nbsp;</small></span>
                        <span class="sfk-dash-ranking-figure"><strong>&nbsp;</strong></span>
                        <span class="sfk-dash-meter"><i></i></span>
                    </li>
                @endfor
            </ol>
            @break

        @case('people')
            <ul class="sfk-dash-people {{ $class }}">
                @for ($i = 0; $i < $rows; $i++)
                    <li>
                        <span class="sfk-dash-avatar">&nbsp;</span>
                        <span class="sfk-dash-people-text"><strong>&nbsp;</strong><small>&nbsp;</small></span>
                        <span class="sfk-dash-people-figure"><small>&nbsp;</small><strong>&nbsp;</strong></span>
                    </li>
                @endfor
            </ul>
            @break

        @case('products')
            <ol class="sfk-products">
                @for ($i = 0; $i < $rows; $i++)
                    <li class="sfk-products-item">
                        <span class="sfk-dash-rankno">0</span>
                        <span class="sfk-products-name">&nbsp;</span>
                        <span class="sfk-products-bar"><i></i></span>
                        <span class="sfk-products-figure"><span class="sfk-products-amount">&nbsp;</span><span class="sfk-products-count">&nbsp;</span></span>
                    </li>
                @endfor
            </ol>
            @break

        @case('recent')
            <ul class="sfk-recent">
                @for ($i = 0; $i < $rows; $i++)
                    <li>
                        <span class="sfk-recent-link">
                            <span class="sfk-recent-icon">&nbsp;</span>
                            <span class="sfk-recent-text"><strong>&nbsp;</strong><small>&nbsp;</small></span>
                        </span>
                    </li>
                @endfor
            </ul>
            @break

        @case('activity')
            @php($perColumn = (int) ceil($rows / max(1, (int) ($columns ?? 1))))
            <div @class(['sfk-activity', 'is-single' => ($columns ?? 1) === 1])>
                @for ($c = 0; $c < max(1, (int) ($columns ?? 1)); $c++)
                    <ol>
                        @for ($i = 0; $i < $perColumn; $i++)
                            <li class="sfk-activity-item">
                                <span class="sfk-activity-icon"></span>
                                <span class="sfk-activity-text">
                                    <span class="sfk-activity-head"><strong>&nbsp;</strong><time>&nbsp;<small>&nbsp;</small></time></span>
                                    <span class="sfk-activity-body">&nbsp;</span>
                                </span>
                            </li>
                        @endfor
                    </ol>
                @endfor
            </div>
            @break

        @case('absences')
            <div class="sfk-absence-days">
                @for ($d = 0; $d < 2; $d++)
                    <div class="sfk-absence-day">
                        <h3>&nbsp;<small>&nbsp;</small></h3>
                        <ul class="sfk-dash-people is-compact">
                            @for ($i = 0; $i < $rows; $i++)
                                <li>
                                    <span class="sfk-dash-avatar">&nbsp;</span>
                                    <span class="sfk-dash-people-text"><strong>&nbsp;</strong><small>&nbsp;</small></span>
                                </li>
                            @endfor
                        </ul>
                    </div>
                @endfor
            </div>
            @break
    @endswitch
</div>
