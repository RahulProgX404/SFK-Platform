<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages\Concerns\Lists;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Cancel;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Modules\Core\Application\Records\RecordDeletion;
use Modules\Core\Filament\Support\DeleteImpact;

/**
 * 「…」 → 一括削除 / 選択したレコードを削除 of an app list: asked twice (what goes with the records, then that it
 * cannot be undone), deleted one by one in one transaction (RecordDeletion); records still used are kept.
 *
 * Part of Modules\Core\Filament\AppPages\AppListRecords (the shared app list); uses its views, fields and access rights.
 */
trait BulkDeletion
{
    /** 一括削除: on in その他の設定, for administrators who may delete the app's records. */
    public function canBulkDelete(): bool
    {
        return $this->appSetting('bulk_delete_enabled') && $this->isAppAdministrator() && $this->appResource()::canDeleteAny();
    }

    /**
     * 「…」 → 一括削除 / 選択したレコードを削除 (red): with no row checked it deletes every record of the list as
     * filtered and searched (一括削除: on in その他の設定, administrators), with rows checked only those (users with
     * 削除). It asks twice — how many and what goes with them, then that it cannot be undone (confirmBulkDelete).
     */
    private function bulkDeleteAction(): Action
    {
        return Action::make('bulkDelete')
            // the label follows the checked rows ($store.sfkCheckedRows)
            ->label(new HtmlString('<span x-show="$store.sfkCheckedRows === 0">一括削除</span><span x-show="$store.sfkCheckedRows > 0" x-cloak>選択したレコードを削除</span>'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => $this->appResource()::canDeleteAny())
            // without 一括削除 the item is there for checked rows only
            ->extraAttributes(fn (): array => $this->canBulkDelete() ? [] : ['x-show' => '$store.sfkCheckedRows > 0'])
            // mounted through the table, so the checked rows come along
            ->accessSelectedRecords()
            ->alpineClickHandler("(t => t ? Alpine.\$data(t).mountAction('bulkDelete') : \$wire.mountAction('bulkDelete'))(\$wire.\$el.querySelector('[x-data^=filamentTable]'))")
            ->mountUsing(function (Action $action): void {
                if ($action->getSelectedRecords()->isNotEmpty()) {
                    return;
                }
                if (! $this->canBulkDelete()) {
                    Notification::make()->warning()->title('レコードを選択してください。')->send();

                    throw new Cancel;
                }
                if (($this->getFilteredTableQuery()?->count() ?? 0) === 0) {
                    Notification::make()->warning()->title('削除するレコードがありません。')->send();

                    throw new Cancel;
                }
            })
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalIconColor('danger')
            ->modalWidth('md')
            ->modalHeading(fn (Action $action): string => $action->getSelectedRecords()->isNotEmpty()
                ? '選択した'.$action->getSelectedRecords()->count().'件のレコードを削除しますか？'
                : number_format($this->getFilteredTableQuery()?->count() ?? 0).'件のレコードを一括削除しますか？')
            ->modalDescription(fn (Action $action): HtmlString => $action->getSelectedRecords()->isNotEmpty()
                ? DeleteImpact::description($this->appResource()::getModel(), $action->getSelectedRecords()->map(fn (Model $record): int|string => $record->getKey())->values()->all())
                : DeleteImpact::description($this->appResource()::getModel(), $this->bulkDeleteKeys(['all' => true]),
                    '一覧に表示されているすべてのレコード（絞り込み・検索の結果）が削除されます。'.DeleteImpact::PARTS))
            ->modalSubmitAction(fn (Action $action): Action => $action->label('削除する')->color('danger'))
            ->modalCancelActionLabel('キャンセル')
            ->action(fn (Action $action) => $this->replaceMountedAction('confirmBulkDelete', $action->getSelectedRecords()->isNotEmpty()
                ? ['keys' => $action->getSelectedRecords()->map(fn (Model $record): int|string => $record->getKey())->values()->all()]
                : ['all' => true]));
    }

    /** 一括削除 / 選択したレコードを削除, second step: 「この操作は取り消せません」, then the records are deleted. */
    public function confirmBulkDeleteAction(): Action
    {
        return Action::make('confirmBulkDelete')
            ->visible(fn (array $arguments): bool => $this->appResource()::canDeleteAny() && (! ($arguments['all'] ?? false) || $this->canBulkDelete()))
            ->requiresConfirmation()
            ->color('danger')
            ->modalIcon(Heroicon::OutlinedTrash)
            ->modalIconColor('danger')
            ->modalWidth('md')
            ->modalHeading(fn (array $arguments): string => '本当に'.number_format(count($this->bulkDeleteKeys($arguments))).'件のレコードを削除しますか？')
            ->modalDescription('この操作は取り消せません。')
            ->modalSubmitActionLabel('削除する')
            ->modalCancelActionLabel('キャンセル')
            ->action(function (array $arguments): void {
                $this->deleteRecords($this->bulkDeleteKeys($arguments));
                $this->deselectAllTableRecords();
            });
    }

    /**
     * The records a 一括削除 / 選択したレコードを削除 is about: the checked ones, or every record of the list as filtered
     * and searched (一括削除 only).
     *
     * @param  array<string, mixed>  $arguments
     * @return list<int|string>
     */
    private function bulkDeleteKeys(array $arguments): array
    {
        if (! ($arguments['all'] ?? false)) {
            return array_values(array_filter((array) ($arguments['keys'] ?? []), is_scalar(...)));
        }

        $query = $this->canBulkDelete() ? $this->getFilteredTableQuery() : null;

        return $query === null ? [] : $query->pluck($query->getModel()->getQualifiedKeyName())->all();
    }

    /**
     * Deletes the records the user may delete, one by one so their parts go with them; records other records still
     * use are kept (RecordDeletion) and counted in the message.
     *
     * @param  list<int|string>  $keys
     */
    private function deleteRecords(array $keys): void
    {
        $resource = $this->appResource();
        $deletion = app(RecordDeletion::class);
        $deleted = 0;
        $kept = [];

        // one transaction (R4): an error part way through deletes nothing; records that may not go are only kept
        DB::transaction(function () use ($keys, $resource, $deletion, &$deleted, &$kept): void {
            foreach (array_chunk($keys, 200) as $chunk) {
                foreach ($resource::getEloquentQuery()->whereKey($chunk)->get() as $record) {
                    if (! $resource::canDelete($record)) {
                        continue;
                    }

                    $reason = $deletion->delete($record);
                    $reason === null ? $deleted++ : $kept[] = $reason;
                }
            }
        });

        $this->cancelInlineEdit();
        $this->resetPage($this->getTablePaginationPageName());

        Notification::make()->title($deleted.'件のレコードを削除しました')->success()->send();
        if ($kept !== []) {
            Notification::make()->danger()->persistent()
                ->title(count($kept).'件のレコードは他のレコードで使われているため削除できませんでした')
                ->body($kept[0])
                ->send();
        }
    }
}
