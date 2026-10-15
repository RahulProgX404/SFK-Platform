<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages;

use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\AppPages\Concerns\HasAppChrome;

/** Editing a record of an app: app frame with キャンセル / 保存 at the top, no buttons below the form. */
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
}
