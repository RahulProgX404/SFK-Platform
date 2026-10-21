<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\AlcoholChecks\Pages;

use Modules\Core\Filament\AppPages\AppSettings;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\AlcoholCheckResource;

/** アプリの設定 → 設定 of 18. アルコールチェック. */
final class AlcoholCheckSettings extends AppSettings
{
    protected static string $resource = AlcoholCheckResource::class;
}
