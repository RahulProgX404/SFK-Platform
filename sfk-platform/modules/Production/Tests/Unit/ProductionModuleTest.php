<?php

use App\Modules\ModuleManager;

it('registers the production module', function () {
    expect(app(ModuleManager::class)->isEnabled('production'))->toBeTrue();
});
