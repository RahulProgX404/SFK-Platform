{{--
    帳票テンプレートの編集 (ManagesDocuments::designerConfig(), saveDesign()): the template's pages drawn from its background PDF
    (pdf.js, public/vendor/pdfjs, Apache-2.0) with the placed elements on top, in mm.
    - left: フィールド / テーブル / その他 (固定テキスト, 出力日 …): drag onto the page, or click to add at the top left;
    - page: drag an element to move it, its corner to resize it; arrow keys move the selected one (Shift: 5 mm),
      Delete removes it, Ctrl+D copies it, Esc unselects;
    - right: the selected element's properties (position, size, font, alignment, 書式, table columns);
    - top: テンプレート名, pages, 表示倍率, 1mm単位, 背景を差し替える, プレビュー (the unsaved layout with a record or the
      field labels, new tab), キャンセル / 保存.
    Behaviour: sfkDocumentDesigner (core::filament.documents.designer-script).
--}}
@php
    use Filament\Support\Icons\Heroicon;
@endphp

@include('core::filament.documents.designer-script')

<div
    class="sfk-designer"
    x-data="sfkDocumentDesigner(@js($this->designerConfig()))"
    x-on:keydown.window="onKey($event)"
    x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = '' }"
    wire:ignore
    wire:key="designer-{{ $this->designing }}"
>
    <div class="sfk-designer-bar">
        <label class="sfk-designer-name">
            <span class="sfk-designer-caption">テンプレート名<span class="sfk-filter-required" aria-hidden="true">*</span></span>
            <input type="text" class="sfk-filter-input" x-model="name" x-on:input="dirty = true" maxlength="100" required>
            <span class="sfk-filter-error" x-show="errors.name" x-text="errors.name" role="alert"></span>
        </label>

        <div class="sfk-designer-tools">
            <div class="sfk-designer-pages" role="tablist" aria-label="ページ" x-show="pages.length > 1">
                <template x-for="(p, index) in pages" :key="index">
                    <button type="button" role="tab" class="sfk-designer-page-tab" :class="{ 'is-active': page === index }"
                        :aria-selected="page === index" x-on:click="showPage(index)" x-text="(index + 1) + 'ページ'"></button>
                </template>
            </div>

            <label class="sfk-designer-tool">
                <span class="sfk-designer-caption">表示倍率</span>
                <span class="sfk-filter-select">
                    <select x-model.number="zoom" x-on:change="renderBackground()">
                        <template x-for="z in [0.5, 0.75, 1, 1.25, 1.5, 2]" :key="z">
                            <option :value="z" x-text="Math.round(z * 100) + '%'" :selected="zoom === z"></option>
                        </template>
                    </select>
                </span>
            </label>

            <label class="sfk-settings-check sfk-designer-tool">
                <input type="checkbox" class="sfk-ui-check" x-model="snap">
                1mm単位
            </label>

            <button type="button" class="sfk-designer-link" x-on:click="replaceBackground()">
                <x-filament::icon :icon="Heroicon::OutlinedDocumentArrowUp" class="sfk-designer-link-icon" />背景を差し替える
            </button>

            <span class="sfk-designer-preview">
                <span class="sfk-filter-select">
                    <select x-model="previewRecord" aria-label="プレビューするレコード">
                        <option value="">フィールド名で表示</option>
                        <template x-for="record in config.records" :key="record.key">
                            <option :value="record.key" x-text="record.title"></option>
                        </template>
                    </select>
                </span>
                <button type="button" class="sfk-designer-link" x-on:click="preview()">
                    <x-filament::icon :icon="Heroicon::OutlinedEye" class="sfk-designer-link-icon" />プレビュー
                </button>
            </span>
        </div>

        <div class="sfk-designer-actions">
            <a href="{{ $this->documentsUrl() }}" class="sfk-filter-btn sfk-designer-btn" x-on:click="dirty = false">キャンセル</a>
            <button type="button" class="sfk-filter-btn sfk-filter-btn-submit sfk-designer-btn" x-on:click="save()" :disabled="busy">保存</button>
        </div>
    </div>

    <div class="sfk-designer-body">
        {{-- palette --}}
        <aside class="sfk-designer-palette" aria-label="フィールド">
            <label class="sfk-view-search">
                <x-filament::icon :icon="Heroicon::MagnifyingGlass" class="sfk-view-search-icon" />
                <span class="fi-sr-only">フィールドを検索</span>
                <input type="search" class="sfk-filter-input" x-model="search" aria-label="フィールドを検索">
            </label>

            <h3 class="sfk-designer-group">フィールド</h3>
            <ul class="sfk-designer-items">
                <template x-for="field in filtered(config.fields)" :key="field.value">
                    <li>
                        <button type="button" class="sfk-designer-item" draggable="true" :title="field.label"
                            x-on:dragstart="dragStart($event, { type: 'field', field: field.value })"
                            x-on:click="addAt({ type: 'field', field: field.value }, null)" x-text="field.label"></button>
                    </li>
                </template>
            </ul>

            <template x-if="config.tables.length > 0">
                <div>
                    <h3 class="sfk-designer-group">テーブル</h3>
                    <ul class="sfk-designer-items">
                        <template x-for="table in config.tables" :key="table.name">
                            <li x-show="matches(table.label)">
                                <button type="button" class="sfk-designer-item is-table" draggable="true" :title="table.label"
                                    x-on:dragstart="dragStart($event, { type: 'table', table: table.name })"
                                    x-on:click="addAt({ type: 'table', table: table.name }, null)">
                                    <x-filament::icon :icon="Heroicon::OutlinedTableCells" class="sfk-designer-item-icon" />
                                    <span x-text="table.label"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>

            <h3 class="sfk-designer-group">その他</h3>
            <ul class="sfk-designer-items">
                <li x-show="matches('固定テキスト')">
                    <button type="button" class="sfk-designer-item is-text" draggable="true"
                        x-on:dragstart="dragStart($event, { type: 'text' })" x-on:click="addAt({ type: 'text' }, null)">固定テキスト</button>
                </li>
                <template x-for="field in filtered(config.system)" :key="field.value">
                    <li>
                        <button type="button" class="sfk-designer-item" draggable="true"
                            x-on:dragstart="dragStart($event, { type: 'field', field: field.value })"
                            x-on:click="addAt({ type: 'field', field: field.value }, null)" x-text="field.label"></button>
                    </li>
                </template>
            </ul>
        </aside>

        {{-- the page --}}
        <div class="sfk-designer-stage" x-ref="stage" x-on:pointerdown.self="select(null)">
            <div class="sfk-designer-sheet" x-ref="sheet" :style="sheetStyle()"
                x-on:dragover.prevent="$event.dataTransfer.dropEffect = 'copy'" x-on:drop.prevent="drop($event)"
                x-on:pointerdown.self="select(null)">
                <canvas x-ref="canvas" class="sfk-designer-background" aria-hidden="true"></canvas>
                <template x-for="element in pageElements()" :key="element.id">
                    <div class="sfk-designer-element"
                        :class="['is-' + element.type, { 'is-selected': selectedId === element.id, 'has-border': element.border }]"
                        :style="elementStyle(element)" :title="describe(element)"
                        x-on:pointerdown.stop.prevent="startMove($event, element)">
                        <template x-if="element.type !== 'table'">
                            <span class="sfk-designer-text" :style="textStyle(element)" x-text="displayText(element)"></span>
                        </template>
                        <template x-if="element.type === 'table'">
                            <span class="sfk-designer-table" :style="tableStyle(element)">
                                <template x-for="(column, index) in element.columns" :key="index">
                                    <span class="sfk-designer-column" :style="columnStyle(element, column)" x-text="columnLabel(element, column.field)"></span>
                                </template>
                            </span>
                        </template>
                        <span class="sfk-designer-resize" x-show="selectedId === element.id"
                            x-on:pointerdown.stop.prevent="startResize($event, element)" title="サイズを変更"></span>
                    </div>
                </template>
            </div>
        </div>

        {{-- properties of the selected element --}}
        <aside class="sfk-designer-props" aria-label="プロパティ">
            <template x-if="sel">
                <div class="sfk-designer-form" x-on:input="dirty = true" x-on:change="dirty = true">
                    <h3 class="sfk-designer-group" x-text="typeLabel(sel)"></h3>

                    <template x-if="sel.type === 'field'">
                        <div class="sfk-designer-fields">
                            <label class="sfk-designer-prop is-wide">
                                <span class="sfk-designer-caption">フィールド</span>
                                <span class="sfk-filter-select">
                                    <select x-model="sel.field">
                                        <template x-for="field in [...config.fields, ...config.system]" :key="field.value">
                                            <option :value="field.value" x-text="field.label" :selected="sel.field === field.value"></option>
                                        </template>
                                    </select>
                                </span>
                            </label>
                            <label class="sfk-designer-prop is-wide">
                                <span class="sfk-designer-caption">書式</span>
                                <span class="sfk-filter-select">
                                    <select x-model="sel.format">
                                        <template x-for="format in config.formats" :key="format.value">
                                            <option :value="format.value" x-text="format.label" :selected="sel.format === format.value"></option>
                                        </template>
                                    </select>
                                </span>
                            </label>
                            <label class="sfk-designer-prop">
                                <span class="sfk-designer-caption">前に付ける文字</span>
                                <input type="text" class="sfk-filter-input" x-model="sel.prefix" maxlength="40">
                            </label>
                            <label class="sfk-designer-prop">
                                <span class="sfk-designer-caption">後に付ける文字</span>
                                <input type="text" class="sfk-filter-input" x-model="sel.suffix" maxlength="40">
                            </label>
                        </div>
                    </template>

                    <template x-if="sel.type === 'text'">
                        <label class="sfk-designer-prop is-wide">
                            <span class="sfk-designer-caption">テキスト</span>
                            <textarea class="sfk-filter-input sfk-designer-textarea" x-model="sel.text" rows="3" maxlength="1000"></textarea>
                        </label>
                    </template>

                    <div class="sfk-designer-fields">
                        <label class="sfk-designer-prop">
                            <span class="sfk-designer-caption">X (mm)</span>
                            <input type="number" class="sfk-filter-input" x-model.number="sel.x" step="0.5" min="0">
                        </label>
                        <label class="sfk-designer-prop">
                            <span class="sfk-designer-caption">Y (mm)</span>
                            <input type="number" class="sfk-filter-input" x-model.number="sel.y" step="0.5" min="0">
                        </label>
                        <template x-if="sel.type !== 'table'">
                            <label class="sfk-designer-prop">
                                <span class="sfk-designer-caption">幅 (mm)</span>
                                <input type="number" class="sfk-filter-input" x-model.number="sel.w" step="0.5" min="1">
                            </label>
                        </template>
                        <template x-if="sel.type !== 'table'">
                            <label class="sfk-designer-prop">
                                <span class="sfk-designer-caption">高さ (mm)</span>
                                <input type="number" class="sfk-filter-input" x-model.number="sel.h" step="0.5" min="1">
                            </label>
                        </template>
                        <template x-if="sel.type === 'table'">
                            <label class="sfk-designer-prop">
                                <span class="sfk-designer-caption">行の高さ (mm)</span>
                                <input type="number" class="sfk-filter-input" x-model.number="sel.rowHeight" step="0.5" min="2">
                            </label>
                        </template>
                        <template x-if="sel.type === 'table'">
                            <label class="sfk-designer-prop">
                                <span class="sfk-designer-caption">1ページの行数</span>
                                <input type="number" class="sfk-filter-input" x-model.number="sel.rows" step="1" min="1" max="200">
                            </label>
                        </template>
                        <template x-if="pages.length > 1">
                            <label class="sfk-designer-prop">
                                <span class="sfk-designer-caption">ページ</span>
                                <span class="sfk-filter-select">
                                    <select x-model.number="sel.page" x-on:change="showPage(sel.page)">
                                        <template x-for="(p, index) in pages" :key="index">
                                            <option :value="index" x-text="(index + 1) + 'ページ'" :selected="sel.page === index"></option>
                                        </template>
                                    </select>
                                </span>
                            </label>
                        </template>
                    </div>

                    <div class="sfk-designer-fields">
                        <label class="sfk-designer-prop">
                            <span class="sfk-designer-caption">フォント</span>
                            <span class="sfk-filter-select">
                                <select x-model="sel.font">
                                    <template x-for="font in config.fonts" :key="font.value">
                                        <option :value="font.value" x-text="font.label" :selected="sel.font === font.value"></option>
                                    </template>
                                </select>
                            </span>
                        </label>
                        <label class="sfk-designer-prop">
                            <span class="sfk-designer-caption">サイズ (pt)</span>
                            <input type="number" class="sfk-filter-input" x-model.number="sel.size" step="0.5" min="4" max="72">
                        </label>
                        <label class="sfk-designer-prop">
                            <span class="sfk-designer-caption">文字の色</span>
                            <input type="color" class="sfk-designer-color" x-model="sel.color">
                        </label>
                        <label class="sfk-settings-check sfk-designer-prop is-check">
                            <input type="checkbox" class="sfk-ui-check" x-model="sel.bold">
                            太字
                        </label>
                    </div>

                    <div class="sfk-designer-fields">
                        <template x-if="sel.type !== 'table'">
                            <label class="sfk-designer-prop">
                                <span class="sfk-designer-caption">横の配置</span>
                                <span class="sfk-filter-select">
                                    <select x-model="sel.align">
                                        <template x-for="align in config.aligns" :key="align.value">
                                            <option :value="align.value" x-text="align.label" :selected="sel.align === align.value"></option>
                                        </template>
                                    </select>
                                </span>
                            </label>
                        </template>
                        <template x-if="sel.type !== 'table'">
                            <label class="sfk-designer-prop">
                                <span class="sfk-designer-caption">縦の配置</span>
                                <span class="sfk-filter-select">
                                    <select x-model="sel.valign">
                                        <template x-for="valign in config.valigns" :key="valign.value">
                                            <option :value="valign.value" x-text="valign.label" :selected="sel.valign === valign.value"></option>
                                        </template>
                                    </select>
                                </span>
                            </label>
                        </template>
                        <label class="sfk-designer-prop is-wide">
                            <span class="sfk-designer-caption">長い文字</span>
                            <span class="sfk-filter-select">
                                <select x-model="sel.overflow">
                                    <template x-for="overflow in config.overflows" :key="overflow.value">
                                        <option :value="overflow.value" x-text="overflow.label" :selected="sel.overflow === overflow.value"></option>
                                    </template>
                                </select>
                            </span>
                        </label>
                        <label class="sfk-settings-check sfk-designer-prop is-check">
                            <input type="checkbox" class="sfk-ui-check" x-model="sel.border">
                            罫線を引く
                        </label>
                    </div>

                    {{-- columns of a table --}}
                    <template x-if="sel.type === 'table'">
                        <div class="sfk-designer-columns">
                            <h3 class="sfk-designer-group">列</h3>
                            <template x-for="(column, index) in sel.columns" :key="index">
                                <div class="sfk-designer-column-row">
                                    <span class="sfk-filter-select sfk-designer-column-field">
                                        <select x-model="column.field" :aria-label="'列 ' + (index + 1)">
                                            <template x-for="option in tableColumns(sel.table)" :key="option.name">
                                                <option :value="option.name" x-text="option.label" :selected="column.field === option.name"></option>
                                            </template>
                                        </select>
                                    </span>
                                    <input type="number" class="sfk-filter-input sfk-designer-column-width" x-model.number="column.width" step="0.5" min="1" aria-label="幅 (mm)" title="幅 (mm)">
                                    <span class="sfk-filter-select sfk-designer-column-align">
                                        <select x-model="column.align" aria-label="横の配置">
                                            <template x-for="align in config.aligns" :key="align.value">
                                                <option :value="align.value" x-text="align.label" :selected="column.align === align.value"></option>
                                            </template>
                                        </select>
                                    </span>
                                    <span class="sfk-filter-select sfk-designer-column-format">
                                        <select x-model="column.format" aria-label="書式">
                                            <template x-for="format in config.formats" :key="format.value">
                                                <option :value="format.value" x-text="format.label" :selected="column.format === format.value"></option>
                                            </template>
                                        </select>
                                    </span>
                                    <span class="sfk-designer-column-tools">
                                        <button type="button" class="sfk-designer-icon-btn" x-on:click="moveColumn(index, -1)" :disabled="index === 0" title="左へ移動">
                                            <x-filament::icon :icon="Heroicon::ChevronUp" class="sfk-designer-icon" /><span class="fi-sr-only">左へ移動</span>
                                        </button>
                                        <button type="button" class="sfk-designer-icon-btn" x-on:click="moveColumn(index, 1)" :disabled="index === sel.columns.length - 1" title="右へ移動">
                                            <x-filament::icon :icon="Heroicon::ChevronDown" class="sfk-designer-icon" /><span class="fi-sr-only">右へ移動</span>
                                        </button>
                                        <button type="button" class="sfk-designer-icon-btn" x-on:click="removeColumn(index)" :disabled="sel.columns.length === 1" title="削除">
                                            <x-filament::icon :icon="Heroicon::XMark" class="sfk-designer-icon" /><span class="fi-sr-only">削除</span>
                                        </button>
                                    </span>
                                </div>
                            </template>
                            <button type="button" class="sfk-filter-link" x-on:click="addColumn()">列を追加</button>
                        </div>
                    </template>

                    <div class="sfk-designer-element-actions">
                        <button type="button" class="sfk-designer-link" x-on:click="duplicate()">
                            <x-filament::icon :icon="Heroicon::OutlinedDocumentDuplicate" class="sfk-designer-link-icon" />複製
                        </button>
                        <button type="button" class="sfk-designer-link is-danger" x-on:click="remove()">
                            <x-filament::icon :icon="Heroicon::OutlinedTrash" class="sfk-designer-link-icon" />削除
                        </button>
                    </div>
                </div>
            </template>
        </aside>
    </div>

    {{-- プレビュー: the unsaved layout goes to a new tab --}}
    <form x-ref="previewForm" method="post" target="_blank" :action="config.previewUrl" hidden>
        <input type="hidden" name="_token" :value="config.csrf">
        <input type="hidden" name="layout" x-ref="previewLayout">
        <input type="hidden" name="record" x-ref="previewRecordInput">
    </form>
</div>
