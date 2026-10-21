<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receivables\Pages;

use Modules\Core\Filament\AppPages\AppSettings;
use Modules\Sales\Filament\Resources\Receivables\ReceivableResource;

/** アプリの設定 → 設定 of 23. 売掛管理. */
final class ReceivableSettings extends AppSettings
{
    protected static string $resource = ReceivableResource::class;
}
