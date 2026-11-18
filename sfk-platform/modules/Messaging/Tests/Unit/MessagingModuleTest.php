<?php

use App\Modules\ModuleManager;

it('registers the messaging module', function () {
    expect(app(ModuleManager::class)->isEnabled('messaging'))->toBeTrue();
});
