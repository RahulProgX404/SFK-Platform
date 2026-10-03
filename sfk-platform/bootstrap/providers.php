<?php

use App\Modules\ModulesServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    // Discovers modules/*/module.json and registers every enabled module's provider (incl. panels).
    ModulesServiceProvider::class,
];
