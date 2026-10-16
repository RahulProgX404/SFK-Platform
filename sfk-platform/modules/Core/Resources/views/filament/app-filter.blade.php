{{--
    絞り込む dialog of an app list (Modules\Core\Filament\AppPages\AppListRecords::filterFields()), in the legacy layout
    (design_ref/filter1-3.png): 条件 rows of field / comparison / value with (+) below and すべてクリア, the
    すべての条件を満たす / いずれかの条件を満たす choice once there are two rows, ソート rows, and キャンセル | 保存 適用.
    Choice and user values are picked from a list box ("--" = blank), dates from the calendar (今日 / 選択を解除).
    Behaviour: the sfkAppFilter Alpine component (core::filament.app-filter-script).
--}}
@php
    use Filament\Support\Icons\Heroicon;
@endphp

<div
    wire:ignore
    x-data="sfkAppFilter(@js($page->filterDialog()))"
    x-on:sfk-app-filter-open.window="open($event.detail)"
    class="sfk-filter"
>
    <template x-teleport="body">
        <div x-show="isOpen" x-cloak x-on:keydown.escape="naming ? (naming = false) : close()" class="sfk-filter-backdrop">
            <div class="sfk-filter-dialog" role="dialog" aria-modal="true" aria-labelledby="sfk-filter-title" x-trap.noscroll="isOpen">
                <header class="sfk-filter-head">
                    <h2 id="sfk-filter-title" class="sfk-filter-title">絞り込む</h2>
                    <button type="button" class="sfk-filter-close" x-on:click="close()" title="閉じる">
                        <x-filament::icon :icon="Heroicon::XMark" class="sfk-filter-close-icon" />
                        <span class="fi-sr-only">閉じる</span>
                    </button>
                </header>

                <div class="sfk-filter-body">
                    @include('core::filament.filter-editor', ['conditionsLabel' => '条件', 'idPrefix' => 'sfk-filter'])
                </div>

                <footer class="sfk-filter-foot">
                    <button type="button" class="sfk-filter-btn" x-on:click="close()">キャンセル</button>
                    <span class="sfk-filter-foot-end">
                        <template x-if="config.canSave">
                            <button type="button" class="sfk-filter-btn" x-on:click="startSave()" :disabled="busy">保存</button>
                        </template>
                        <button type="button" class="sfk-filter-btn sfk-filter-btn-submit" x-on:click="apply()" :disabled="busy">適用</button>
                    </span>
                </footer>

                {{-- 保存: 一覧名 of the new list view --}}
                <div class="sfk-filter-name-backdrop" x-show="naming" x-cloak>
                    <form class="sfk-filter-name" x-on:submit.prevent="save()" role="dialog" aria-modal="true" aria-labelledby="sfk-filter-name-title">
                        <h2 id="sfk-filter-name-title" class="sfk-filter-title">一覧を保存</h2>
                        <div class="sfk-filter-name-body">
                            <label for="sfk-filter-name-input" class="sfk-filter-label">一覧名<span class="sfk-filter-required" aria-hidden="true">*</span></label>
                            <input id="sfk-filter-name-input" type="text" class="sfk-filter-input sfk-filter-name-input" x-model="viewName" x-ref="viewName" maxlength="64" required>
                            <p class="sfk-filter-error" x-show="saveError" x-text="saveError" role="alert"></p>
                        </div>
                        <div class="sfk-filter-foot">
                            <button type="button" class="sfk-filter-btn" x-on:click="naming = false">キャンセル</button>
                            <button type="submit" class="sfk-filter-btn sfk-filter-btn-submit" :disabled="busy">保存</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </template>
</div>
