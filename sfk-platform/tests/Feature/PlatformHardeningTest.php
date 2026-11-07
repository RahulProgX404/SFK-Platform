<?php

/*
 * Browser protections on every response, HSTS over HTTPS, the panels' look as a cached stylesheet / script instead
 * of inside every page, and avatars drawn without an outside service.
 */

use App\Models\User;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsIconAlias;
use Modules\Core\Filament\Support\ThemeAssets;

it('sends the browser protections with every page', function () {
    $response = $this->get('/login')->assertOk();

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'self'")
        ->toContain("form-action 'self'")
        ->toContain("object-src 'none'")
        ->and($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->headers->get('Permissions-Policy'))->toContain('camera=()')
        ->and($response->headers->has('Strict-Transport-Security'))->toBeFalse();

    expect($this->get('https://localhost/login')->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000; includeSubDomains');
});

it('lets pages load the attachments from the object storage of the uploads, and from nowhere else', function () {
    expect($this->get('/login')->headers->get('Content-Security-Policy'))
        ->toContain("img-src 'self' data: blob:; ")
        ->toContain("connect-src 'self'; ");

    // Laravel Cloud: a private bucket; the attachments open through its temporary URLs
    config([
        'filesystems.disks.uploads-bucket' => [
            'driver' => 's3', 'key' => 'key', 'secret' => 'secret', 'region' => 'auto', 'bucket' => 'sfk-uploads',
            'endpoint' => 'https://storage.example.com', 'use_path_style_endpoint' => true,
        ],
        'filesystems.uploads' => 'uploads-bucket',
    ]);

    expect($this->get('/login')->headers->get('Content-Security-Policy'))
        ->toContain("img-src 'self' data: blob: https://storage.example.com; ")
        ->toContain("connect-src 'self' https://storage.example.com; ")
        ->toContain("default-src 'self'; ")
        ->toContain("frame-src 'self'; ");
});

it('serves the look of the panels as a stylesheet and a script the browser keeps', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    $bundle = ThemeAssets::bundle();

    $this->get('/erp')
        ->assertOk()
        ->assertSee(e(route('core.theme.css', ['v' => $bundle['version']])), false)
        ->assertSee(e(route('core.theme.js', ['v' => $bundle['version']])), false)
        // not inside the page any more
        ->assertDontSee('--kt-blue: #05467E', false)
        // the avatar is drawn here, no name goes to an outside service
        ->assertDontSee('ui-avatars.com', false);

    $css = $this->get(route('core.theme.css', ['v' => $bundle['version']]))->assertOk();
    expect($css->headers->get('Content-Type'))->toStartWith('text/css')
        ->and($css->headers->get('Cache-Control'))->toContain('immutable')
        ->and($css->getContent())->toContain('--kt-blue: #05467E')
        // the menus of the app header (e.g. エクスポート) open above the list's sticky search row (z-index 20)
        ->and($css->getContent())->toMatch('/\.sfk-app-header \.fi-dropdown-panel \{\s*z-index: 30;/')
        ->and($css->headers->has('Content-Security-Policy'))->toBeFalse();

    expect($this->get(route('core.theme.js', ['v' => $bundle['version']]))->assertOk()->getContent())
        ->toContain("Alpine.data('sfkAppFilter'");
});

it('draws the sidebar of sidebar.png: a grey column, ≡ at the right of the header, the open screen a white card with a blue bar', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    $css = ThemeAssets::bundle()['css'];

    expect($css)->toContain('--sfk-sidebar: #f3f4f6;')
        ->and($css)->toMatch('/\.fi-sidebar \{\s*background-color: var\(--sfk-sidebar\);/')
        ->and($css)->toMatch('/\.fi-sidebar-header>\.fi-sidebar-close-collapse-sidebar-btn \{\s*order: 2;\s*margin-inline-start: auto;/')
        ->and($css)->toMatch('/\.fi-sidebar-item\.fi-active>\.fi-sidebar-item-btn \{\s*background-color: var\(--sfk-surface\);/')
        ->and($css)->toMatch('/\.fi-sidebar-item\.fi-active>\.fi-sidebar-item-btn::before \{[^}]*background-color: var\(--sfk-primary\);/')
        ->and($css)->toMatch('/\.fi-sidebar-nav>\.fi-sidebar-nav-groups>li\+li \{\s*border-top: 1px solid var\(--sfk-line\);/');

    // ≡ (Bars 3) closes the sidebar: shown on an app screen (no top bar there)
    $this->get('/erp/orders')->assertOk()->assertSee('fi-sidebar-close-collapse-sidebar-btn', false);
    expect(FilamentIcon::resolve(PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON))->toBe(Heroicon::OutlinedBars3);
});
