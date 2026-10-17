<?php

declare(strict_types=1);

namespace Modules\MasterData\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;
use Modules\MasterData\Application\Partners\LegacyPartnerImport;

/** Takes over 01. 取引先マスタ from a legacy record export (LegacyPartnerImport); safe to run again. */
final class ImportLegacyPartnersCommand extends Command
{
    protected $signature = 'master-data:import-partners
        {file=seed.json : Record export of the legacy app 6 (path relative to the project)}
        {--warnings : List every warning instead of a summary}';

    protected $description = 'Import 01. 取引先マスタ (partners) from the legacy record export, by 得意先ID';

    public function handle(LegacyPartnerImport $import): int
    {
        $path = (string) $this->argument('file');
        $path = is_file($path) ? $path : base_path($path);

        if (! is_file($path)) {
            $this->components->error("File not found: {$path}");

            return self::FAILURE;
        }

        try {
            $export = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            $result = $import->import(is_array($export) ? $export : []);
        } catch (JsonException|InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Partners created: %d / updated: %d / users created: %d / warnings: %d',
            $result->created,
            $result->updated,
            $result->usersCreated,
            count($result->warnings),
        ));

        $warnings = $this->option('warnings') ? $result->warnings : array_slice($result->warnings, 0, 20);
        foreach ($warnings as $warning) {
            $this->components->warn($warning);
        }
        if (count($warnings) < count($result->warnings)) {
            $this->line(sprintf('  … %d more (--warnings lists them all)', count($result->warnings) - count($warnings)));
        }

        return self::SUCCESS;
    }
}
