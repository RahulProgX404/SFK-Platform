<?php

use App\Modules\ModuleManager;

it('registers the analytics module', function () {
    expect(app(ModuleManager::class)->isEnabled('analytics'))->toBeTrue();
});
