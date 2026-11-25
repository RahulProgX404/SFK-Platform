<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;

/**
 * The look and the Alpine components of every panel, served as one stylesheet and one script that the browser keeps
 * (GET /core/theme.css|js?v=<version>, a year, immutable) instead of ~125 KB inside every page. They are still written
 * in the Blade views core::filament.theme (with core::filament.ui and core::filament.documents.styles),
 * core::filament.design, core::filament.app-filter-script, core::filament.user-popup-script,
 * core::filament.row-menu-script and core::filament.notification-pulse-script: their <style> and <script> contents are put together once
 * per change of those files (the version follows the content).
 *
 * The styles of screens drawn by a Livewire component (the dashboard, the quick entries, the スペース wizard) are here
 * too (SCREEN_STYLES), never a <style> inside the component: each Livewire update (a filter, a step, a live redraw)
 * would send them again.
 */
final class ThemeAssets
{
    /** Styles of single screens, after the general look (as they were after it in the page); scoped to their own classes. */
    private const SCREEN_STYLES = ['core::filament.portal-styles', 'core::filament.quick-entry-styles', 'core::filament.space-wizard-styles'];

    /** Views rendered into the assets, in order (core::filament.design, the current look, before the screens' styles). */
    private const VIEWS = ['core::filament.theme', 'core::filament.design', ...self::SCREEN_STYLES, 'core::filament.app-filter-script',
        'core::filament.user-popup-script', 'core::filament.row-menu-script', 'core::filament.table-links-script', 'core::filament.realtime-script',
        'core::filament.notification-pulse-script', 'core::filament.live-data-script'];

    /** Views whose changes make new assets (the ones above and what they include). */
    private const SOURCES = [...self::VIEWS, 'core::filament.ui', 'core::filament.documents.styles'];

    /** @var array{css: string, js: string, version: string}|null */
    private static ?array $bundle = null;

    /** @var list<string> views of other modules (e.g. the messenger's unread badge), after Core's */
    private static array $extra = [];

    /**
     * Adds a module's view (its <style> / <script>) to every panel's theme: small scripts every page needs (e.g. the
     * messenger's badge), and the styles of a module's screens that Livewire redraws (e.g. 売上分析), which would
     * otherwise travel with each update. The browser keeps the theme, so it is loaded once per version.
     */
    public static function extend(string $view): void
    {
        if (! in_array($view, self::$extra, true)) {
            self::$extra[] = $view;
            self::$bundle = null;
        }
    }

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
        // compiled afresh: where Blade does not check its compiled views (VIEW_CHECK_CACHE_TIMESTAMPS=false) a stale
        // one would otherwise be kept under the new version
        $finder = view()->getFinder();
        foreach ([...self::SOURCES, ...self::$extra] as $view) {
            Blade::compile($finder->find($view));
        }

        $html = implode("\n", array_map(fn (string $view): string => view($view)->render(), [...self::VIEWS, ...self::$extra]));

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
        foreach ([...self::SOURCES, ...self::$extra] as $view) {
            $path = $finder->find($view);
            $parts[] = $path.':'.(int) @filemtime($path);
        }

        return md5(implode('|', $parts));
    }
}
