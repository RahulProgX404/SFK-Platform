<?php

use App\Modules\ModuleManager;
use App\Modules\ModuleType;
use Illuminate\Support\Facades\File;

function writeModule(string $root, array $manifest): void
{
    $dir = $root.'/'.$manifest['name'];
    File::ensureDirectoryExists($dir);
    File::put($dir.'/module.json', json_encode($manifest));
}

beforeEach(function () {
    $this->root = storage_path('framework/testing/modules-'.uniqid());
    writeModule($this->root, ['name' => 'Core', 'slug' => 'core', 'type' => 'kernel', 'order' => 0]);
    writeModule($this->root, ['name' => 'Sales', 'slug' => 'sales', 'type' => 'business', 'panel' => 'erp', 'depends_on' => ['core', 'master-data'], 'order' => 30]);
    writeModule($this->root, ['name' => 'MasterData', 'slug' => 'master-data', 'type' => 'business', 'panel' => 'erp', 'depends_on' => ['core'], 'order' => 20]);
    writeModule($this->root, ['name' => 'Sns', 'slug' => 'sns', 'type' => 'extension', 'depends_on' => ['core'], 'order' => 500]);
});

afterEach(fn () => File::deleteDirectory($this->root));

it('discovers modules and orders dependencies first', function () {
    $manager = new ModuleManager($this->root);

    expect(array_keys($manager->enabled()))->toBe(['core', 'master-data', 'sales', 'sns'])
        ->and($manager->get('sns')->type)->toBe(ModuleType::Extension)
        ->and($manager->get('sns')->ownPanelId())->toBe('sns')
        ->and(array_keys($manager->forPanel('erp')))->toBe(['master-data', 'sales']);
});

it('disables a module through its switch and cascades to dependents', function () {
    $manager = new ModuleManager($this->root, ['master-data' => false]);

    expect($manager->isEnabled('master-data'))->toBeFalse()
        ->and($manager->isEnabled('sales'))->toBeFalse()
        ->and($manager->disabledReasons())->toMatchArray([
            'master-data' => 'switched off',
            'sales' => 'dependency [master-data] is disabled',
        ])
        ->and($manager->isEnabled('sns'))->toBeTrue();
});

it('never disables kernel modules', function () {
    $manager = new ModuleManager($this->root, ['core' => false]);

    expect($manager->isEnabled('core'))->toBeTrue();
});

it('reports missing dependencies', function () {
    writeModule($this->root, ['name' => 'Seo', 'slug' => 'seo', 'type' => 'extension', 'depends_on' => ['ghost']]);

    $manager = new ModuleManager($this->root);

    expect($manager->isEnabled('seo'))->toBeFalse()
        ->and($manager->disabledReasons()['seo'])->toBe('dependency [ghost] does not exist');
});
