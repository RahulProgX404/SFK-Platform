<?php

declare(strict_types=1);

namespace Modules\MasterData\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Called automatically by DatabaseSeeder when the module is enabled. Keep it idempotent.
 *
 * 01. 取引先マスタ: when the legacy record export seed.json is at the project root, its partners are taken over
 * (master-data:import-partners; re-running updates them by 得意先ID).
 */
final class MasterDataDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (is_file(base_path('seed.json'))) {
            $this->command->call('master-data:import-partners', ['file' => 'seed.json']);
        }
    }
}
