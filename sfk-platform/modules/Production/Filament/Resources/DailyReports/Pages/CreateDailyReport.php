<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\DailyReports\Pages;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\AppPages\AppCreateRecord;
use Modules\Production\Filament\Resources\DailyReports\DailyReportResource;
use Modules\Production\Models\DailyReport;

final class CreateDailyReport extends AppCreateRecord
{
    protected static string $resource = DailyReportResource::class;

    protected static ?string $title = '日報を追加';

    /**
     * レコードを再利用する: the legacy id, the copied rates / prices and the figures are not copied (taken again on save).
     *
     * @return list<string>
     */
    protected function duplicateExcludedFields(): array
    {
        return ['legacy_id', 'work_rate', 'overtime_rate', 'cardboard_cost', 'partition_cost', ...DailyReport::MACHINE_RATES, 'work_hours', 'overtime_hours',
            'internal_cost', 'manufacturing_sales'];
    }

    /** The copy is a task of today, without the status update. */
    protected function mutateDuplicateData(array $data, Model $source): array
    {
        return [...$data, 'worked_on' => now()->toDateString(), 'update_status' => false, 'new_status' => null];
    }

    /** Like the legacy system, show the saved record. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
