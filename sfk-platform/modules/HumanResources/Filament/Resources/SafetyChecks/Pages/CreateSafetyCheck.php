<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\SafetyChecks\Pages;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\AppPages\AppCreateRecord;
use Modules\HumanResources\Domain\HrChoices;
use Modules\HumanResources\Filament\Resources\SafetyChecks\SafetyCheckLayout;
use Modules\HumanResources\Filament\Resources\SafetyChecks\SafetyCheckResource;
use Modules\HumanResources\Models\SafetyCheck;
use Modules\HumanResources\Models\SafetyCheckItem;

/**
 * New checklist of 17. 安全衛生チェック. レコードを再利用する copies the checklist items (No, 項目) for this month's check,
 * with 評価 back to はい and チェック / 備考 empty.
 */
final class CreateSafetyCheck extends AppCreateRecord
{
    protected static string $resource = SafetyCheckResource::class;

    protected static ?string $title = '安全衛生チェックを追加';

    /** A new checklist starts with the template items (those of 部署 once it is chosen, SafetyCheckLayout). */
    protected function afterFill(): void
    {
        if ($this->duplicate === null) {
            $this->form->fill([...$this->form->getRawState(), 'items' => SafetyCheckLayout::templateRows(null)]);
        }
    }

    /**
     * レコードを再利用する: the legacy id is not copied; the date starts today.
     *
     * @return list<string>
     */
    protected function duplicateExcludedFields(): array
    {
        return ['legacy_id', 'checked_on'];
    }

    protected function mutateDuplicateData(array $data, Model $source): array
    {
        if ($source instanceof SafetyCheck) {
            $data['checked_on'] = now()->toDateString();
            $data['items'] = $source->items->map(fn (SafetyCheckItem $row): array => [
                'number' => $row->number,
                'item' => $row->item,
                'answer' => HrChoices::ANSWERS[0],
                'checked' => false,
                'notes' => null,
            ])->all();
        }

        return $data;
    }

    /** Like the legacy system, show the saved record. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
