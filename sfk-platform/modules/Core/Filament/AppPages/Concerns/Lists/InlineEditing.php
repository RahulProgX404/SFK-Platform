<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages\Concerns\Lists;

use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Application\History\RecordHistory;
use Modules\Core\Application\Records\RecordValidator;
use Modules\Core\Application\Records\RecordVersion;
use Modules\Core\Filament\Tables\InlineField;

/**
 * インライン編集 of an app list (その他の設定): the pencil of a row turns its cells of inlineFields() into inputs
 * (Tables\AppTextColumn) with 保存 / キャンセル, checked with the fields' rules and the app's RecordRules, saved
 * with optimistic locking (RecordVersion) and kept in the 変更履歴.
 *
 * Part of Modules\Core\Filament\AppPages\AppListRecords (the shared app list); uses its views, fields and access rights.
 */
trait InlineEditing
{
    /** インライン編集: key of the row being edited */
    public ?string $inlineRecordKey = null;

    /** インライン編集: 更新日時 of the row when it was opened (RecordVersion) */
    #[Locked]
    public ?string $inlineVersion = null;

    /** @var array<string, mixed> インライン編集: values of the row being edited, by field */
    public array $inlineData = [];

    private ?bool $rowActionsEnabled = null;

    public function inlineField(string $name): ?InlineField
    {
        foreach ($this->allowedInlineFields() as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    /** その他の設定 → インライン編集: the pencil and × at the end of the rows. */
    public function rowActionsEnabled(): bool
    {
        return $this->rowActionsEnabled ??= $this->appSetting('inline_edit_enabled');
    }

    public function isInlineEditing(Model $record): bool
    {
        return $this->inlineRecordKey !== null && $this->inlineRecordKey === (string) $record->getKey();
    }

    /** The pencil of a row: its cells become inputs. */
    public function startInlineEdit(Model|string $record): void
    {
        $record = $this->inlineRecord($record instanceof Model ? (string) $record->getKey() : $record);
        $this->resetErrorBag();

        if ($record === null) {
            $this->cancelInlineEdit();

            return;
        }

        $this->inlineRecordKey = (string) $record->getKey();
        $this->inlineData = $this->inlineEditState($record);
        $this->inlineVersion = RecordVersion::of($record);
    }

    /** 保存 of the row being edited: checked with the fields' rules, saved and kept in the 変更履歴. */
    public function saveInlineEdit(): void
    {
        $record = $this->inlineRecord($this->inlineRecordKey);
        if ($record === null) {
            $this->cancelInlineEdit();

            return;
        }

        $rules = [];
        $attributes = [];
        foreach ($this->allowedInlineFields() as $field) {
            foreach ($field->rules($record) as $path => $fieldRules) {
                $rules['inlineData.'.$path] = $fieldRules;
            }
            $attributes['inlineData.'.$field->name] = $this->listColumns()[$field->name] ?? $field->name;
            $attributes['inlineData.'.$field->name.'.*'] = $attributes['inlineData.'.$field->name];
        }

        /** @var array<string, mixed> $data */
        $data = $this->validate($rules, [], $attributes)['inlineData'] ?? [];

        // optimistic locking (RecordVersion): someone else saved the row since it was opened
        if (! RecordVersion::unchanged($record, $this->inlineVersion)) {
            Notification::make()->danger()->persistent()->title(RecordVersion::CONFLICT)->send();
            $this->cancelInlineEdit();

            return;
        }

        // the app's RecordRules (e.g. unique per person and month), for the fields changed here only — whatever else a
        // legacy record lacks does not keep this edit from being saved
        $recordRules = $this->appResource()::recordRules();
        if ($recordRules !== null) {
            $fields = array_map(fn (InlineField $field): string => $field->name, $this->allowedInlineFields());
            RecordValidator::throwIfAny(RecordValidator::split($recordRules, [...$recordRules->values($record), ...$data], $record, 'inlineData', $fields)[0]);
        }
        foreach ($this->allowedInlineFields() as $field) {
            $data[$field->name] = match (true) {
                $field->isBoolean() => (bool) ($data[$field->name] ?? false),
                $field->isMultiple() => array_values((array) ($data[$field->name] ?? [])),
                default => blank($data[$field->name] ?? null) ? null : $data[$field->name],
            };
        }

        DB::transaction(function () use ($record, $data): void {
            $this->saveInlineRecord($record, $data);

            if ($record instanceof HasRecordHistory && $this->appSetting('record_history_enabled')) {
                $user = auth()->user();
                app(RecordHistory::class)->record($record, $user instanceof User ? $user->id : null);
            }
        });

        $this->cancelInlineEdit();
    }

    public function cancelInlineEdit(): void
    {
        $this->inlineRecordKey = null;
        $this->inlineData = [];
        $this->resetErrorBag();
    }

    /**
     * Values the inputs of インライン編集 start with; add the record's related data here (e.g. selected users).
     *
     * @return array<string, mixed>
     */
    protected function inlineEditState(Model $record): array
    {
        $state = [];
        $columns = $record->getAttributes();
        foreach ($this->allowedInlineFields() as $field) {
            // columns only: related data (e.g. selected users) is added by the app
            $value = array_key_exists($field->name, $columns) ? $record->getAttribute($field->name) : null;
            $state[$field->name] = match (true) {
                $value instanceof BackedEnum => (string) $value->value,
                $value instanceof DateTimeInterface => $value->format('Y-m-d'),
                $field->isBoolean() => (bool) $value,
                $field->isMultiple() => array_values((array) $value),
                default => $value,
            };
        }

        return $state;
    }

    /**
     * Saves the checked values of インライン編集 (inside a transaction); override for related data.
     *
     * @param  array<string, mixed>  $data
     */
    protected function saveInlineRecord(Model $record, array $data): void
    {
        $record->fill($data)->save();
    }

    /** The record behind a row key, when インライン編集 is on, the app has inline fields and the user may edit it. */
    private function inlineRecord(?string $key): ?Model
    {
        if ($key === null || ! $this->rowActionsEnabled() || $this->allowedInlineFields() === []) {
            return null;
        }

        $resource = $this->appResource();
        $record = $resource::getEloquentQuery()->whereKey($key)->first();

        return $record !== null && $resource::canEdit($record) ? $record : null;
    }

    /** @return list<InlineField> the インライン編集 fields the user may change */
    private function allowedInlineFields(): array
    {
        return array_values(array_filter($this->inlineFields(), fn (InlineField $field): bool => $this->fieldLevel($field->name)->canEdit()));
    }
}
