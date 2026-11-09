<?php

declare(strict_types=1);

namespace Modules\Core\Http;

/**
 * The indentation of the HTML the views write (2026-11-09, speed): about two thirds of a list page (e.g. 3.6 MB → 1.3 MB
 * for 100 rows) is the spaces at the start and end of lines, which the browser reads and throws away. strip() removes
 * them; the line breaks themselves stay (line comments in the JavaScript of attributes, blank lines of text shown with
 * `white-space: pre-line` keep their meaning), and <pre>, <textarea>, <script> and <style> are kept as they are. Applied
 * to the pages (Middleware\StripHtmlIndentation) and to the Livewire components, first render and updates alike
 * (Livewire\StripsIndentation), so the page and its updates agree.
 */
final class HtmlIndentation
{
    public static function strip(string $html): string
    {
        $out = '';
        $offset = 0;
        // the blocks whose whitespace is content, found by plain string search (no backtracking over large scripts)
        while (preg_match('~<(pre|textarea|script|style)\b~i', $html, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start = $match[0][1];
            $close = stripos($html, '</'.strtolower($match[1][0]), $start);
            $end = $close === false ? false : strpos($html, '>', $close);
            if ($end === false) {
                break;
            }

            $out .= self::squeeze(substr($html, $offset, $start - $offset)).substr($html, $start, $end + 1 - $start);
            $offset = $end + 1;
        }

        return $out.self::squeeze(substr($html, $offset));
    }

    private static function squeeze(string $part): string
    {
        return $part === '' ? '' : (string) (preg_replace(['/[ \t]+\n/', '/\n[ \t]+/'], "\n", $part) ?? $part);
    }
}
