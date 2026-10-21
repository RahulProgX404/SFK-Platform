<?php

use App\Modules\ModuleManager;

it('registers the sales module', function () {
    expect(app(ModuleManager::class)->isEnabled('sales'))->toBeTrue();
});
