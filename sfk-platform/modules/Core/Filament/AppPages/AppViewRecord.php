<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages;

use Filament\Resources\Pages\ViewRecord;
use Modules\Core\Filament\AppPages\Concerns\HasAppChrome;

/** A record of an app: app frame; the header actions (edit, delete…) sit in the toolbar. */
abstract class AppViewRecord extends ViewRecord
{
    use HasAppChrome;

    public function appPageKind(): string
    {
        return 'view';
    }
}
