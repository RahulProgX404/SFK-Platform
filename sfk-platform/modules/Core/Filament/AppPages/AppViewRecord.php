<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\Entry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\Comments\RecordComments;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Application\History\RecordHistory;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Core\Filament\AppPages\Concerns\HasAppChrome;
use Modules\Core\Filament\Support\MenuButtons;
use Modules\Core\Models\RecordComment;
use Modules\Core\Models\RecordVersion;

/**
 * A record of an app, in the legacy layout: app frame; toolbar with the pencil (レコードを編集する), 再利用
 * (レコードを再利用する, その他の設定) and the 「…」 menu (印刷する, 削除する) on the left, gear and ‹ › to the previous /
 * next record (in the app's list order, recordNavigationColumn()) on the right; the panel on the right of the record
 * (collapsed to a bar of icons when the record opens) with the tabs コメント (records implementing HasRecordComments, その他の設定): 宛先 + text → 書き込む, comments newest
 * first with 返信する / 削除する (own comments); and 変更履歴 (records implementing HasRecordHistory, その他の設定):
 * versions newest first with 更新内容を表示 (changed fields in green) / このバージョンに戻す, and 最新の内容を表示.
 * 印刷する opens the print view (?print=1): the record without header, menus, comments and history, with 印刷 / 閉じる.
 *
 * @property-read Schema $commentForm
 */
abstract class AppViewRecord extends ViewRecord
{
    use HasAppChrome;

    /** Print view (印刷する) */
    #[Url(as: 'print')]
    public bool $printMode = false;

    /** 変更履歴: the version shown instead of the latest content */
    #[Url(as: 'version')]
    public ?int $historyVersion = null;

    /** @var array<string, mixed>|null コメント being written: body, mentions (宛先) */
    public ?array $commentData = [];

    private ?Model $versionPreview = null;

    private ?RecordVersion $shownVersion = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->commentForm->fill();
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
        return $schema->components([
            Flex::make([
                Group::make([EmbeddedSchema::make('infolist')])->grow(),
                ViewComponent::make('core::filament.record-sidebar')
                    ->visible(fn (): bool => $this->showsComments() || $this->showsHistory())
                    ->grow(false),
            ])->from('lg'),
            $this->getRelationManagersContentComponent(),
        ]);
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

    public function showsHistory(): bool
    {
        return ! $this->printMode && $this->record instanceof HasRecordHistory && $this->appSetting('record_history_enabled');
    }

    public function showsComments(): bool
    {
        return ! $this->printMode && $this->record instanceof HasRecordComments && $this->appSetting('record_comments_enabled');
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

    /** 宛先 + コメント → 書き込む */
    public function commentForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('commentData')
            ->components([
                Select::make('mentions')
                    ->label('宛先')
                    ->multiple()
                    ->searchable()
                    ->options(fn (): array => User::query()
                        ->whereIn('employment_status', array_map(
                            fn (EmploymentStatus $status): string => $status->value,
                            array_filter(EmploymentStatus::cases(), fn (EmploymentStatus $status): bool => $status->canSignIn()),
                        ))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
                Textarea::make('body')
                    ->label('コメント')
                    ->hiddenLabel()
                    ->required()
                    ->maxLength(RecordComments::MAX_LENGTH)
                    ->rows(3)
                    ->autosize()
                    ->extraInputAttributes(['data-sfk-comment-body' => true]),
            ]);
    }

    /** @return Collection<int, RecordComment> newest first */
    public function recordComments(): Collection
    {
        return $this->showsComments() ? app(RecordComments::class)->comments($this->record) : new Collection;
    }

    public function postComment(): void
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $this->showsComments() || ! $this->record instanceof HasRecordComments) {
            return;
        }

        $data = $this->commentForm->getState();
        app(RecordComments::class)->post(
            $this->record,
            $user,
            trim((string) $data['body']),
            array_map('intval', (array) ($data['mentions'] ?? [])),
            $this->appResource()::appName().': '.$this->getRecordTitle(),
            static::getResource()::getUrl('view', ['record' => $this->record]),
        );

        $this->commentForm->fill();
    }

    /** 返信する: addresses the comment being written to the writer of the given one. */
    public function replyToComment(int $commentId): void
    {
        $comment = $this->recordComments()->firstWhere('id', $commentId);
        if ($comment === null) {
            return;
        }

        $mentions = array_map('intval', (array) ($this->commentData['mentions'] ?? []));
        if ($comment->user_id !== null && $comment->user_id !== auth()->id()) {
            $mentions = [$comment->user_id];
        }

        $this->commentForm->fill(['body' => $this->commentData['body'] ?? '', 'mentions' => $mentions]);
        $this->dispatch('sfk-comment-reply');
    }

    public function deleteCommentAction(): Action
    {
        return Action::make('deleteComment')
            ->label('削除する')
            ->link()
            ->color('gray')
            ->visible(fn (array $arguments): bool => $this->showsComments()
                && $this->recordComments()->firstWhere('id', (int) ($arguments['comment'] ?? 0))?->user_id === auth()->id())
            ->modalIcon(Heroicon::OutlinedTrash)
            ->modalIconColor('danger')
            ->modalWidth('md')
            ->modalHeading('コメントを削除しますか？')
            ->modalSubmitAction(fn (Action $action): Action => $action->label('削除する')->color('danger'))
            ->modalCancelActionLabel('キャンセル')
            ->action(function (array $arguments): void {
                $user = auth()->user();
                if ($user instanceof User) {
                    app(RecordComments::class)->delete($this->record, (int) ($arguments['comment'] ?? 0), $user);
                }
            });
    }

    /** @return Collection<int, RecordVersion> */
    public function historyVersions(): Collection
    {
        return $this->showsHistory() ? app(RecordHistory::class)->versions($this->record) : new Collection;
    }

    public function shownVersion(): ?RecordVersion
    {
        if ($this->historyVersion === null || ! $this->showsHistory()) {
            return null;
        }

        return $this->shownVersion ??= app(RecordHistory::class)->find($this->record, $this->historyVersion);
    }

    /** @return list<string> fields marked in green: those changed in the shown version */
    public function highlightedFields(): array
    {
        $changed = $this->shownVersion()->changed ?? [];

        return [...$changed, ...$this->relatedHighlightFields($changed)];
    }

    /** Label of a field in the 変更履歴: historyLabels(), else the app's list column label, else the field name. */
    public function historyFieldLabel(string $field): string
    {
        if (isset($this->historyLabels()[$field])) {
            return $this->historyLabels()[$field];
        }

        $index = static::getResource()::getPages()['index']->getPage();
        $columns = is_subclass_of($index, AppListRecords::class) ? (new $index)->listColumns() : [];

        return $columns[$field] ?? $field;
    }

    /** @return array<string, string> field => label for the 変更履歴 (e.g. the app's legacy field names) */
    protected function historyLabels(): array
    {
        return [];
    }

    public function showVersion(int $version): void
    {
        $this->historyVersion = $version;
        $this->rebindShownRecord();
    }

    public function showLatest(): void
    {
        $this->historyVersion = null;
        $this->rebindShownRecord();
    }

    /** The record schema is cached at the start of the request: point it at the newly shown content. */
    private function rebindShownRecord(): void
    {
        $this->shownVersion = null;
        $this->versionPreview = null;
        $this->getSchema('infolist')?->record($this->getRecord());
    }

    public function restoreVersionAction(): Action
    {
        return Action::make('restoreVersion')
            ->label(fn (array $arguments): string => (int) ($arguments['version'] ?? 0) === 1 ? '初版に戻す' : 'このバージョンに戻す')
            ->link()
            ->color('gray')
            ->visible(fn (): bool => $this->showsHistory() && static::getResource()::canEdit($this->record))
            ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
            ->modalWidth('md')
            ->modalHeading(fn (array $arguments): string => 'バージョン '.(int) ($arguments['version'] ?? 0).' に戻しますか？')
            ->modalSubmitActionLabel('戻す')
            ->modalCancelActionLabel('キャンセル')
            ->action(function (array $arguments): void {
                $version = app(RecordHistory::class)->find($this->record, (int) ($arguments['version'] ?? 0));
                if ($version === null || ! $this->record instanceof HasRecordHistory) {
                    return;
                }

                $user = auth()->user();
                app(RecordHistory::class)->restore($this->record, $version, $user instanceof User ? $user->id : null);
                $this->record->refresh();
                $this->showLatest();

                Notification::make()->title('バージョン '.$version->version.' に戻しました')->success()->send();
            });
    }

    protected function getHeaderActions(): array
    {
        $resource = static::getResource();

        return [
            // the app's legacy record buttons, not on an old version of the record
            ...array_map(fn (Action $action): Action => MenuButtons::mark($action)->hidden(fn (): bool => $this->historyVersion !== null), $this->menuActions()),
            EditAction::make()
                ->label('レコードを編集する')
                ->icon(Heroicon::OutlinedPencil)
                ->iconButton()
                ->color('gray')
                ->visible(fn (): bool => $this->historyVersion === null && $resource::canEdit($this->record)),
            Action::make('duplicate')
                ->label('レコードを再利用する')
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->iconButton()
                ->color('gray')
                ->url(fn (): string => $resource::getUrl('create', ['duplicate' => $this->record->getKey()]))
                ->visible(fn (): bool => $this->historyVersion === null && $resource::hasPage('create') && $resource::canCreate()
                    && $this->appSetting('record_duplicate_enabled')),
            ActionGroup::make([
                Action::make('print')
                    ->label('印刷する')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(fn (): string => $resource::getUrl('view', ['record' => $this->record, 'print' => 1]))
                    ->openUrlInNewTab(),
                DeleteAction::make()
                    ->label('削除する')
                    ->record(fn (): Model => $this->record)
                    ->visible(fn (): bool => $resource::canDelete($this->record)),
            ])
                ->label('その他の操作')
                ->icon(Heroicon::EllipsisHorizontal)
                ->color('gray')
                ->iconButton(),
        ];
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

    /**
     * Entries to mark besides the changed fields themselves (e.g. an entry built from several fields).
     *
     * @param  list<string>  $changed
     * @return list<string>
     */
    protected function relatedHighlightFields(array $changed): array
    {
        return [];
    }

    /** Column of the app's default list order, followed by ‹ › (the key breaks ties; a blank value navigates by key). */
    protected function recordNavigationColumn(): string
    {
        return $this->record->getKeyName();
    }

    /** @return array{previous: string|null, next: string|null} */
    public function recordNeighbours(): array
    {
        $resource = static::getResource();
        $urls = [];

        foreach (['previous' => false, 'next' => true] as $direction => $forward) {
            $neighbour = $this->neighbour($forward);
            $urls[$direction] = $neighbour !== null && $resource::canView($neighbour)
                ? $resource::getUrl('view', ['record' => $neighbour])
                : null;
        }

        return $urls;
    }

    private function neighbour(bool $forward): ?Model
    {
        $record = $this->record;
        $key = $record->getQualifiedKeyName();
        $id = $record->getKey();
        $value = $record->getAttribute($this->recordNavigationColumn());
        $column = $value === null ? $key : $record->qualifyColumn($this->recordNavigationColumn());
        $value ??= $id;
        $operator = $forward ? '>' : '<';
        $direction = $forward ? 'asc' : 'desc';

        return static::getResource()::getEloquentQuery()
            ->where(fn (Builder $query): Builder => $query
                ->where($column, $operator, $value)
                ->orWhere(fn (Builder $query): Builder => $query->where($column, $value)->where($key, $operator, $id)))
            ->orderBy($column, $direction)
            ->orderBy($key, $direction)
            ->first();
    }
}
