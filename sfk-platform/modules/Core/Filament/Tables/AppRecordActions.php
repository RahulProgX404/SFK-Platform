<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\AppResource;
use Modules\Core\Filament\Support\GuardedDelete;

/**
 * The 「…」 at the end of an app list row (design_ref/app-index.png): 編集する opens the record's edit screen and
 * 削除する deletes it; while a row is edited in place (AppListRecords::startInlineEdit()) it shows 保存 / キャンセル
 * instead. Both follow the app's permissions and are hidden when インライン編集 is switched off in その他の設定.
 */
final class AppRecordActions
{
    /**
     * @param  class-string<AppResource>  $resource
     * @return list<Action|ActionGroup>
     */
    public static function make(string $resource): array
    {
        $editing = fn (HasTable $livewire, Model $record): bool => $livewire instanceof AppListRecords && $livewire->isInlineEditing($record);
        $icons = fn (HasTable $livewire, Model $record): bool => $livewire instanceof AppListRecords
            && $livewire->rowActionsEnabled()
            && ! $livewire->isInlineEditing($record);

        $edit = Action::make('inlineEdit')
            ->label('編集する')
            ->icon(self::icon('edit'))
            ->visible(fn (HasTable $livewire, Model $record): bool => $icons($livewire, $record) && $resource::canEdit($record))
            ->url(fn (Model $record): string => $resource::getUrl('edit', ['record' => $record]));
        // a record still used elsewhere is not deleted (GuardedDelete)
        $delete = GuardedDelete::apply(DeleteAction::make())
            ->label('削除する')
            ->icon(self::icon('delete'))
            // no ⌘/Ctrl+D (DeleteAction's default) on a list: every row bound it to the whole page — 100 listeners, and
            // the browser's bookmark key opened a delete
            ->keyBindings(null)
            ->visible(fn (HasTable $livewire, Model $record): bool => $icons($livewire, $record) && $resource::canDelete($record));

        return [
            ActionGroup::make([$edit, $delete])
                ->label('操作')
                ->icon(self::icon('more'))
                ->iconButton()
                ->color('gray')
                ->extraAttributes(['class' => 'sfk-row-menu'])
                // one small button per row; the page's one menu (core::filament.row-menu-script) opens on it
                ->view('core::filament.tables.row-menu'),
            Action::make('inlineSave')
                ->label('保存')
                ->button()
                ->size('sm')
                ->visible($editing)
                ->action(function (HasTable $livewire): void {
                    if ($livewire instanceof AppListRecords) {
                        $livewire->saveInlineEdit();
                    }
                }),
            Action::make('inlineCancel')
                ->label('キャンセル')
                ->button()
                ->size('sm')
                ->color('gray')
                ->visible($editing)
                ->action(function (HasTable $livewire): void {
                    if ($livewire instanceof AppListRecords) {
                        $livewire->cancelInlineEdit();
                    }
                }),
        ];
    }

    /** An icon of the row menu drawn by CSS (.sfk-i-<name>, core::filament.theme): the rows carry no SVG each. */
    private static function icon(string $name): HtmlString
    {
        return new HtmlString('<span class="sfk-i sfk-i-'.$name.'"></span>');
    }
}
