<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Application\History\RecordHistory;
use Modules\Core\Filament\AppPages\Concerns\HasAppChrome;

/**
 * Editing a record of an app: app frame with キャンセル / 保存 at the top, no buttons below the form. A save that changed
 * the record adds a version to its 変更履歴 (subclasses with their own afterSave() call parent::afterSave()).
 */
abstract class AppEditRecord extends EditRecord
{
    use HasAppChrome;

    public function appPageKind(): string
    {
        return 'edit';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getCancelFormAction()->label('キャンセル')->color('gray'),
            Action::make('saveRecord')->label('保存')->action(fn () => $this->save())->keyBindings(['mod+s']),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
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
