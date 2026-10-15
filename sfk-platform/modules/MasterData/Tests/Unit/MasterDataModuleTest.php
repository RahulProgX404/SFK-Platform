<?php

use App\Modules\ModuleManager;

it('registers the master-data module', function () {
    expect(app(ModuleManager::class)->isEnabled('master-data'))->toBeTrue();
});
