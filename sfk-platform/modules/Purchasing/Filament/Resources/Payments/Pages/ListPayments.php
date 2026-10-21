<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Payments\Pages;

use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Purchasing\Filament\Resources\Payments\PaymentResource;
use Modules\Purchasing\Filament\Resources\Payments\Tables\PaymentsTable;

final class ListPayments extends AppListRecords
{
    protected static string $resource = PaymentResource::class;

    public function listColumns(): array
    {
        return PaymentsTable::columns();
    }

    /** The legacy views (SYSTEM_BRAIN APP_07 views.md). */
    public function defaultListViews(): array
    {
        return PaymentsTable::defaultViews();
    }

    public function filterFields(): array
    {
        return PaymentsTable::filterFields();
    }

    public function inlineFields(): array
    {
        return PaymentsTable::inlineFields();
    }
}
