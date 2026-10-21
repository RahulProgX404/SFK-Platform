<?php

use App\Modules\ModuleManager;

it('registers the purchasing module', function () {
    expect(app(ModuleManager::class)->isEnabled('purchasing'))->toBeTrue();
});
