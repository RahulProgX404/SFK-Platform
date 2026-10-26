<?php

declare(strict_types=1);

namespace Modules\Core\Application\Documents;

use Carbon\CarbonImmutable;
use Modules\Core\Application\Documents\Pdf\JapanesePdf;
use Modules\Core\Domain\Documents\DocumentValue;
use Modules\Core\Domain\Documents\ValueFormat;
use Modules\Core\Models\DocumentTemplate;

/**
 * Draws a 帳票テンプレート for records into one PDF: per record the template's pages (the background PDF, or blank
 * sheets) with the placed fields, fixed texts and tables. When a table has more rows than its lines, the record
 * continues on another copy of all the template's pages (the header fields repeated) until every row is printed.
 * ページ番号 / 総ページ数 count the pages of each record's document.
 */
final class DocumentRenderer
{
    /**
     * @param  iterable<RecordData>  $records
     * @param  list<array<string, mixed>>|null  $elements  elements to draw instead of the saved ones (preview)
     */
    public function render(DocumentTemplate $template, iterable $records, ?string $userName = null, ?array $elements = null): string
    {
        $pdf = new JapanesePdf;
        $pdf->SetTitle($template->name, true);
        $pdf->SetAutoPageBreak(false);

        $backgrounds = ($file = $template->backgroundFile()) !== null ? $pdf->importBackground($file) : [];
        $pages = $template->pages !== [] ? $template->pages : [['width' => 210.0, 'height' => 297.0]];
        $elements ??= $template->elements();
        $today = CarbonImmutable::today();

        $drawn = false;
        foreach ($records as $record) {
            $drawn = true;
            $copies = $this->copies($elements, $record);
            $total = $copies * count($pages);
            $base = $record->with([
                '_today' => new DocumentValue($today->format('Y-m-d'), $today),
                '_user' => DocumentValue::text((string) $userName),
                '_pages' => new DocumentValue((string) $total, $total),
            ]);

            for ($copy = 0; $copy < $copies; $copy++) {
                foreach ($pages as $index => $page) {
                    $number = $copy * count($pages) + $index + 1;
                    $pdf->newPage((float) $page['width'], (float) $page['height'], $backgrounds[$index] ?? null);
                    $data = $base->with(['_page' => new DocumentValue((string) $number, $number)]);

                    foreach ($elements as $element) {
                        if ((int) $element['page'] === $index) {
                            $this->draw($pdf, $element, $data, $copy);
                        }
                    }
                }
            }
        }

        if (! $drawn) {
            // an empty file still opens: the template's pages without values
            foreach ($pages as $index => $page) {
                $pdf->newPage((float) $page['width'], (float) $page['height'], $backgrounds[$index] ?? null);
            }
        }

        return $pdf->Output('S');
    }

    /**
     * How many copies of the template's pages the record needs for all the rows of its tables.
     *
     * @param  list<array<string, mixed>>  $elements
     */
    private function copies(array $elements, RecordData $record): int
    {
        $copies = 1;
        foreach ($elements as $element) {
            if ($element['type'] === 'table') {
                $copies = max($copies, (int) ceil(count($record->rows((string) $element['table'])) / max(1, (int) $element['rows'])));
            }
        }

        return $copies;
    }

    /** @param  array<string, mixed>  $element */
    private function draw(JapanesePdf $pdf, array $element, RecordData $data, int $copy): void
    {
        $style = [
            'font' => $element['font'], 'size' => (float) $element['size'], 'bold' => (bool) $element['bold'],
            'color' => $element['color'], 'border' => (bool) $element['border'],
        ];

        match ($element['type']) {
            'text' => $pdf->textBox((float) $element['x'], (float) $element['y'], (float) $element['w'], (float) $element['h'], (string) $element['text'], [
                ...$style, 'align' => $element['align'], 'valign' => $element['valign'], 'overflow' => $element['overflow'],
            ]),
            'field' => $pdf->textBox((float) $element['x'], (float) $element['y'], (float) $element['w'], (float) $element['h'], $this->fieldText($element, $data), [
                ...$style, 'align' => $element['align'], 'valign' => $element['valign'], 'overflow' => $element['overflow'],
            ]),
            'table' => $this->drawTable($pdf, $element, $data, $copy, $style),
            default => null,
        };
    }

    /** @param  array<string, mixed>  $element */
    private function fieldText(array $element, RecordData $data): string
    {
        $text = (ValueFormat::tryFrom((string) $element['format']) ?? ValueFormat::Display)->apply($data->value((string) $element['field']));

        return $text === '' ? '' : $element['prefix'].$text.$element['suffix'];
    }

    /**
     * The rows of this copy, one line each: the columns side by side from the table's left edge.
     *
     * @param  array<string, mixed>  $element
     * @param  array<string, mixed>  $style
     */
    private function drawTable(JapanesePdf $pdf, array $element, RecordData $data, int $copy, array $style): void
    {
        $lines = max(1, (int) $element['rows']);
        $height = (float) $element['rowHeight'];
        $rows = array_slice($data->rows((string) $element['table']), $copy * $lines, $lines);

        for ($line = 0; $line < $lines; $line++) {
            $row = $rows[$line] ?? null;
            if ($row === null && ! $style['border']) {
                break;
            }

            $x = (float) $element['x'];
            $y = (float) $element['y'] + $line * $height;
            foreach ($element['columns'] as $column) {
                $value = $row[$column['field']] ?? DocumentValue::empty();
                $text = $row === null ? '' : (ValueFormat::tryFrom((string) $column['format']) ?? ValueFormat::Display)->apply($value);
                $pdf->textBox($x, $y, (float) $column['width'], $height, $text, [
                    ...$style, 'align' => $column['align'], 'valign' => 'middle', 'overflow' => $element['overflow'],
                ]);
                $x += (float) $column['width'];
            }
        }
    }
}
