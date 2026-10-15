<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages;

use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\AppPages\Concerns\HasAppChrome;

/** New record of an app: app frame with キャンセル / 保存 at the top, no buttons below the form. */
abstract class AppCreateRecord extends CreateRecord
{
    use HasAppChrome;

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
}
