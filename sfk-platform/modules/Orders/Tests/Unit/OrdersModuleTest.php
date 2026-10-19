<?php

use App\Modules\ModuleManager;

it('registers the orders module', function () {
    expect(app(ModuleManager::class)->isEnabled('orders'))->toBeTrue();
});
