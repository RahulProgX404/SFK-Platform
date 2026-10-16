<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Application\History\RecordHistory;
use Modules\Core\Filament\AppPages\Concerns\HasAppChrome;

/**
 * New record of an app: app frame with キャンセル / 保存 at the top, no buttons below the form.
 *
 * レコードを再利用する (?duplicate=<id>, legacy duplicate record): the form starts with the copied record's values except
 * the key, 作成者 / 更新者, the timestamps and duplicateExcludedFields() (auto-numbered codes, attachments, legacy ids);
 * the history is not copied. The saved record starts its 変更履歴 with version 1 (its subclasses calling
 * parent::afterCreate() when they add their own hook).
 */
abstract class AppCreateRecord extends CreateRecord
{
    use HasAppChrome;

    #[Url(as: 'duplicate')]
    public ?string $duplicate = null;

    public function appPageKind(): string
    {
        return 'create';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getCancelFormAction()->label('キャンセル')->color('gray'),
            Action::make('save')->label('保存')->action(fn () => $this->create())->keyBindings(['mod+s']),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function fillForm(): void
    {
        $source = $this->duplicateSource();
        if ($source === null) {
            parent::fillForm();

            return;
        }

        $this->callHook('beforeFill');
        $this->form->fill($this->duplicateFormData($source));
        $this->callHook('afterFill');
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        $app = $this->appResource()::app();

        if ($record instanceof HasRecordHistory && ($app === null || $app->record_history_enabled)) {
            $user = auth()->user();
            app(RecordHistory::class)->record($record, $user instanceof User ? $user->id : null);
        }
    }

    /**
     * Fields not copied by レコードを再利用する besides the key, audit columns and timestamps.
     *
     * @return list<string>
     */
    protected function duplicateExcludedFields(): array
    {
        return [];
    }

    /**
     * Form data of the copied record; add the record's related data here (e.g. selected users).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateDuplicateData(array $data, Model $source): array
    {
        return $data;
    }

    /** The record to copy: it must exist and be visible, and 「レコードの再利用」 must be on. */
    private function duplicateSource(): ?Model
    {
        if ($this->duplicate === null || preg_match('/^\d+$/', $this->duplicate) !== 1) {
            return null;
        }

        $resource = $this->appResource();
        $app = $resource::app();
        if ($app !== null && ! $app->record_duplicate_enabled) {
            return null;
        }

        $source = $resource::getEloquentQuery()->whereKey((int) $this->duplicate)->first();

        return $source !== null && $resource::canView($source) ? $source : null;
    }

    /** @return array<string, mixed> */
    private function duplicateFormData(Model $source): array
    {
        $excluded = [
            $source->getKeyName(), 'created_by', 'updated_by', $source->getCreatedAtColumn(), $source->getUpdatedAtColumn(),
            ...$this->duplicateExcludedFields(),
        ];

        return $this->mutateDuplicateData(array_diff_key($source->attributesToArray(), array_flip(array_filter($excluded))), $source);
    }
}
