<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages\Concerns\Lists;

use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Cancel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Js;
use Modules\Core\Application\Documents\AppDocuments;
use Modules\Core\Application\Documents\PrintJobs;
use Modules\Core\Application\Export\ListCsvExport;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Documents\OutputDelivery;
use Modules\Core\Domain\Documents\OutputMode;
use Modules\Core\Domain\Documents\OutputPlacement;
use Modules\Core\Filament\Support\DocumentNameField;
use Modules\Core\Filament\Support\MenuButtons;
use Modules\Core\Models\DocumentOutput;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * エクスポート of an app list: 帳票出力 of the listed or checked records (アプリの設定 → 帳票出力, PrintJobs) and
 * ⚙ → ファイルに書き出す (ListCsvExport).
 *
 * Part of Modules\Core\Filament\AppPages\AppListRecords (the shared app list); uses its views, fields and access rights.
 */
trait ListExports
{
    /**
     * 「ファイルに書き出す」 (legacy ファイル書き出し, ListCsvExport) for users with the app's ファイル書き出し permission: the list as
     * filtered, searched and sorted, with its database columns the user may see. An app with its own export (an option
     * action named `export`, e.g. 01. 取引先マスタ) keeps it instead.
     */
    private function exportCsvAction(): Action
    {
        return Action::make('exportCsv')
            ->label('ファイルに書き出す')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->visible(fn (): bool => $this->appResource()::appCan(AppAbility::Export)
                && ! in_array('export', array_map(fn (Action $action): string => (string) $action->getName(), $this->optionActions()), true))
            ->action(fn (): StreamedResponse => app(ListCsvExport::class)->download(
                ($query = $this->getFilteredSortedTableQuery()) instanceof Builder ? $query : $this->appResource()::getEloquentQuery(),
                $this->exportColumns(),
                $this->appResource()::displayName(),
            ));
    }

    /** @return array<string, string> the database columns of the list the user may see (attribute => label), id first */
    public function exportColumns(): array
    {
        $model = $this->appResource()::getEloquentQuery()->getModel();
        $stored = self::databaseColumns($model);
        $hidden = $this->hiddenFields();
        $columns = in_array($model->getKeyName(), $stored, true) ? [$model->getKeyName() => 'レコード番号'] : [];
        foreach ($this->listColumns() as $name => $label) {
            if (in_array($name, $stored, true) && ! in_array($name, $hidden, true)) {
                $columns[$name] = $label;
            }
        }

        return $columns;
    }

    /**
     * エクスポート (帳票出力, アプリの設定 → 帳票出力): an entry per 出力設定 of the list — for the records of the list as
     * filtered and searched (asking 「N件のレコードを出力しますか？」), or for the checked rows. 出力設定 with the same
     * ボタン名 are one entry with a sub-menu of their サブボタン名. A ZIP whose ファイル名 has 「{ユーザー選択}」 asks for
     * the field of the names.
     *
     * @return list<Action|ActionGroup>
     */
    private function exportActions(): array
    {
        $listed = collect([...$this->documentOutputsAt(OutputPlacement::ListMenu), ...$this->documentOutputsAt(OutputPlacement::ListSpace)])
            ->unique('id')->values()->all();

        $entries = [];
        foreach (AppDocuments::groups($listed) as $group) {
            $entries[] = MenuButtons::exportEntry(array_map(
                fn (DocumentOutput $output): Action => $this->listedDocumentAction(Action::make('documentList'.$output->id), fn (): DocumentOutput => $output)
                    ->label(count($group) > 1 ? $output->optionLabel() : $output->name),
                $group,
            ), $group[0]->name);
        }
        foreach (AppDocuments::groups($this->documentOutputsAt(OutputPlacement::ListChecked)) as $group) {
            $entries[] = MenuButtons::exportEntry(array_map(
                fn (DocumentOutput $output): Action => $this->checkedDocumentAction($output, count($group) > 1 ? $output->optionLabel() : $output->name.'（選択したレコード）'),
                $group,
            ), $group[0]->name.'（選択したレコード）');
        }

        return $entries;
    }

    private function checkedDocumentAction(DocumentOutput $output, string $label): Action
    {
        $action = Action::make('documentChecked'.$output->id)->label($label)->icon(Heroicon::OutlinedPrinter);
        if ($output->asksForNameField(forList: true)) {
            $action->modalIcon(Heroicon::OutlinedPrinter)
                ->modalWidth('md')
                ->modalHeading($output->name)
                ->modalSubmitActionLabel('出力する')
                ->modalCancelActionLabel('キャンセル')
                ->schema([DocumentNameField::make($this->appResource())]);
        }

        return self::onCheckedRows($action->action(function (Collection $records, array $data) use ($output): void {
            if ($records->count() > DocumentOutput::MAX_RECORDS) {
                Notification::make()->danger()->title(DocumentOutput::MAX_RECORDS.'件以下になるようにレコードを選択してください。')->send();

                return;
            }

            $this->openDocuments($output, $records->map(fn (Model $record): int|string => $record->getKey())->values()->all(), self::chosenField($data));
        }));
    }

    /** @param  array<string, mixed>  $data */
    private static function chosenField(array $data): ?string
    {
        return is_string($data['name_field'] ?? null) && $data['name_field'] !== '' ? $data['name_field'] : null;
    }

    /** @return list<DocumentOutput> the 出力設定 the user gets at the place */
    private function documentOutputsAt(OutputPlacement $placement): array
    {
        $user = auth()->user();

        return AppDocuments::at($this->appResource()::app(), $user instanceof User ? $user : null, $placement);
    }

    /**
     * A 出力 of the listed records: asks 「N件のレコードを出力しますか？」 (nothing listed, or more than the limit: a
     * message instead), then opens the 帳票.
     *
     * @param  Closure(array<string, mixed>): ?DocumentOutput  $output
     */
    private function listedDocumentAction(Action $action, Closure $output): Action
    {
        return $action
            ->icon(Heroicon::OutlinedPrinter)
            ->modalIcon(Heroicon::OutlinedPrinter)
            ->modalWidth('md')
            ->modalHeading(fn (): string => number_format($this->getFilteredTableQuery()?->count() ?? 0).'件のレコードを出力しますか？')
            ->modalSubmitActionLabel('出力する')
            ->modalCancelActionLabel('キャンセル')
            // a ZIP named with 「{ユーザー選択}」: which field
            ->schema(fn (array $arguments): array => $output($arguments)?->asksForNameField(forList: true) === true
                ? [DocumentNameField::make($this->appResource())]
                : [])
            ->mountUsing(function (array $arguments, ?Schema $schema) use ($output): void {
                $count = $output($arguments) === null ? 0 : ($this->getFilteredTableQuery()?->count() ?? 0);
                if ($count === 0) {
                    Notification::make()->warning()->title('出力するレコードがありません。')->send();

                    throw new Cancel;
                }
                if ($count > DocumentOutput::MAX_RECORDS) {
                    Notification::make()->danger()->title(DocumentOutput::MAX_RECORDS.'件以下になるように絞り込んでください。')->send();

                    throw new Cancel;
                }

                $schema?->fill();
            })
            ->action(function (array $arguments, array $data) use ($output): void {
                $document = $output($arguments);
                $query = $this->getFilteredSortedTableQuery();
                if ($document === null || $query === null) {
                    return;
                }

                $this->openDocuments($document, $query->limit(DocumentOutput::MAX_RECORDS)->pluck($query->getModel()->getQualifiedKeyName())->all(), self::chosenField($data));
            });
    }

    /**
     * Keeps the records for the 出力設定 (PrintJobs) and opens their 帳票 in a new tab, or downloads it.
     *
     * @param  list<int|string>  $keys
     * @param  string|null  $nameField  the field chosen for 「{ユーザー選択}」 of the ファイル名
     */
    private function openDocuments(DocumentOutput $output, array $keys, ?string $nameField = null): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        $url = route('core.documents.print', ['token' => app(PrintJobs::class)->store($user->id, $output->id, $keys, $nameField)]);
        $opens = $output->delivery === OutputDelivery::Open && $output->mode === OutputMode::Merged;

        $this->js($opens ? 'window.open('.Js::from($url).', "_blank")' : 'window.location.href = '.Js::from($url));
    }
}
