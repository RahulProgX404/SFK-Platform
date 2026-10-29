<?php

declare(strict_types=1);

namespace Modules\Core\Application\Legacy;

/**
 * Takes over the records of one legacy app from its export (LegacyExport) — registered by the module owning the app
 * (tag TAG) and run by `php artisan legacy:import` in the order of the business process (step()): the masters first
 * (02 → 03 → 01 → 19), then quotation, order, production, purchasing, sales and collection (SYSTEM_BRAIN
 * 02_BUSINESS_PROCESSES). An importer is safe to run again: records are matched by their legacy record number and
 * updated.
 */
interface LegacyImporter
{
    public const TAG = 'legacy.importers';

    /** The legacy app id of the export it reads (app.appId, also the number before the file name: 0009_… = 9). */
    public function legacyAppId(): string;

    /** The legacy app, e.g. 「04. 受注入力」. */
    public function label(): string;

    /** Position in the business process: lower runs first. */
    public function step(): int;

    public function import(LegacyExport $export, LegacyImportLog $log): void;
}
