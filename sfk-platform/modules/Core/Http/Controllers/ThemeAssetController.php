<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\Response;
use Modules\Core\Filament\Support\FilamentStylesheet;
use Modules\Core\Filament\Support\ThemeAssets;

/**
 * The panels' stylesheet and script (ThemeAssets) and Filament's stylesheet (FilamentStylesheet): the URL carries the
 * version, so browsers keep them for a year.
 */
final class ThemeAssetController
{
    public function css(): Response
    {
        return self::asset(ThemeAssets::bundle()['css'], 'text/css');
    }

    public function js(): Response
    {
        return self::asset(ThemeAssets::bundle()['js'], 'application/javascript');
    }

    /** Filament's stylesheet without the rules that restyle the whole page on every change (FilamentStylesheet). */
    public function filament(): Response
    {
        return self::asset(FilamentStylesheet::bundle()['css'], 'text/css');
    }

    private static function asset(string $body, string $type): Response
    {
        return response($body, 200, [
            'Content-Type' => $type.'; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
