<?php

declare(strict_types=1);

namespace Modules\MasterData\Providers;

use App\Modules\ModuleServiceProvider;
use Modules\MasterData\Console\ImportLegacyPartnersCommand;

final class MasterDataServiceProvider extends ModuleServiceProvider
{
    protected string $slug = 'master-data';

    protected array $commands = [
        ImportLegacyPartnersCommand::class,
    ];

    protected function registerModule(): void
    {
        // Bind module services / repositories here.
    }

    protected function bootModule(): void
    {
        // Register observers, policies, event listeners here.
    }
}
