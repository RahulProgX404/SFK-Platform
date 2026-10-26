<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use Illuminate\Support\Facades\Cache;

/**
 * The look and the Alpine components of every panel, served as one stylesheet and one script that the browser keeps
 * (GET /core/theme.css|js?v=<version>, a year, immutable) instead of ~125 KB inside every page. They are still written
 * in the Blade views core::filament.theme (with core::filament.ui and core::filament.documents.styles) and
 * core::filament.app-filter-script: their <style> and <script> contents are put together once per change of those
 * files (the version follows the content).
 */
final class ThemeAssets
{
    /** Views rendered into the assets, in order. */
    private const VIEWS = ['core::filament.theme', 'core::filament.app-filter-script'];

    /** Views whose changes make new assets (the ones above and what they include). */
    private const SOURCES = [...self::VIEWS, 'core::filament.ui', 'core::filament.documents.styles'];

    /** @var array{css: string, js: string, version: string}|null */
    private static ?array $bundle = null;

    /** @return array{css: string, js: string, version: string} */
    public static function bundle(): array
    {
        if (self::$bundle !== null) {
            return self::$bundle;
        }

        $fingerprint = self::fingerprint();

        return self::$bundle = Cache::remember('core:theme-assets:'.$fingerprint, now()->addDay(), self::build(...));
    }

    /** <link> and <script> of the assets, for the end of <head> (the script registers the Alpine components before Alpine starts). */
    public static function headTags(): string
    {
        $version = self::bundle()['version'];

        return '<link rel="stylesheet" href="'.e(route('core.theme.css', ['v' => $version])).'">'
            .'<script src="'.e(route('core.theme.js', ['v' => $version])).'"></script>';
    }

    public static function forget(): void
    {
        self::$bundle = null;
    }

    /** @return array{css: string, js: string, version: string} */
    private static function build(): array
    {
        $html = implode("\n", array_map(fn (string $view): string => view($view)->render(), self::VIEWS));

        preg_match_all('#<style[^>]*>(.*?)</style>#s', $html, $styles);
        preg_match_all('#<script[^>]*>(.*?)</script>#s', $html, $scripts);
        $css = trim(implode("\n", $styles[1]));
        $js = trim(implode(";\n", $scripts[1]));

        return ['css' => $css, 'js' => $js, 'version' => substr(md5($css."\0".$js), 0, 12)];
    }

    /** Paths and change times of the source views. */
    private static function fingerprint(): string
    {
        $finder = view()->getFinder();
        $parts = [];
        foreach (self::SOURCES as $view) {
            $path = $finder->find($view);
            $parts[] = $path.':'.(int) @filemtime($path);
        }

        return md5(implode('|', $parts));
    }
}
