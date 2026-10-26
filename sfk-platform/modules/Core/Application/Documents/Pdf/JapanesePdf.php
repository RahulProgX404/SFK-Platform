<?php

declare(strict_types=1);

namespace Modules\Core\Application\Documents\Pdf;

use setasign\Fpdi\Fpdi;

/**
 * PDF writer of the 帳票 (FPDF + FPDI, both MIT): pages of an imported background PDF with text drawn on top, in mm.
 *
 * Japanese text uses the standard Japanese fonts of PDF (Adobe-Japan1 ゴシック / 明朝, not embedded: every PDF viewer
 * and printer driver in Japanese supplies them) through the UniJIS-UCS2-HW-H CMap, so no font file is needed and
 * the files stay small. Latin letters, digits and half-width kana are half as wide as the other characters, which
 * lets the writer measure text exactly for alignment, shrinking and wrapping.
 */
final class JapanesePdf extends Fpdi
{
    private const CMAP = 'UniJIS-UCS2-HW-H';

    /** key => [font name, serif]; viewers without these fonts use their own Japanese ゴシック / 明朝 */
    private const FONTS = [
        'gothic' => ['MS-Gothic', false],
        'mincho' => ['MS-Mincho', true],
    ];

    /** 禁則: characters a wrapped line does not start with. */
    private const NO_LINE_START = '、。，．・：；？！ー－）」』】〕〉》｝］っゃゅょッャュョ,.:;?!)]}';

    /** Top of the glyphs above the baseline and line height, as part of the font size. */
    private const ASCENT = 0.88;

    private const LINE_HEIGHT = 1.2;

    /** Space between a box's edge and its text, in mm. */
    private const PADDING = 0.5;

    private const MIN_SIZE = 4.0;

    public function __construct()
    {
        parent::__construct('P', 'mm', 'A4');

        $this->SetAutoPageBreak(false);
        $this->SetMargins(0, 0, 0);
        $this->SetCreator('SFK', true);

        foreach (self::FONTS as $key => [$name, $serif]) {
            // a Type0 font of FPDF's "additional types": written by _puttype0()
            $this->fonts[$key] = [
                'i' => count($this->fonts) + 1, 'type' => 'Type0', 'name' => $name, 'serif' => $serif,
                'subsetted' => false, 'cw' => [], 'up' => -100, 'ut' => 50, 'desc' => [],
            ];
        }
    }

    /**
     * Imports the pages of a background PDF.
     *
     * @return list<string> template ids for useTemplate(), by page
     */
    public function importBackground(string $file): array
    {
        $count = $this->setSourceFile($file);
        $pages = [];
        for ($page = 1; $page <= $count; $page++) {
            $pages[] = $this->importPage($page);
        }

        return $pages;
    }

    /** A new page of the size (mm), with the imported background page when given. */
    public function newPage(float $width, float $height, ?string $background = null): void
    {
        $this->AddPage($width > $height ? 'L' : 'P', [$width, $height]);

        if ($background !== null) {
            $this->useTemplate($background, 0, 0, $width, $height);
        }
    }

    /**
     * Writes text into a box (mm from the top left corner of the page).
     *
     * @param  array{font?: string, size?: float, bold?: bool, color?: string, align?: string, valign?: string, overflow?: string, border?: bool}  $style
     */
    public function textBox(float $x, float $y, float $width, float $height, string $text, array $style): void
    {
        if ($style['border'] ?? false) {
            $this->SetDrawColor(0, 0, 0);
            $this->SetLineWidth(0.2);
            $this->Rect($x, $y, $width, $height);
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        if (trim($text) === '') {
            return;
        }

        $font = array_key_exists($style['font'] ?? '', self::FONTS) ? (string) $style['font'] : 'gothic';
        $size = (float) ($style['size'] ?? 10.5);
        $overflow = $style['overflow'] ?? 'shrink';
        $innerWidth = max(0.1, $width - 2 * self::PADDING);
        $lines = explode("\n", $text);

        if ($overflow === 'wrap') {
            $lines = $this->wrap($lines, $innerWidth, $size);
        } elseif ($overflow === 'shrink') {
            $size = $this->fittingSize($lines, $innerWidth, $height, $size);
        }

        $sizeMm = $size * 25.4 / 72;
        $lineHeight = $sizeMm * self::LINE_HEIGHT;
        if ($overflow === 'wrap') {
            // the lines that fit into the box
            $lines = array_slice($lines, 0, max(1, (int) floor(($height + 0.01) / $lineHeight)));
        }

        $top = match ($style['valign'] ?? 'middle') {
            'top' => $y,
            'bottom' => $y + $height - count($lines) * $lineHeight,
            default => $y + ($height - count($lines) * $lineHeight) / 2,
        };

        [$red, $green, $blue] = self::rgb((string) ($style['color'] ?? '#000000'));
        $out = ['q', sprintf('%.3F %.3F %.3F rg %.3F %.3F %.3F RG', $red, $green, $blue, $red, $green, $blue)];
        if ($overflow === 'clip') {
            $out[] = sprintf('%.2F %.2F %.2F %.2F re W n', $x * $this->k, ($this->h - $y) * $this->k, $width * $this->k, -$height * $this->k);
        }
        // 太字: the glyphs are also stroked
        $out[] = ($style['bold'] ?? false) ? sprintf('2 Tr %.3F w', $size * 0.035) : '0 Tr';

        foreach ($lines as $index => $line) {
            if ($line === '') {
                continue;
            }

            $lineWidth = self::emWidth($line) * $sizeMm;
            $left = match ($style['align'] ?? 'left') {
                'center' => $x + ($width - $lineWidth) / 2,
                'right' => $x + $width - self::PADDING - $lineWidth,
                default => $x + self::PADDING,
            };
            $baseline = $top + $index * $lineHeight + ($lineHeight - $sizeMm) / 2 + $sizeMm * self::ASCENT;

            $out[] = sprintf(
                'BT /F%d %.2F Tf %.2F %.2F Td (%s) Tj ET',
                $this->fonts[$font]['i'],
                $size,
                $left * $this->k,
                ($this->h - $baseline) * $this->k,
                $this->_escape(self::encode($line)),
            );
        }

        $out[] = 'Q';
        $this->_out(implode("\n", $out));
    }

    /** Width of a text in em: half-width characters 0.5, the others 1. */
    public static function emWidth(string $text): float
    {
        $width = 0.0;
        foreach (mb_str_split($text) as $character) {
            $width += self::isHalfWidth(mb_ord($character)) ? 0.5 : 1.0;
        }

        return $width;
    }

    /**
     * Type0 font (FPDF calls _put<type> for fonts of other types): the CMap maps UCS-2 to CIDs of Adobe-Japan1.
     *
     * @param  array<string, mixed>  $font
     */
    protected function _puttype0(array $font): void
    {
        $name = (string) $font['name'];

        $this->_newobj();
        $this->_put('<</Type /Font /Subtype /Type0 /BaseFont /'.$name.'-'.self::CMAP.' /Encoding /'.self::CMAP.' /DescendantFonts ['.($this->n + 1).' 0 R]>>');
        $this->_put('endobj');

        // full-width glyphs 1000; CIDs 231–389 (half-width Latin and kana) 500
        $this->_newobj();
        $this->_put('<</Type /Font /Subtype /CIDFontType0 /BaseFont /'.$name.' /CIDSystemInfo <</Registry (Adobe) /Ordering (Japan1) /Supplement 2>> /FontDescriptor '.($this->n + 1).' 0 R /DW 1000 /W [231 389 500]>>');
        $this->_put('endobj');

        $this->_newobj();
        $this->_put('<</Type /FontDescriptor /FontName /'.$name.' /Flags '.($font['serif'] ? 6 : 4).' /FontBBox [-200 -331 1100 903] /ItalicAngle 0 /Ascent 880 /Descent -120 /CapHeight 740 /StemV 80>>');
        $this->_put('endobj');
    }

    /**
     * The font size (pt) at which the lines fit into the box, at most the given size.
     *
     * @param  list<string>  $lines
     */
    private function fittingSize(array $lines, float $width, float $height, float $size): float
    {
        $widest = max(array_map(self::emWidth(...), $lines));
        $sizeMm = $size * 25.4 / 72;

        $scale = 1.0;
        if ($widest * $sizeMm > $width) {
            $scale = $width / ($widest * $sizeMm);
        }
        if (count($lines) * $sizeMm * self::LINE_HEIGHT * $scale > $height) {
            $scale = min($scale, $height / (count($lines) * $sizeMm * self::LINE_HEIGHT));
        }

        return max(self::MIN_SIZE, $size * $scale);
    }

    /**
     * Breaks the lines so each fits the width, between characters (Japanese has no spaces).
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function wrap(array $lines, float $width, float $size): array
    {
        $limit = $width / ($size * 25.4 / 72);
        $wrapped = [];
        foreach ($lines as $line) {
            $current = '';
            $currentWidth = 0.0;
            foreach (mb_str_split($line) as $character) {
                $characterWidth = self::isHalfWidth(mb_ord($character)) ? 0.5 : 1.0;
                if ($current !== '' && $currentWidth + $characterWidth > $limit + 0.001) {
                    // 禁則: 「。」「、」 etc. take the last character of the line with them to the next one
                    $carried = '';
                    if (str_contains(self::NO_LINE_START, $character) && mb_strlen($current) > 1) {
                        $carried = mb_substr($current, -1);
                        $current = mb_substr($current, 0, -1);
                    }
                    $wrapped[] = $current;
                    $current = $carried;
                    $currentWidth = self::emWidth($carried);
                }
                $current .= $character;
                $currentWidth += $characterWidth;
            }
            $wrapped[] = $current;
        }

        return $wrapped;
    }

    /** Characters the CMap maps to half-width glyphs: ASCII, ¥ and ‾, half-width kana. */
    private static function isHalfWidth(int $codePoint): bool
    {
        return ($codePoint >= 0x20 && $codePoint <= 0x7E) || $codePoint === 0xA5 || $codePoint === 0x203E
            || ($codePoint >= 0xFF61 && $codePoint <= 0xFF9F);
    }

    /** UCS-2 big endian (the CMap's code space); characters outside it become 〓. */
    private static function encode(string $text): string
    {
        $text = (string) preg_replace('/[^\x{0}-\x{FFFF}]/u', '〓', $text);

        return (string) mb_convert_encoding($text, 'UCS-2BE', 'UTF-8');
    }

    /** @return array{0: float, 1: float, 2: float} */
    private static function rgb(string $hex): array
    {
        if (preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m) !== 1) {
            return [0.0, 0.0, 0.0];
        }

        return [hexdec($m[1]) / 255, hexdec($m[2]) / 255, hexdec($m[3]) / 255];
    }
}
