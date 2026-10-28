<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\AccidentReports\Pages;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\AppPages\AppCreateRecord;
use Modules\Production\Application\AccidentReports\AccidentAlert;
use Modules\Production\Filament\Resources\AccidentReports\AccidentReportResource;
use Modules\Production\Models\AccidentDamage;
use Modules\Production\Models\AccidentReport;

/** @property AccidentReport $record */
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

    /** A new incident → administrators and 営業関係者 (AccidentAlert), after the 営業関係者 were saved. */
    protected function afterCreate(): void
    {
        parent::afterCreate();

        $actor = auth()->user();
        app(AccidentAlert::class)->created($this->record, $actor instanceof User ? $actor : null, AccidentReportResource::titleOf($this->record));
    }

    /** Like the legacy system, show the saved record. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
