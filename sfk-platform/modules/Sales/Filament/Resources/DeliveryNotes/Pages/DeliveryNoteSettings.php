<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\DeliveryNotes\Pages;

use Modules\Core\Filament\AppPages\AppSettings;
use Modules\Sales\Filament\Resources\DeliveryNotes\DeliveryNoteResource;

/** アプリの設定 → 設定 of 10. 納品書管理(売上). */
final class DeliveryNoteSettings extends AppSettings
{
    protected static string $resource = DeliveryNoteResource::class;
}
