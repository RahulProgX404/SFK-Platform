<?php

use App\Modules\Module;
use App\Modules\ModuleManager;
use App\Modules\ModuleType;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->root = storage_path('framework/testing/make-module-'.uniqid());
    File::ensureDirectoryExists($this->root);
    app()->instance(ModuleManager::class, new ModuleManager($this->root));
});

afterEach(fn () => File::deleteDirectory($this->root));

it('scaffolds an extension module with its own panel and api', function () {
    $this->artisan('make:module', ['name' => 'SeoAnalyzer', '--type' => 'extension'])->assertSuccessful();

    $dir = $this->root.'/SeoAnalyzer';
    $manifest = json_decode(File::get($dir.'/module.json'), true);
    $module = Module::fromManifest($manifest, $dir);

    expect($module->slug)->toBe('seo-analyzer')
        ->and($module->type)->toBe(ModuleType::Extension)
        ->and(File::exists($dir.'/Filament/SeoAnalyzerPanelProvider.php'))->toBeTrue()
        ->and(File::exists($dir.'/routes/api.php'))->toBeTrue()
        ->and(File::get($dir.'/Config/config.php'))->toContain("env('SEO_ANALYZER_PANEL_PATH', 'seo-analyzer')")
        ->and(File::exists($dir.'/Domain/.gitkeep'))->toBeTrue();
});

it('scaffolds a business module that plugs into the ERP panel', function () {
    $this->artisan('make:module', ['name' => 'Sales'])->assertSuccessful();

    $manifest = json_decode(File::get($this->root.'/Sales/module.json'), true);

    expect($manifest['type'])->toBe('business')
        ->and($manifest['panel'])->toBe('erp')
        ->and($manifest['filament_plugin'])->toBe('Modules\\Sales\\Filament\\SalesPlugin')
        ->and(File::exists($this->root.'/Sales/Filament/SalesPlugin.php'))->toBeTrue();
});

it('rejects reserved slugs and invalid names', function () {
    $this->artisan('make:module', ['name' => 'Mail'])->assertFailed();
    $this->artisan('make:module', ['name' => 'bad_name'])->assertFailed();
    $this->artisan('make:module', ['name' => 'Kernelish', '--type' => 'kernel'])->assertFailed();
});
