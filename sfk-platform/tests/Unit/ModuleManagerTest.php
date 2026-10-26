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

it('knows the conventional parts of a module and reads the modules from its cache without the folders', function () {
    File::ensureDirectoryExists($this->root.'/Sales/routes');
    File::put($this->root.'/Sales/routes/web.php', '<?php');
    File::ensureDirectoryExists($this->root.'/Sales/Filament/Resources');
    $cache = $this->root.'/modules.php';

    $manager = new ModuleManager($this->root, cachePath: $cache);
    expect($manager->get('sales')->has('routes/web.php'))->toBeTrue()
        ->and($manager->get('sales')->has('Filament/Resources'))->toBeTrue()
        ->and($manager->get('sales')->has('routes/api.php'))->toBeFalse();

    File::put($cache, '<?php return '.var_export($manager->export(), true).';');
    File::deleteDirectory($this->root.'/Sns');

    // the cache is read instead of the folders: the removed module is still there until the cache is rebuilt
    $cached = new ModuleManager($this->root, cachePath: $cache);
    expect(array_keys($cached->enabled()))->toBe(['core', 'master-data', 'sales', 'sns'])
        ->and($cached->get('sales')->parts)->toBe(['routes/web.php', 'Filament/Resources']);
});
