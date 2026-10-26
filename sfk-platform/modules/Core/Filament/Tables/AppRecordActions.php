<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Tables;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\AppResource;
use Modules\Core\Filament\Support\GuardedDelete;

/**
 * The actions at the end of an app list row, like the legacy list: the pencil (編集する) edits the row in place
 * (インライン編集, or opens the edit screen when the app has no inline fields) and × (削除する) deletes it; while a
 * row is edited it shows 保存 / キャンセル. Both icons follow the app's permissions and are hidden when インライン編集 is
 * switched off in その他の設定.
 */
final class AppRecordActions
{
    /**
     * @param  class-string<AppResource>  $resource
     * @return list<Action>
     */
    public static function make(string $resource): array
    {
        $editing = fn (HasTable $livewire, Model $record): bool => $livewire instanceof AppListRecords && $livewire->isInlineEditing($record);
        $icons = fn (HasTable $livewire, Model $record): bool => $livewire instanceof AppListRecords
            && $livewire->rowActionsEnabled()
            && ! $livewire->isInlineEditing($record);

        return [
            Action::make('inlineEdit')
                ->label('編集する')
                ->icon(Heroicon::OutlinedPencil)
                ->iconButton()
                ->visible(fn (HasTable $livewire, Model $record): bool => $icons($livewire, $record) && $resource::canEdit($record))
                ->url(fn (HasTable $livewire, Model $record): ?string => $livewire instanceof AppListRecords && $livewire->inlineFields() === []
                    ? $resource::getUrl('edit', ['record' => $record])
                    : null)
                ->action(function (HasTable $livewire, Model $record): void {
                    if ($livewire instanceof AppListRecords) {
                        $livewire->startInlineEdit($record);
                    }
                }),
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
            // a record still used elsewhere is not deleted (GuardedDelete)
            GuardedDelete::apply(DeleteAction::make())
                ->label('削除する')
                ->icon(Heroicon::XMark)
                ->iconButton()
                ->visible(fn (HasTable $livewire, Model $record): bool => $icons($livewire, $record) && $resource::canDelete($record)),
        ];
    }
}
