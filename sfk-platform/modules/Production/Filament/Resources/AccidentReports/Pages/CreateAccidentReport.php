<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\AccidentReports\Pages;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\AppPages\AppCreateRecord;
use Modules\Production\Filament\Resources\AccidentReports\AccidentReportResource;
use Modules\Production\Models\AccidentDamage;
use Modules\Production\Models\AccidentReport;

final class CreateAccidentReport extends AppCreateRecord
{
    protected static string $resource = AccidentReportResource::class;

    protected static ?string $title = '事故報告を追加';

    /**
     * レコードを再利用する: the legacy id and the derived total are not copied.
     *
     * @return list<string>
     */
    protected function duplicateExcludedFields(): array
    {
        return ['legacy_id', 'total_damage'];
    }

    /** 営業関係者 and the damage rows go with the copy. */
    protected function mutateDuplicateData(array $data, Model $source): array
    {
        if ($source instanceof AccidentReport) {
            $data['sales_users'] = $source->salesUsers()->pluck('users.id')->map(fn (mixed $id): string => (string) $id)->all();
            $data['damages'] = $source->damages->map(fn (AccidentDamage $row): array => array_intersect_key($row->attributesToArray(), array_flip(AccidentDamage::COLUMNS)))->all();
        }

        return $data;
    }

    /** Like the legacy system, show the saved record. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
