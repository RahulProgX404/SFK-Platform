{{--
    「…」 of an app list row (Modules\Core\Filament\Tables\AppRecordActions): one small button carrying its items — 編集する
    (a link) and 削除する (Filament's delete action of the table, mounted with its confirmations) — and one menu for the
    whole page drawn by core::filament.row-menu-script when it is clicked (2026-11-09, speed: a list of 100 rows carried
    100 dropdown components). The actions stay defined on the table, so Filament still checks and runs them.
    $group: the ActionGroup of the row
--}}
@php
    $items = [];
    $recordKey = null;
    foreach ($group->getActions() as $action) {
        if (! $action instanceof \Filament\Actions\Action || ! $action->isVisible()) {
            continue;
        }
        $recordKey ??= $action->getRecord()?->getKey();
        $url = $action->getUrl();
        $items[] = $url !== null
            ? ['l' => (string) $action->getLabel(), 'u' => $url, 'i' => 'edit']
            : ['l' => (string) $action->getLabel(), 'a' => $action->getName(), 'i' => $action->getName() === 'delete' ? 'delete' : 'more', 'd' => $action->getColor() === 'danger'];
    }
@endphp

@if ($items !== [] && $recordKey !== null)
    <button type="button" class="sfk-row-menu fi-icon-btn fi-size-md" title="{{ $group->getLabel() }}" aria-label="{{ $group->getLabel() }}" aria-haspopup="menu"
        aria-expanded="false" data-sfk-row-menu="{{ json_encode(['k' => (string) $recordKey, 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">
        <span class="fi-icon fi-size-md"><span class="sfk-i sfk-i-more"></span></span>
    </button>
@endif
