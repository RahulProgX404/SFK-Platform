<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use Filament\Support\Assets\Theme;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Cache;

/**
 * Filament's stylesheet (its dist/theme.css, otherwise published as css/filament/filament/app.css) without the rules
 * that make every small change of a page restyle the whole page — served at GET /core/filament.css?v=<version> (a
 * year, immutable) as the panels' theme.
 *
 * Measured 2026-11-20 on a 100-row list (docs/performance.md): a few `:has()` rules made Chrome restyle the whole
 * document whenever an `aria-expanded`, a checkbox or an input wrapper changed anywhere — opening any drop-down,
 * ticking a row, opening the row 「…」 or the sidebar took 60–200 ms of main-thread work before the browser could
 * draw. Each rule below is replaced by one that styles the same elements without `:has()`:
 *   - the form builder's and the rich editor's toolbar come forward (z-index) while one of their menus is open: the
 *     menu's trigger keeps the focus while it is open, so `:focus-within` (no `:has()`);
 *   - an input wrapper's children other than its prefix and suffix fill it (`flex: 1`): Filament's wrappers hold
 *     prefix, content container and suffix, or (SelectColumn, SelectAction) the select itself — the same elements as
 *     "the content container, or every child when there is none", same specificity.
 *
 * A Filament update that changes one of these rules leaves it in place (nothing is replaced blindly) and
 * FilamentStylesheetTest fails, so the replacement is checked again.
 */
final class FilamentStylesheet
{
    /** search => replacement, in Filament's minified stylesheet */
    public const PATCHES = [
        '.fi-fo-builder .fi-fo-builder-add-between-items:has(.fi-dropdown-trigger>button[aria-expanded=true])' => '.fi-fo-builder .fi-fo-builder-add-between-items:focus-within',
        ':is(.fi-fo-rich-editor .fi-fo-rich-editor-toolbar,.fi-fo-rich-editor .fi-fo-rich-editor-floating-toolbar):has(.fi-fo-rich-editor-dropdown-tool-trigger[aria-expanded=true])' => ':is(.fi-fo-rich-editor .fi-fo-rich-editor-toolbar,.fi-fo-rich-editor .fi-fo-rich-editor-floating-toolbar):focus-within',
        '.fi-input-wrp:not(:has(.fi-input-wrp-content-ctn))>*' => '.fi-input-wrp>:not(.fi-input-wrp-prefix,.fi-input-wrp-suffix)',
    ];

    /** @var array{css: string, version: string}|null */
    private static ?array $bundle = null;

    /** The panels' theme: a <link> to the patched stylesheet (data-navigate-track: a new version reloads the page). */
    public static function theme(): Theme
    {
        return Theme::make('app')->html(fn (): string => route('core.filament.css', ['v' => self::bundle()['version']]));
    }

    /** @return array{css: string, version: string} */
    public static function bundle(): array
    {
        if (self::$bundle !== null) {
            return self::$bundle;
        }

        $source = self::source();
        $key = 'core:filament-css:'.md5($source.':'.(int) @filemtime($source).':'.md5(serialize(self::PATCHES)));

        return self::$bundle = Cache::remember($key, now()->addDay(), static function () use ($source): array {
            $css = self::patch((string) file_get_contents($source));

            return ['css' => $css, 'version' => substr(md5($css), 0, 12)];
        });
    }

    public static function patch(string $css): string
    {
        return str_replace(array_keys(self::PATCHES), array_values(self::PATCHES), $css);
    }

    /**
     * Patches whose rule is not in Filament's stylesheet (any = Filament changed it: check the replacement again).
     *
     * @return list<string>
     */
    public static function missing(): array
    {
        $css = (string) file_get_contents(self::source());

        return array_values(array_filter(array_keys(self::PATCHES), fn (string $search): bool => ! str_contains($css, $search)));
    }

    /** Filament's own stylesheet (the file `filament:assets` publishes). */
    public static function source(): string
    {
        return FilamentAsset::getTheme('app')->getPath();
    }

    public static function forget(): void
    {
        self::$bundle = null;
    }
}
