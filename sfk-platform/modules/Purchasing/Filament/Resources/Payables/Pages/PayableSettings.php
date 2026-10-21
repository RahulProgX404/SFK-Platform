<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Payables\Pages;

use Modules\Core\Filament\AppPages\AppSettings;
use Modules\Purchasing\Filament\Resources\Payables\PayableResource;

/** アプリの設定 → 設定 of 23. 買掛管理. */
final class PayableSettings extends AppSettings
{
    protected static string $resource = PayableResource::class;
}
