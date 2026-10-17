{{--
    アプリの設定 → 設定 (Modules\Core\Filament\AppPages\AppSettings): アクセス権 and 高度な設定 → その他の設定
    (インライン編集, コメント, 「レコードの再利用」, 「変更履歴」, 一括削除) with 保存.
--}}
@php
    use Filament\Support\Icons\Heroicon;
@endphp

<x-filament-panels::page>
    <div class="sfk-settings">
        @if ($permissionsUrl = $this->permissionsUrl())
            <section class="sfk-settings-group" aria-labelledby="sfk-settings-permissions">
                <h2 id="sfk-settings-permissions" class="sfk-settings-group-title">アクセス権</h2>
                <a href="{{ $permissionsUrl }}" class="sfk-settings-link">
                    <x-filament::icon :icon="Heroicon::OutlinedKey" class="sfk-settings-link-icon" />
                    アプリ
                </a>
            </section>
        @endif

        <section class="sfk-settings-group" id="misc" aria-labelledby="sfk-settings-misc">
            <h2 id="sfk-settings-misc" class="sfk-settings-group-title">高度な設定</h2>
            <form class="sfk-filter-section sfk-settings-form" wire:submit="saveMisc">
                <h3 class="sfk-filter-label sfk-settings-form-title">その他の設定</h3>
                <label class="sfk-settings-check">
                    <input type="checkbox" wire:model="inlineEditEnabled">
                    インライン編集を有効にする
                </label>
                <label class="sfk-settings-check">
                    <input type="checkbox" wire:model="commentsEnabled">
                    コメント機能を有効にする
                </label>
                <label class="sfk-settings-check">
                    <input type="checkbox" wire:model="duplicateEnabled">
                    「レコードの再利用」機能を有効にする
                </label>
                <label class="sfk-settings-check">
                    <input type="checkbox" wire:model="historyEnabled">
                    レコードの変更履歴を記録する
                </label>
                <label class="sfk-settings-check">
                    <input type="checkbox" wire:model="bulkDeleteEnabled">
                    一括削除を有効にする
                </label>
                <div class="sfk-settings-form-foot">
                    <button type="submit" class="sfk-filter-btn sfk-filter-btn-submit">保存</button>
                </div>
            </form>
        </section>
    </div>
</x-filament-panels::page>
