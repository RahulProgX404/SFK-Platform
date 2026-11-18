<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Tables;

use Filament\Support\Facades\FilamentView;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use WeakMap;

/**
 * The link of every table cell to its record (docs/performance.md, measured 2026-11-18). Filament writes each cell as
 * `<a href … wire:navigate.hover>` (Filament\Support\generate_href_html), and Livewire makes every such link an Alpine
 * root of its own that prefetches the record page after 60 ms of hover:
 *
 * - a 100-row list has ~2,300 of them: they took ~280 ms of the ~770 ms Livewire / Alpine start-up of the page;
 * - moving the mouse over 20 rows sent 20 full record pages to the server (110–280 ms, 20–57 queries each), which on a
 *   small server queue up before the user's real click;
 * - on the server, each cell matched its URL against the SPA exceptions (Str::is) again.
 *
 * Here the cell is a plain link — new tab, middle click, the address on hover all keep working — marked
 * `data-sfk-navigate` when the panel opens it in place (SPA); one click listener for the whole page
 * (core::filament.table-links-script) opens it with Alpine.navigate(), as wire:navigate did. No hover prefetch for rows.
 *
 * Swapped into Filament's table view by a Blade precompiler (register()): when Filament changes the line, the
 * precompiler no longer matches and the stock link comes back (a test in MasterData's PartnerResourceTest notices).
 */
final class RecordLink
{
    /** The calls in vendor/filament/tables/resources/views/index.blade.php that write the cell / record links. */
    public const REPLACEMENTS = [
        '\Filament\Support\generate_href_html($columnUrl ?: $recordUrl, $columnUrl ? $column->shouldOpenUrlInNewTab() : $openRecordUrlInNewTab, hasNestedClickEventHandler: true)' => '\Modules\Core\Filament\Tables\RecordLink::attributes($columnUrl ?: $recordUrl, $columnUrl ? $column->shouldOpenUrlInNewTab() : $openRecordUrlInNewTab)',
        '\Filament\Support\generate_href_html($recordUrl, $openRecordUrlInNewTab, hasNestedClickEventHandler: true)' => '\Modules\Core\Filament\Tables\RecordLink::attributes($recordUrl, $openRecordUrlInNewTab)',
    ];

    /**
     * folder of a URL (…/orders/) => opened in place (SPA), per HTTP request: a long-lived worker (tests, Octane) serves
     * panels with other settings next — a plain static leaked the answer of one request into the next.
     *
     * @var WeakMap<Request, array<string, bool>>|null
     */
    private static ?WeakMap $spaByFolder = null;

    public static function register(): void
    {
        Blade::precompiler(static function (string $template): string {
            if (! str_contains($template, 'generate_href_html($')) {
                return $template;
            }

            return strtr($template, self::REPLACEMENTS);
        });
    }

    public static function attributes(?string $url, bool $openInNewTab = false): Htmlable
    {
        if (blank($url)) {
            return new HtmlString('');
        }

        $html = 'href="'.e($url).'"';
        if ($openInNewTab) {
            return new HtmlString($html.' target="_blank"');
        }

        return new HtmlString(self::opensInPlace($url) ? $html.' data-sfk-navigate' : $html);
    }

    /**
     * Whether the panel opens the URL in place (Filament's SPA mode and its URL exceptions), decided once per folder:
     * the rows of a table differ only in the record key at the end.
     */
    private static function opensInPlace(string $url): bool
    {
        $folder = substr($url, 0, (int) strrpos($url, '/') + 1);
        $request = request();
        self::$spaByFolder ??= new WeakMap;
        $known = self::$spaByFolder[$request] ?? [];
        if (! array_key_exists($folder, $known)) {
            $known[$folder] = FilamentView::hasSpaMode($url);
            self::$spaByFolder[$request] = $known;
        }

        return $known[$folder];
    }
}
