<?php

/*
 * 帳票出力 (replaces the legacy PrintCreator, SYSTEM_BRAIN 08_PRINTCREATOR / I-01): the 書式 of placed fields, what the
 * designer may save, file names (legacy 見積書番号_得意先名), and the PDF writer / renderer.
 */

use Carbon\CarbonImmutable;
use Modules\Core\Application\Documents\DocumentRenderer;
use Modules\Core\Application\Documents\Pdf\JapanesePdf;
use Modules\Core\Application\Documents\RecordData;
use Modules\Core\Domain\Documents\DocumentValue;
use Modules\Core\Domain\Documents\FileNamePattern;
use Modules\Core\Domain\Documents\TemplateLayout;
use Modules\Core\Domain\Documents\ValueFormat;
use Modules\Core\Models\DocumentTemplate;

/** The text of a PDF made by JapanesePdf: its content streams, inflated. */
function pdfContent(string $pdf): string
{
    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);

    return implode("\n", array_map(fn (string $stream): string => @gzuncompress($stream) ?: $stream, $streams[1]));
}

/** A text as the PDF holds it: UCS-2 with the bytes \ ( ) escaped (e.g. 封 is 5C 01). */
function ucs2(string $text): string
{
    return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', '\\r'], (string) mb_convert_encoding($text, 'UCS-2BE', 'UTF-8'));
}

function pdfPageCount(string $pdf): int
{
    return preg_match_all('/\/Type \/Page\b/', $pdf);
}

it('formats values as numbers, amounts and Western or Japanese-era dates, else as shown', function () {
    $amount = new DocumentValue('¥1,234,567', 1234567);
    $price = new DocumentValue('¥12.5', '12.50');
    $date = new DocumentValue('2026-10-26', CarbonImmutable::parse('2026-10-26 09:30'));

    expect(ValueFormat::Display->apply($amount))->toBe('¥1,234,567')
        ->and(ValueFormat::Number->apply($amount))->toBe('1,234,567')
        ->and(ValueFormat::Yen->apply($price))->toBe('¥12.5')
        ->and(ValueFormat::Date->apply($date))->toBe('2026/10/26')
        ->and(ValueFormat::DateJapanese->apply($date))->toBe('2026年10月26日')
        ->and(ValueFormat::Wareki->apply($date))->toBe('令和8年10月26日')
        ->and(ValueFormat::DateTime->apply($date))->toBe('2026/10/26 09:30')
        ->and(ValueFormat::Wareki->apply(new DocumentValue('', '2019-05-01')))->toBe('令和元年5月1日')
        ->and(ValueFormat::Wareki->apply(new DocumentValue('', '2019-04-30')))->toBe('平成31年4月30日')
        // a text under a number or date format prints as shown
        ->and(ValueFormat::Number->apply(DocumentValue::text('株式会社得意先')))->toBe('株式会社得意先')
        ->and(ValueFormat::Date->apply(DocumentValue::text('未定')))->toBe('未定');
});

it('keeps what the designer may save: known types, clamped numbers, unique ids, tables with columns', function () {
    $elements = TemplateLayout::normalize([
        ['id' => 'a', 'type' => 'field', 'field' => 'customer_id', 'x' => -5, 'y' => 20, 'w' => 0, 'h' => 7, 'size' => 200, 'page' => 9, 'format' => 'yen', 'color' => 'red'],
        ['id' => 'a', 'type' => 'text', 'text' => "御中\r\n", 'x' => 100, 'y' => 30],
        ['type' => 'table', 'table' => 'lines', 'columns' => [['field' => 'product_name', 'width' => 60], ['field' => 'bad name']], 'rows' => 500],
        ['type' => 'table', 'table' => 'lines', 'columns' => []],
        ['type' => 'field', 'field' => '<script>'],
        ['type' => 'image'],
        'not an element',
    ], 2);

    expect($elements)->toHaveCount(3)
        ->and($elements[0])->toMatchArray(['id' => 'a', 'x' => 0.0, 'w' => 1.0, 'size' => 72.0, 'page' => 1, 'format' => 'yen', 'color' => '#000000', 'align' => 'left', 'overflow' => 'shrink'])
        ->and($elements[1]['id'])->not->toBe('a')
        ->and($elements[1]['text'])->toBe("御中\n")
        ->and($elements[2]['rows'])->toBe(200)
        ->and($elements[2]['columns'])->toBe([['field' => 'product_name', 'width' => 60.0, 'align' => 'left', 'format' => 'display']]);
});

it('names files from fields like the legacy 見積書番号_得意先名, without characters a file name cannot hold', function () {
    $values = ['number' => '0000123', 'customer_id' => '株式会社/得意先'];

    expect(FileNamePattern::render('{number}_{customer_id}', fn (string $field): string => $values[$field] ?? ''))->toBe('0000123_株式会社_得意先')
        ->and(FileNamePattern::render('{missing}', fn (): string => ''))->toBeNull()
        ->and(FileNamePattern::fields('見積書_{number}_{customer_id}'))->toBe(['number', 'customer_id'])
        ->and(FileNamePattern::clean(' a:b*c? '))->toBe('a_b_c');
});

it('measures Japanese text with half-width Latin, digits and kana', function () {
    expect(JapanesePdf::emWidth('納品書'))->toBe(3.0)
        ->and(JapanesePdf::emWidth('ABC123'))->toBe(3.0)
        ->and(JapanesePdf::emWidth('¥1,000'))->toBe(3.0)
        ->and(JapanesePdf::emWidth('ｶﾀｶﾅ'))->toBe(2.0);
});

it('draws fields, fixed texts and table rows, continuing long tables on more pages with ページ番号', function () {
    $template = new DocumentTemplate([
        'name' => '納品書',
        'pages' => [['width' => 210, 'height' => 297]],
        'layout' => ['elements' => TemplateLayout::normalize([
            ['type' => 'field', 'field' => 'customer_id', 'x' => 20, 'y' => 20, 'w' => 80, 'h' => 8, 'suffix' => ' 御中'],
            ['type' => 'field', 'field' => 'subtotal', 'x' => 20, 'y' => 30, 'w' => 40, 'h' => 8, 'format' => 'number', 'align' => 'right'],
            ['type' => 'field', 'field' => '_page', 'x' => 180, 'y' => 280, 'w' => 10, 'h' => 6],
            ['type' => 'text', 'text' => '納品書', 'x' => 90, 'y' => 10, 'w' => 30, 'h' => 8, 'bold' => true],
            ['type' => 'table', 'table' => 'lines', 'x' => 20, 'y' => 60, 'rows' => 2, 'rowHeight' => 7, 'border' => true, 'columns' => [
                ['field' => 'product_name', 'width' => 80], ['field' => 'amount', 'width' => 30, 'align' => 'right', 'format' => 'yen'],
            ]],
        ], 1)],
    ]);

    $row = fn (string $name, int $amount): array => ['product_name' => DocumentValue::text($name), 'amount' => new DocumentValue('¥'.$amount, $amount)];
    $record = new RecordData('1', '0000001', [
        'customer_id' => DocumentValue::text('株式会社得意先'),
        'subtotal' => new DocumentValue('¥12,500', 12500),
    ], ['lines' => [$row('長3封筒', 10000), $row('角2封筒', 2000), $row('洋形封筒', 500)]]);

    $pdf = app(DocumentRenderer::class)->render($template, [$record, $record->with([])]);
    $content = pdfContent($pdf);

    expect(substr($pdf, 0, 5))->toBe('%PDF-')
        // 3 rows on 2 lines a page: 2 pages per record
        ->and(pdfPageCount($pdf))->toBe(4)
        ->and($content)->toContain(ucs2('株式会社得意先 御中'))
        ->and($content)->toContain(ucs2('12,500'))
        ->and($content)->toContain(ucs2('洋形封筒'))
        ->and($content)->toContain(ucs2('¥10,000'))
        ->and($content)->toContain('('.ucs2('2').') Tj')
        ->and($content)->toContain('2 Tr')
        ->and($pdf)->toContain('/Encoding /UniJIS-UCS2-HW-H')
        ->and($pdf)->toContain('/BaseFont /MS-Gothic');
});
