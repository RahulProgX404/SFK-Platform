<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
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
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (HasTable $livewire, Model $record): bool => $icons($livewire, $record) && $resource::canEdit($record))
            ->url(fn (Model $record): string => $resource::getUrl('edit', ['record' => $record]));
        // a record still used elsewhere is not deleted (GuardedDelete)
        $delete = GuardedDelete::apply(DeleteAction::make())
            ->label('削除する')
            ->icon(Heroicon::OutlinedTrash)
            ->visible(fn (HasTable $livewire, Model $record): bool => $icons($livewire, $record) && $resource::canDelete($record));

        return [
            ActionGroup::make([$edit, $delete])
                ->label('操作')
                ->icon(Heroicon::EllipsisHorizontal)
                ->iconButton()
                ->color('gray')
                ->extraAttributes(['class' => 'sfk-row-menu']),
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
}
