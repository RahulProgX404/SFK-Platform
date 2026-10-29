<?php

declare(strict_types=1);

namespace Modules\MasterData\Application\Legacy;

use Modules\Core\Application\Legacy\LegacyExport;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyRecord;
use Modules\MasterData\Application\Partners\LegacyPartnerImport;

/** 01. 取引先マスタ in the legacy import (legacy:import): the partners taken over by LegacyPartnerImport. */
final class LegacyPartnerImporter implements LegacyImporter
{
    public function __construct(private readonly LegacyPartnerImport $import) {}

    public function legacyAppId(): string
    {
        return LegacyPartnerImport::LEGACY_APP_ID;
    }

    public function label(): string
    {
        return '01. 取引先マスタ（得意先・仕入先）';
    }

    public function step(): int
    {
        return 30;
    }

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        // about 3,300 partners (23 MB): read at once, as the partners import does
        $records = array_map(fn (LegacyRecord $record): array => $record->toArray(), iterator_to_array($export->records(), false));
        $result = $this->import->import(['app' => ['appId' => $export->appId()], 'records' => $records]);

        $log->created += $result->created;
        $log->updated += $result->updated;
        foreach ($result->warnings as $warning) {
            $log->warn($warning);
        }
    }
}
