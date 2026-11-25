<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages\Concerns\Records;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Infolists\Components\Entry;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Application\History\RecordHistory;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Models\RecordVersion;

/**
 * 変更履歴 of the record page's side panel (records implementing HasRecordHistory, その他の設定): version cards newest
 * first, 更新内容を表示 (the version shown with its changed fields in green), このバージョンに戻す and 最新の内容を表示.
 *
 * Part of Modules\Core\Filament\AppPages\AppViewRecord (the shared record page).
 */
trait HistoryPanel
{
    /** 変更履歴: the version shown instead of the latest content */
    #[Url(as: 'version')]
    public ?int $historyVersion = null;

    private ?Model $versionPreview = null;

    private ?RecordVersion $shownVersion = null;

    public function showsHistory(): bool
    {
        return ! $this->printMode && $this->record instanceof HasRecordHistory && $this->appSetting('record_history_enabled');
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
            ->icon(Heroicon::ArrowUturnLeft)
            ->extraAttributes(['class' => 'sfk-comment-action'])
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
}
