<?php

/*
 * Speed of the clicks that never reach the server (performance pass 2026-11-20, docs/performance.md): a `:has()` rule
 * whose argument changes when a drop-down opens, a row is ticked or a field changes makes Chrome restyle the whole
 * page — 60–210 ms of main-thread work per click on a 100-row list or the 受注 form, measured. Filament's stylesheet is
 * served without those rules (FilamentStylesheet) and the theme does not bring them back.
 */

use App\Models\User;
use Modules\Core\Filament\Support\FilamentStylesheet;
use Modules\Core\Filament\Support\ThemeAssets;

it('finds every rule it replaces in Filament\'s stylesheet (a Filament update that changes one fails here)', function () {
    expect(FilamentStylesheet::missing())->toBe([]);
});

it('serves Filament\'s stylesheet without the :has() rules that restyled the whole page', function () {
    $css = FilamentStylesheet::bundle()['css'];

    foreach (FilamentStylesheet::PATCHES as $search => $replacement) {
        expect($css)->not->toContain($search)->toContain($replacement);
    }
    // nothing reacts to a menu opening (aria-expanded) or to whether an input wrapper has a content container
    expect($css)->not->toMatch('/:has\([^{]*aria-expanded/')
        ->not->toContain(':not(:has(.fi-input-wrp-content-ctn))')
        // the rest of Filament's stylesheet is untouched
        ->and(strlen($css))->toBeGreaterThan((int) (filesize(FilamentStylesheet::source()) * 0.99));
});

it('links the panels to that stylesheet, which the browser keeps and loads without a session', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    $version = FilamentStylesheet::bundle()['version'];

    $this->get('/erp')
        ->assertOk()
        ->assertSee(e(route('core.filament.css', ['v' => $version])), false)
        ->assertDontSee('css/filament/filament/app.css', false);

    auth()->logout();
    $this->flushSession();

    $response = $this->get(route('core.filament.css', ['v' => $version]))->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('text/css')
        ->and($response->headers->get('Cache-Control'))->toContain('immutable')
        // no session started for it (no cookie, no signed-in user read from the database)
        ->and($response->headers->getCookies())->toBe([])
        ->and($response->getContent())->toBe(FilamentStylesheet::bundle()['css']);

    // the theme's own stylesheet and script are built and served the same way, also for a first visitor
    ThemeAssets::forget();
    $bundle = ThemeAssets::bundle();
    $css = $this->get(route('core.theme.css', ['v' => $bundle['version']]))->assertOk();
    expect($css->headers->getCookies())->toBe([])
        ->and($this->get(route('core.theme.js', ['v' => $bundle['version']]))->assertOk()->headers->getCookies())->toBe([]);
});

it('keeps the theme free of the :has() rules that restyled the whole page', function () {
    $css = ThemeAssets::bundle()['css'];

    expect($css)
        // a selected row: Filament marks it .fi-selected itself
        ->not->toContain(':has(.fi-ta-record-checkbox:checked)')
        ->toMatch('/\.fi-ta-row\.fi-selected>td \{/')
        // one-line fields: textarea wrappers are .fi-fo-textarea
        ->not->toContain(':has(textarea)')
        ->toContain('.fi-input-wrp:not(.fi-fo-textarea) {');
});
