<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Application\History\RecordHistory;
use Modules\Core\Application\Records\RecordValidator;
use Modules\Core\Filament\AppPages\Concerns\HasAppChrome;

/**
 * Editing a record of an app (design_ref/add-invoice.png): ← and the app name at the top; キャンセル / 保存 in the footer
 * of the form, which stays at the bottom of the screen. A save that changed
 * the record adds a version to its 変更履歴 (subclasses with their own afterSave() call parent::afterSave()). The app's
 * RecordRules (AppResource::recordRules(): required fields, values that must differ or be unique) are checked on the form
 * with the record's other values, together with the inputs' own rules; an error of a field the form does not offer stops
 * the save with a message.
 */
abstract class AppEditRecord extends EditRecord
{
    use HasAppChrome;

    public function appPageKind(): string
    {
        return 'edit';
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCancelFormAction()->label('キャンセル')->color('gray'),
            Action::make('saveRecord')->label('保存')->action(fn () => $this->save())->keyBindings(['mod+s']),
        ];
    }

    public function areFormActionsSticky(): bool
    {
        return true;
    }

    /**
     * The app's RecordRules, before the inputs are checked: when they find something, the errors of the inputs are added,
     * so the form shows every error of the save at once.
     */
    protected function beforeValidate(): void
    {
        $rules = $this->appResource()::recordRules();
        if ($rules === null) {
            return;
        }

        $form = (array) $this->data;
        [$errors, $elsewhere] = RecordValidator::split($rules, [...$rules->values($this->record), ...$form], $this->record, 'data', array_keys($form));
        if ($errors === [] && $elsewhere === []) {
            return;
        }

        try {
            $this->form->validate();
        } catch (ValidationException $exception) {
            $errors = array_merge_recursive($exception->errors(), $errors);
        }

        if ($elsewhere !== []) {
            Notification::make()->danger()->title(implode("\n", $elsewhere))->send();
        }
        if ($errors === []) {
            throw new Halt;
        }

        RecordValidator::throwIfAny(array_map(fn (array $messages): array => array_values(array_unique($messages)), $errors));
    }

    protected function afterSave(): void
    {
        $app = $this->appResource()::app();

        if ($this->record instanceof HasRecordHistory && ($app === null || $app->record_history_enabled)) {
            $user = auth()->user();
            app(RecordHistory::class)->record($this->record, $user instanceof User ? $user->id : null);
        }
    }
}
