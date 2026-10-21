<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receivables\Pages;

use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Sales\Domain\Receivables\ReceivableBasis;
use Modules\Sales\Filament\Resources\Receivables\ReceivableMonthResource;
use Modules\Sales\Filament\Resources\Receivables\Tables\ReceivablesTable;

/** The record list of 23. 売掛管理 / 23-2. 売掛管理(締日別). */
abstract class ListReceivableMonths extends AppListRecords
{
    public function listColumns(): array
    {
        return ReceivablesTable::columns($this->basis());
    }

    /** The legacy views (SYSTEM_BRAIN views.md of the app). */
    public function defaultListViews(): array
    {
        return ReceivablesTable::defaultViews($this->basis());
    }

    public function filterFields(): array
    {
        return ReceivablesTable::filterFields($this->basis());
    }

    public function inlineFields(): array
    {
        return ReceivablesTable::inlineFields();
    }

    protected function basis(): ReceivableBasis
    {
        /** @var class-string<ReceivableMonthResource> $resource */
        $resource = static::getResource();

        return $resource::basis();
    }
}
