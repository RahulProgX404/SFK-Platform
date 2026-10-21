<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\AlcoholChecks\Pages;

use Modules\Core\Filament\AppPages\AppListViewSettings;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\AlcoholCheckResource;

/** アプリの設定 → 一覧 of 18. アルコールチェック. */
final class AlcoholCheckListViewSettings extends AppListViewSettings
{
    protected static string $resource = AlcoholCheckResource::class;
}
