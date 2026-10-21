<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Payments\Pages;

use Modules\Core\Filament\AppPages\AppSettings;
use Modules\Purchasing\Filament\Resources\Payments\PaymentResource;

/** アプリの設定 → 設定 of 07. 支払入力. */
final class PaymentSettings extends AppSettings
{
    protected static string $resource = PaymentResource::class;
}
