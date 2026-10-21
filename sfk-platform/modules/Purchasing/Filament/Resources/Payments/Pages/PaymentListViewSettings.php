<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Payments\Pages;

use Modules\Core\Filament\AppPages\AppListViewSettings;
use Modules\Purchasing\Filament\Resources\Payments\PaymentResource;

/** アプリの設定 → 一覧 of 07. 支払入力. */
final class PaymentListViewSettings extends AppListViewSettings
{
    protected static string $resource = PaymentResource::class;
}
