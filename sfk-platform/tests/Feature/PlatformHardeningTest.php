<?php

/*
 * Browser protections on every response, HSTS over HTTPS, the panels' look as a cached stylesheet / script instead
 * of inside every page, and avatars drawn without an outside service.
 */

use App\Models\User;
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

it('serves the look of the panels as a stylesheet and a script the browser keeps', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    $bundle = ThemeAssets::bundle();

    $this->get('/erp')
        ->assertOk()
        ->assertSee(e(route('core.theme.css', ['v' => $bundle['version']])), false)
        ->assertSee(e(route('core.theme.js', ['v' => $bundle['version']])), false)
        // not inside the page any more
        ->assertDontSee('--kt-blue: #3498db', false)
        // the avatar is drawn here, no name goes to an outside service
        ->assertDontSee('ui-avatars.com', false);

    $css = $this->get(route('core.theme.css', ['v' => $bundle['version']]))->assertOk();
    expect($css->headers->get('Content-Type'))->toStartWith('text/css')
        ->and($css->headers->get('Cache-Control'))->toContain('immutable')
        ->and($css->getContent())->toContain('--kt-blue: #3498db')
        ->and($css->headers->has('Content-Security-Policy'))->toBeFalse();

    expect($this->get(route('core.theme.js', ['v' => $bundle['version']]))->assertOk()->getContent())
        ->toContain("Alpine.data('sfkAppFilter'");
});
