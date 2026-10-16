<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Partners\Pages;

use Modules\Core\Filament\AppPages\AppListViewSettings;
use Modules\MasterData\Filament\Resources\Partners\PartnerResource;

/** アプリの設定 → 一覧 of 01. 取引先マスタ. */
final class PartnerListViewSettings extends AppListViewSettings
{
    protected static string $resource = PartnerResource::class;
}
