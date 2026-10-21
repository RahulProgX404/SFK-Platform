<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\SafetyChecks\Pages;

use Modules\Core\Filament\AppPages\AppListViewSettings;
use Modules\HumanResources\Filament\Resources\SafetyChecks\SafetyCheckResource;

/** アプリの設定 → 一覧 of 17. 安全衛生チェック. */
final class SafetyCheckListViewSettings extends AppListViewSettings
{
    protected static string $resource = SafetyCheckResource::class;
}
