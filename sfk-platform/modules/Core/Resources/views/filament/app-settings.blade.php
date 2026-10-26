{{--
    アプリの設定 → 設定 (Modules\Core\Filament\AppPages\AppSettings): アクセス権 (アプリ / レコード / フィールド) and 高度な設定 → その他の設定
    (インライン編集, コメント, 「レコードの再利用」, 「変更履歴」, 一括削除) with 保存; tab 帳票出力 (core::filament.documents.*).
--}}
@php
    use Filament\Support\Icons\Heroicon;
@endphp

<x-filament-panels::page>
    @if ($this->isDocumentsTab())
        {{-- 帳票出力: the designer of a template, else the templates and 出力設定 --}}
        @if ($this->designedTemplate())
            @include('core::filament.documents.designer')
        @else
            @include('core::filament.documents.settings')
        @endif
    @else
    <div class="sfk-settings">
        @if ($permissionsUrl = $this->permissionsUrl())
            <section class="sfk-settings-group" aria-labelledby="sfk-settings-permissions">
                <h2 id="sfk-settings-permissions" class="sfk-settings-group-title">アクセス権</h2>
                @php($counts = $this->accessCounts())
                <div class="sfk-access-links">
                    @foreach ([
                        ['url' => $permissionsUrl, 'icon' => Heroicon::OutlinedKey, 'title' => 'アプリ', 'count' => '権限 '.$counts['app'].'件'],
                        ['url' => $this->accessUrl('records'), 'icon' => Heroicon::OutlinedQueueList, 'title' => 'レコード', 'count' => '設定 '.$counts['records'].'件'],
                        ['url' => $this->accessUrl('fields'), 'icon' => Heroicon::OutlinedViewColumns, 'title' => 'フィールド', 'count' => '設定 '.$counts['fields'].'件'],
                    ] as $link)
                        @if ($link['url'])
                            <a href="{{ $link['url'] }}" class="sfk-access-link">
                                <span class="sfk-access-link-tile" aria-hidden="true"><x-filament::icon :icon="$link['icon']" class="sfk-access-link-icon" /></span>
                                <span class="sfk-access-link-text">
                                    <span class="sfk-access-link-title">{{ $link['title'] }}</span>
                                    <span class="sfk-access-link-count">{{ $link['count'] }}</span>
                                </span>
                                <x-filament::icon :icon="Heroicon::ChevronRight" class="sfk-access-link-arrow" />
                            </a>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif

        <section class="sfk-settings-group" id="misc" aria-labelledby="sfk-settings-misc">
            <h2 id="sfk-settings-misc" class="sfk-settings-group-title">高度な設定</h2>
            <form class="sfk-filter-section sfk-settings-form" wire:submit="saveMisc">
                <h3 class="sfk-filter-label sfk-settings-form-title">その他の設定</h3>
                <label class="sfk-settings-check">
                    <input type="checkbox" class="sfk-ui-check" wire:model="inlineEditEnabled">
                    インライン編集を有効にする
                </label>
                <label class="sfk-settings-check">
                    <input type="checkbox" class="sfk-ui-check" wire:model="commentsEnabled">
                    コメント機能を有効にする
                </label>
                <label class="sfk-settings-check">
                    <input type="checkbox" class="sfk-ui-check" wire:model="duplicateEnabled">
                    「レコードの再利用」機能を有効にする
                </label>
                <label class="sfk-settings-check">
                    <input type="checkbox" class="sfk-ui-check" wire:model="historyEnabled">
                    レコードの変更履歴を記録する
                </label>
                <label class="sfk-settings-check">
                    <input type="checkbox" class="sfk-ui-check" wire:model="bulkDeleteEnabled">
                    一括削除を有効にする
                </label>
                <div class="sfk-settings-form-foot">
                    <button type="submit" class="sfk-filter-btn sfk-filter-btn-submit">保存</button>
                </div>
            </form>
        </section>
    </div>
    @endif
</x-filament-panels::page>
