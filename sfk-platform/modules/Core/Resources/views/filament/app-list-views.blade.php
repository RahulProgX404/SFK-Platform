{{--
    アプリの設定 → 一覧 (Modules\Core\Filament\AppPages\AppListViewSettings), legacy layout:
    - 作成済みの一覧: ＋ 一覧を追加する; rows with ドラッグして並び替え, the 表示形式 icon, 一覧名 and 編集 / 複製 / 削除;
      （すべて） last without handle or actions.
    - 一覧 screen: 一覧名, レコード一覧の表示形式, the columns of 表形式 (field list with search and すべて追加 on the left,
      the columns as a header row on the right: drag to reorder, ⚙ → 左へ移動 / 右へ移動 / 削除, すべてクリア), 日付 / タイトル of カレンダー形式,
      絞り込み and ソート (core::filament.filter-editor), キャンセル / 保存. Behaviour: sfkViewEditor
      (core::filament.app-filter-script).
--}}
@php
    use Filament\Support\Icons\Heroicon;
    use Modules\Core\Domain\Views\ListViewType;
@endphp

<x-filament-panels::page>
    @if ($this->editing === null)
        <section class="sfk-views" aria-labelledby="sfk-views-title">
            <div class="sfk-views-head">
                <h2 id="sfk-views-title" class="sfk-views-title">作成済みの一覧</h2>
                <button type="button" class="sfk-app-add sfk-views-add" wire:click="addView" title="一覧を追加する">
                    <x-filament::icon :icon="Heroicon::Plus" class="sfk-app-add-icon" />
                    <span class="fi-sr-only">一覧を追加する</span>
                </button>
            </div>

            <ul
                class="sfk-views-list"
                x-data
                x-sortable
                data-sortable-animation-duration="150"
                x-on:end.stop="$wire.reorderViews($event.target.sortable.toArray())"
            >
                @foreach ($this->storedViews() as $view)
                    <li class="sfk-views-row" x-sortable-item="{{ $view->id }}" wire:key="view-{{ $view->id }}">
                        <button type="button" class="sfk-views-handle" x-sortable-handle title="ドラッグして並び替え">
                            <x-filament::icon :icon="Heroicon::Bars3" class="sfk-views-handle-icon" />
                            <span class="fi-sr-only">ドラッグして並び替え</span>
                        </button>
                        <x-filament::icon
                            :icon="$view->type === ListViewType::Calendar ? Heroicon::OutlinedCalendarDays : Heroicon::OutlinedTableCells"
                            class="sfk-views-type"
                            :title="$view->type->label()"
                        />
                        <span class="fi-sr-only">{{ $view->type->label() }}</span>
                        <span class="sfk-views-name">{{ $view->name }}</span>
                        <span class="sfk-views-actions">
                            <button type="button" class="sfk-views-action" wire:click="editView({{ $view->id }})">
                                <x-filament::icon :icon="Heroicon::OutlinedPencil" class="sfk-views-action-icon" />編集
                            </button>
                            <button type="button" class="sfk-views-action" wire:click="copyView({{ $view->id }})">
                                <x-filament::icon :icon="Heroicon::OutlinedDocumentDuplicate" class="sfk-views-action-icon" />複製
                            </button>
                            {{ ($this->deleteViewAction)(['view' => $view->id]) }}
                        </span>
                    </li>
                @endforeach
            </ul>

            <div class="sfk-views-row is-fixed">
                <span class="sfk-views-handle is-placeholder" aria-hidden="true"></span>
                <x-filament::icon :icon="Heroicon::OutlinedTableCells" class="sfk-views-type" />
                <span class="sfk-views-name">{{ \Modules\Core\Application\Views\ListView::ALL_NAME }}</span>
            </div>
        </section>
    @else
        <div class="sfk-view-editor" x-data="sfkViewEditor(@js($this->editorConfig()))" wire:key="view-editor-{{ $this->editing }}" wire:ignore>
            <section class="sfk-filter-section">
                <label for="sfk-view-name" class="sfk-filter-label">一覧名<span class="sfk-filter-required" aria-hidden="true">*</span></label>
                <input id="sfk-view-name" type="text" class="sfk-filter-input sfk-view-name" x-model="name" maxlength="64" required>
                <p class="sfk-filter-error" x-show="errors.name" x-text="errors.name" role="alert"></p>
            </section>

            <section class="sfk-filter-section" aria-labelledby="sfk-view-type">
                <h3 id="sfk-view-type" class="sfk-filter-label">レコード一覧の表示形式</h3>
                <div class="sfk-view-types" role="radiogroup" aria-labelledby="sfk-view-type">
                    <label class="sfk-view-type" :class="{ 'is-active': type === 'table' }">
                        <input type="radio" value="table" x-model="type" class="fi-sr-only">
                        <x-filament::icon :icon="Heroicon::OutlinedTableCells" class="sfk-view-type-icon" />
                        表形式
                    </label>
                    <label class="sfk-view-type" :class="{ 'is-active': type === 'calendar' }">
                        <input type="radio" value="calendar" x-model="type" class="fi-sr-only">
                        <x-filament::icon :icon="Heroicon::OutlinedCalendarDays" class="sfk-view-type-icon" />
                        カレンダー形式
                    </label>
                </div>

                {{-- 表形式: field list → columns --}}
                <div class="sfk-view-layout" x-show="type === 'table'">
                    <div class="sfk-view-palette-ctn">
                        <label class="sfk-view-search">
                            <x-filament::icon :icon="Heroicon::MagnifyingGlass" class="sfk-view-search-icon" />
                            <span class="fi-sr-only">フィールドを検索</span>
                            <input type="search" class="sfk-filter-input" x-model="search" aria-label="フィールドを検索">
                        </label>
                        <ul class="sfk-view-palette">
                            <template x-for="column in palette()" :key="column.name">
                                <li class="sfk-view-field" :data-field="column.name" draggable="true"
                                    x-on:dragstart="startDrag($event, column.name, 'palette')" x-on:dragend="endDrag()">
                                    <button type="button" class="sfk-view-field-btn" x-on:click="addColumn(column.name)" x-text="column.label" :title="column.label"></button>
                                </li>
                            </template>
                        </ul>
                        <button type="button" class="sfk-filter-link" x-on:click="addAllColumns()">すべて追加</button>
                    </div>

                    <div class="sfk-view-columns-ctn">
                        <ol class="sfk-view-columns" x-ref="columns" aria-label="表示するフィールド"
                            :class="{ 'is-drop-target': dragging, 'is-drop-end': dragging && dropIndex === columns.length }"
                            x-on:dragover.prevent="dragOver($event)" x-on:drop.prevent="drop()">
                            <template x-for="(column, index) in columns" :key="column">
                                <li class="sfk-view-column" :data-column="column" draggable="true"
                                    :class="{ 'is-drop-before': dragging && dropIndex === index, 'is-dragging': dragging && dragging.name === column }"
                                    x-on:dragstart="startDrag($event, column, 'columns')" x-on:dragend="endDrag()">
                                    <span class="sfk-view-column-name" x-text="columnLabel(column)"></span>
                                    <span class="sfk-view-column-menu" x-on:click.outside="columnMenu === column && (columnMenu = null)">
                                        <button type="button" class="sfk-view-column-gear" x-on:click="columnMenu = columnMenu === column ? null : column" :aria-expanded="columnMenu === column" title="設定">
                                            <x-filament::icon :icon="Heroicon::OutlinedCog6Tooth" class="sfk-view-column-gear-icon" />
                                            <span class="fi-sr-only">設定</span>
                                        </button>
                                        <span class="sfk-view-column-pop" x-show="columnMenu === column" x-cloak>
                                            <button type="button" class="sfk-view-column-pop-item" x-show="index > 0" x-on:click="moveColumn(column, index - 1)">左へ移動</button>
                                            <button type="button" class="sfk-view-column-pop-item" x-show="index < columns.length - 1" x-on:click="moveColumn(column, index + 2)">右へ移動</button>
                                            <button type="button" class="sfk-view-column-pop-item" x-on:click="removeColumn(column)">削除</button>
                                        </span>
                                    </span>
                                </li>
                            </template>
                        </ol>
                        <button type="button" class="sfk-filter-link" x-show="columns.length > 0" x-on:click="clearColumns()">すべてクリア</button>
                        <p class="sfk-filter-error" x-show="errors.columns" x-text="errors.columns" role="alert"></p>
                    </div>
                </div>

                {{-- カレンダー形式: 日付 and タイトル --}}
                <div class="sfk-view-calendar-fields" x-show="type === 'calendar'" x-cloak>
                    <label class="sfk-view-calendar-field">
                        <span class="sfk-filter-label">日付<span class="sfk-filter-required" aria-hidden="true">*</span></span>
                        <span class="sfk-filter-select">
                            <select x-model="dateField">
                                <option value="">--</option>
                                <template x-for="f in config.dateFields" :key="f.name">
                                    <option :value="f.name" x-text="f.label" :selected="dateField === f.name"></option>
                                </template>
                            </select>
                        </span>
                        <p class="sfk-filter-error" x-show="errors.dateField" x-text="errors.dateField" role="alert"></p>
                    </label>
                    <label class="sfk-view-calendar-field">
                        <span class="sfk-filter-label">タイトル<span class="sfk-filter-required" aria-hidden="true">*</span></span>
                        <span class="sfk-filter-select">
                            <select x-model="titleField">
                                <option value="">--</option>
                                <template x-for="f in config.titleFields" :key="f.name">
                                    <option :value="f.name" x-text="f.label" :selected="titleField === f.name"></option>
                                </template>
                            </select>
                        </span>
                        <p class="sfk-filter-error" x-show="errors.titleField" x-text="errors.titleField" role="alert"></p>
                    </label>
                </div>
            </section>

            @include('core::filament.filter-editor', ['conditionsLabel' => '絞り込み', 'idPrefix' => 'sfk-view'])

            <footer class="sfk-filter-foot sfk-view-foot">
                <button type="button" class="sfk-filter-btn" wire:click="cancelEditing">キャンセル</button>
                <button type="button" class="sfk-filter-btn sfk-filter-btn-submit" x-on:click="saveView()" :disabled="busy">保存</button>
            </footer>
        </div>
    @endif
</x-filament-panels::page>
