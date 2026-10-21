<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\AlcoholChecks\Pages;

use Modules\Core\Filament\AppPages\AppCreateRecord;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\AlcoholCheckResource;

final class CreateAlcoholCheck extends AppCreateRecord
{
    protected static string $resource = AlcoholCheckResource::class;

    protected static ?string $title = 'アルコールチェックを追加';

    /**
     * レコードを再利用する: a new day's check — the date, the times, the results and the notes are not copied.
     *
     * @return list<string>
     */
    protected function duplicateExcludedFields(): array
    {
        return ['legacy_id', 'checked_on', 'before_time', 'after_time', 'before_detected', 'after_detected', 'before_notes', 'after_notes'];
    }

    /** Like the legacy system, show the saved record. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
