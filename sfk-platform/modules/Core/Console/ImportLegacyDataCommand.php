<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Legacy\LegacyExport;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyPartners;
use Modules\Core\Application\Legacy\LegacyUsers;
use Throwable;

/**
 * Takes over the legacy system's data from its app exports (data_json/<app id>_<app name>.json) in the order of the
 * business process: 02 従業員マスタ → 03 機械マスタ → 01 取引先マスタ → … (LegacyImporter::step()). Safe to run again;
 * `apps` limits it to some legacy apps (their numbers, e.g. 02 03 01). The warnings of each app are written to
 * storage/logs/legacy-import/<app id>.log.
 */
final class ImportLegacyDataCommand extends Command
{
    protected $signature = 'legacy:import
        {apps?* : Legacy app numbers (e.g. 02 03 01, 08N, 23-2; 「23」 = both 23. apps); all when none}
        {--path=data_json : Folder of the app exports}
        {--list : Only list the apps in their order with their files}';

    protected $description = 'Import the legacy system data (app exports) in the order of the business process';

    public function handle(LegacyUsers $users): int
    {
        $folder = (string) $this->option('path');
        $folder = is_dir($folder) ? $folder : base_path($folder);
        if (! is_dir($folder)) {
            $this->components->error("Folder not found: {$folder}");

            return self::FAILURE;
        }

        $importers = $this->importers((array) $this->argument('apps'));
        if ($importers === []) {
            $this->components->error('No importer for the given apps.');

            return self::FAILURE;
        }

        $files = $this->files($folder);
        if ($this->option('list')) {
            $this->table(['Step', 'App', 'File'], array_map(
                fn (LegacyImporter $importer): array => [$importer->step(), $importer->label(), basename($files[$importer->legacyAppId()] ?? '—')],
                $importers,
            ));

            return self::SUCCESS;
        }

        // the bulk of the data: no query log, one app at a time
        DB::connection()->disableQueryLog();
        $failed = false;
        foreach ($importers as $importer) {
            $file = $files[$importer->legacyAppId()] ?? null;
            if ($file === null) {
                $this->components->warn("{$importer->label()}: export not found in {$folder}");

                continue;
            }

            $failed = ! $this->importApp($importer, LegacyExport::open($file), $users) || $failed;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function importApp(LegacyImporter $importer, LegacyExport $export, LegacyUsers $users): bool
    {
        if ($export->appId() !== $importer->legacyAppId()) {
            $this->components->error("{$importer->label()}: ".basename($export->path).' is the export of another app.');

            return false;
        }

        $total = $export->totalCount();
        $started = microtime(true);
        $this->components->info("{$importer->label()} ({$total}件)");
        $log = new LegacyImportLog(fn (int $done) => $this->output->write(sprintf("\r  %s / %s", number_format($done), number_format($total))));

        try {
            $importer->import($export, $log);
        } catch (Throwable $e) {
            $this->newLine();
            $this->components->error("{$importer->label()}: {$e->getMessage()}");
            report($e);

            return false;
        } finally {
            // the next app sees the users and partners this one created
            $users->forget();
            $this->laravel->make(LegacyPartners::class)->forget();
        }

        $this->output->write("\r");
        $this->components->twoColumnDetail(
            sprintf('  作成 %s / 更新 %s / スキップ %s', number_format($log->created), number_format($log->updated), number_format($log->skipped)),
            sprintf('警告 %s · %.1f秒', number_format(count($log->warnings)), microtime(true) - $started),
        );
        $this->writeWarnings($importer, $log);

        return true;
    }

    private function writeWarnings(LegacyImporter $importer, LegacyImportLog $log): void
    {
        $folder = storage_path('logs/legacy-import');
        if (! is_dir($folder)) {
            mkdir($folder, 0775, true);
        }

        $file = $folder.'/'.$importer->legacyAppId().'.log';
        file_put_contents($file, implode("\n", [$importer->label(), ...$log->warnings])."\n");
        foreach (array_slice($log->warnings, 0, 5) as $warning) {
            $this->line('  · '.$warning);
        }
        if (count($log->warnings) > 5) {
            $this->line(sprintf('  … %d more in %s', count($log->warnings) - 5, $file));
        }
    }

    /**
     * @param  list<string>  $apps
     * @return list<LegacyImporter> in the order of the business process
     */
    private function importers(array $apps): array
    {
        $importers = [];
        foreach ($this->laravel->tagged(LegacyImporter::TAG) as $importer) {
            if ($importer instanceof LegacyImporter && ($apps === [] || $this->matches($importer, $apps))) {
                $importers[] = $importer;
            }
        }
        usort($importers, fn (LegacyImporter $a, LegacyImporter $b): int => $a->step() <=> $b->step());

        return $importers;
    }

    /** @param  list<string>  $apps */
    private function matches(LegacyImporter $importer, array $apps): bool
    {
        foreach ($apps as $app) {
            // 「02」 matches 「02. 従業員マスタ」, 「08N」 matches 「08N. 【新】日報入力」
            if (str_starts_with($importer->label(), $app.'.')) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> legacy app id => export file (0009_04. 受注入力.json → '9') */
    private function files(string $folder): array
    {
        $files = [];
        foreach (glob(rtrim($folder, '/\\').'/*.json') ?: [] as $file) {
            if (preg_match('/^0*(\d+)_/', basename($file), $m) === 1) {
                $files[$m[1]] = $file;
            }
        }

        return $files;
    }
}
