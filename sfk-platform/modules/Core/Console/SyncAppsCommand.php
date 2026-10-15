<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Modules\Core\Application\Apps\AppDiscovery;
use Modules\Core\Application\Apps\AppLifecycle;

/** Aligns the app registry (アプリ設定) with the apps built in code; run on deploy. */
final class SyncAppsCommand extends Command
{
    protected $signature = 'core:apps-sync';

    protected $description = 'Add apps built in code to the registry (as 開発中) and remove apps whose code was deleted';

    public function handle(AppDiscovery $discovery, AppLifecycle $lifecycle): int
    {
        $result = $lifecycle->syncWithCode($discovery->definitions());

        $this->components->info(sprintf(
            'Apps added: %s / removed: %s',
            $result['added'] === [] ? '-' : implode(', ', $result['added']),
            $result['removed'] === [] ? '-' : implode(', ', $result['removed']),
        ));

        return self::SUCCESS;
    }
}
