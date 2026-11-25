<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\Entry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Cancel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Application\Records\RecordDeletion;
use Modules\Core\Application\Records\RelationBatch;
use Modules\Core\Filament\AppPages\Concerns\HasAppChrome;
use Modules\Core\Filament\AppPages\Concerns\Records\CommentsPanel;
use Modules\Core\Filament\AppPages\Concerns\Records\HistoryPanel;
use Modules\Core\Filament\AppPages\Concerns\Records\RecordExports;
use Modules\Core\Filament\AppPages\Concerns\Records\RecordNavigation;
use Modules\Core\Filament\Support\DeleteImpact;
use Modules\Core\Filament\Support\GuardedDelete;
use Modules\Core\Filament\Support\MenuButtons;

/**
 * A record of an app, in the legacy layout: app frame; toolbar with the pencil (レコードを編集する), 再利用
 * (レコードを再利用する, その他の設定) and the 「…」 menu (印刷する, 削除する) on the left, gear and ‹ › to the previous /
 * next record (in the app's list order, recordNavigationColumn()) on the right; the panel on the right of the record
 * (collapsed to a bar of icons when the record opens) with the tabs コメント (records implementing HasRecordComments, その他の設定): 宛先 (全員 or users) + text → 書き込む, comment
 * cards newest first (comment-design.png) with their replies under them, 返信 and, on one's own, 編集 / 削除; and 変更履歴
 * (records implementing HasRecordHistory, その他の設定): version cards newest first with 更新内容を表示 (changed fields
 * in green) / このバージョンに戻す, and 最新の内容を表示.
 * 印刷する opens the print view (?print=1): the record without header, menus, comments and history, with 印刷 / 閉じる.
 * The parts live in Concerns\Records: CommentsPanel, HistoryPanel, RecordExports and RecordNavigation.
 *
 * @property-read Schema $commentForm
 */
abstract class AppViewRecord extends ViewRecord
{
    use CommentsPanel;
    use HasAppChrome;
    use HistoryPanel;
    use RecordExports;
    use RecordNavigation;

    /** Print view (印刷する) */
    #[Url(as: 'print')]
    public bool $printMode = false;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        // before a page's own mount() reads one of them (e.g. 受注's 得意先): otherwise that one loads by itself first
        $this->loadBatchedRelations();

        $this->commentForm->fill();
    }

    /** Every request of the page: the record's belongs-to relations shown on it, one query per table (batchedRelations()). */
    public function booted(): void
    {
        $this->loadBatchedRelations();
    }

    /** batchedRelations() not loaded yet (RelationBatch skips those already there). */
    private function loadBatchedRelations(): void
    {
        $paths = $this->batchedRelations();
        if ($paths !== [] && $this->record instanceof Model) {
            RelationBatch::load($this->record, $paths);
        }
    }

    /**
     * Belongs-to relations the page shows — of the record or of its rows ("papers.supplier") — loaded together with one
     * query per related table (Core RelationBatch) instead of one per relation and row, on every request of the page.
     * 作成者 and 更新者 by default (relations the model lacks are skipped); pages add theirs to these.
     *
     * @return list<string>
     */
    protected function batchedRelations(): array
    {
        return ['creator', 'updater'];
    }

    public function appPageKind(): string
    {
        return 'view';
    }

    /** @return array<string> */
    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'sfk-app-page', 'sfk-app-page-view', ...($this->printMode ? ['sfk-app-page-print'] : [])];
    }

    /** The shown version of the record while 更新内容を表示 is on, else the record. */
    public function getRecord(): Model
    {
        $version = $this->shownVersion();
        if ($version === null || ! $this->record instanceof HasRecordHistory) {
            return $this->record;
        }

        return $this->versionPreview ??= $this->record->historyPreview($version->snapshot);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components($this->recordContentComponents());
    }

    /**
     * The record with the コメント | 変更履歴 panel beside it (and relation managers below).
     *
     * @return list<Component>
     */
    protected function recordContentComponents(): array
    {
        return [
            Flex::make([
                Group::make([EmbeddedSchema::make('infolist')])->grow(),
                ViewComponent::make('core::filament.record-sidebar')
                    ->visible(fn (): bool => $this->showsComments() || $this->showsHistory())
                    ->grow(false),
            ])->from('lg'),
            $this->getRelationManagersContentComponent(),
        ];
    }

    /** Changed fields of the shown version get the green mark (entries are matched by name). */
    public function infolist(Schema $schema): Schema
    {
        $schema = parent::infolist($schema);

        foreach ($schema->getFlatComponents(withHidden: true) as $component) {
            if ($component instanceof Entry) {
                $component->extraEntryWrapperAttributes(
                    fn (Entry $entry): array => in_array($entry->getName(), $this->highlightedFields(), true) ? ['class' => 'sfk-history-changed'] : [],
                    merge: true,
                );
            }
        }

        return $schema;
    }

    /** Tab of the side panel shown first: 変更履歴 while a version is shown or without comments, else コメント. */
    public function sidebarTab(): string
    {
        return ! $this->showsComments() || ($this->historyVersion !== null && $this->showsHistory()) ? 'history' : 'comments';
    }

    /** The side panel opens collapsed; only a shown version (変更履歴) keeps it open. */
    public function sidebarStartsOpen(): bool
    {
        return $this->historyVersion !== null && $this->showsHistory();
    }

    protected function getHeaderActions(): array
    {
        $resource = static::getResource();

        return array_values(array_filter([
            // the app's legacy record buttons (under the app name), not on an old version of the record
            ...array_map(fn (Action $action): Action => MenuButtons::mark($action)->hidden(fn (): bool => $this->historyVersion !== null), $this->menuActions()),
            EditAction::make()
                ->label('編集')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->extraAttributes(['title' => 'レコードを編集する'])
                ->visible(fn (): bool => $this->historyVersion === null && $resource::canEdit($this->record)),
            Action::make('duplicate')
                ->label('レコードを再利用する')
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->iconButton()
                ->color('gray')
                // the create page, or the list opening the app's quick-create panel with the copy
                ->url(fn (): string => (string) $resource::newRecordUrl(['duplicate' => $this->record->getKey()]))
                ->visible(fn (): bool => $this->historyVersion === null && $resource::newRecordUrl() !== null && $this->appSetting('record_duplicate_enabled')),
            ActionGroup::make([
                Action::make('print')
                    ->label('印刷する')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(fn (): string => $resource::getUrl('view', ['record' => $this->record, 'print' => 1]))
                    ->openUrlInNewTab(),
                // asks twice (confirmDelete)
                Action::make('delete')
                    ->label('削除する')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->visible(fn (): bool => $resource::canDelete($this->record))
                    // a record still used elsewhere is not deleted (RecordDeletion)
                    ->mountUsing(function (): void {
                        $reason = app(RecordDeletion::class)->blocker($this->record);
                        if ($reason !== null) {
                            Notification::make()->danger()->title('削除できません')->body($reason)->persistent()->send();

                            throw new Cancel;
                        }
                    })
                    ->requiresConfirmation()
                    ->modalIcon(Heroicon::OutlinedExclamationTriangle)
                    ->modalIconColor('danger')
                    ->modalWidth('md')
                    ->modalHeading('このレコードを削除しますか？')
                    ->modalDescription(fn (): HtmlString => DeleteImpact::description($this->record, [$this->record->getKey()]))
                    ->modalSubmitActionLabel('削除する')
                    ->modalCancelActionLabel('キャンセル')
                    ->action(fn () => $this->replaceMountedAction('confirmDelete')),
            ])
                ->label('その他の操作')
                ->icon(Heroicon::EllipsisHorizontal)
                ->color('gray')
                ->iconButton(),
            MenuButtons::export($this->exportActions()),
        ]));
    }

    /** 削除する, second step: 「この操作は取り消せません」, then the record is deleted with its parts (GuardedDelete). */
    public function confirmDeleteAction(): DeleteAction
    {
        $resource = static::getResource();

        return GuardedDelete::apply(DeleteAction::make('confirmDelete'))
            ->record(fn (): Model => $this->record)
            ->visible(fn (): bool => $resource::canDelete($this->record))
            ->modalIcon(Heroicon::OutlinedTrash)
            ->modalWidth('md')
            ->modalHeading('本当にこのレコードを削除しますか？')
            ->modalDescription('この操作は取り消せません。')
            ->modalSubmitActionLabel('削除する')
            ->modalCancelActionLabel('キャンセル')
            ->successRedirectUrl(fn (): string => $resource::getUrl('index'));
    }

    /**
     * The app's legacy record buttons (「ボタンをメニュー位置に配置する」 レコード詳細メニューの上側), drawn as buttons before
     * 編集 / 再利用 / 「…」.
     *
     * @return list<Action>
     */
    protected function menuActions(): array
    {
        return [];
    }

    /**
     * The legacy text of the record menu (「メニュー位置に文字を表示する」 レコード詳細メニューの上側) as button label =>
     * explanation, shown in the collapsible 「ボタンの説明」 panel; empty for none.
     *
     * @return array<string, string>
     */
    public function menuHelp(): array
    {
        return [];
    }
}
