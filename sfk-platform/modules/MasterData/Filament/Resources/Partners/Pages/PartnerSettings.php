<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Partners\Pages;

use Modules\Core\Filament\AppPages\AppSettings;
use Modules\MasterData\Filament\Resources\Partners\PartnerResource;

/** アプリの設定 → 設定 of 01. 取引先マスタ. */
final class PartnerSettings extends AppSettings
{
    protected static string $resource = PartnerResource::class;
}
