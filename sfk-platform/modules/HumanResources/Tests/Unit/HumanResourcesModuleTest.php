<?php

use App\Modules\ModuleManager;

it('registers the hr module', function () {
    expect(app(ModuleManager::class)->isEnabled('hr'))->toBeTrue();
});
