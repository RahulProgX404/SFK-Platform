<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\SafetyChecks\Pages;

use Modules\Core\Filament\AppPages\AppSettings;
use Modules\HumanResources\Filament\Resources\SafetyChecks\SafetyCheckResource;

/** アプリの設定 → 設定 of 17. 安全衛生チェック. */
final class SafetyCheckSettings extends AppSettings
{
    protected static string $resource = SafetyCheckResource::class;
}
