<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\DeliveryNotes\Pages;

use Modules\Core\Filament\AppPages\AppListViewSettings;
use Modules\Sales\Filament\Resources\DeliveryNotes\DeliveryNoteResource;

/** アプリの設定 → 一覧 of 10. 納品書管理(売上). */
final class DeliveryNoteListViewSettings extends AppListViewSettings
{
    protected static string $resource = DeliveryNoteResource::class;
}
