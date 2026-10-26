<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Documents;

/**
 * What a 帳票テンプレート places on its pages, as saved by the designer — positions and sizes in mm from the top left
 * corner of the page, font sizes in pt:
 * - フィールド (field): the value of a field of the record, with 書式 and text before / after it;
 * - 固定テキスト (text): a fixed text;
 * - テーブル (table): the rows of a table field (サブテーブル), one line per row with its columns side by side; rows
 *   beyond a page continue on a copy of the template's pages.
 * Every element has its font (ゴシック / 明朝), size, 太字, colour, alignment, what happens to text longer than its box
 * (縮小 / 折り返し / 切り取り) and an optional frame (罫線). normalize() turns the designer's input into that shape,
 * clamping numbers and dropping what it cannot read; field names are kept as written so a field another
 * administrator cannot see is not lost (it prints empty when the record has no such field).
 */
final class TemplateLayout
{
    public const MAX_ELEMENTS = 500;

    public const MAX_TABLE_COLUMNS = 30;

    /** Values every 帳票 can show besides the record's fields. */
    public const SYSTEM_FIELDS = [
        '_today' => '出力日',
        '_user' => '出力者',
        '_page' => 'ページ番号',
        '_pages' => '総ページ数',
    ];

    public const FONTS = ['gothic' => 'ゴシック', 'mincho' => '明朝'];

    public const ALIGNS = ['left' => '左揃え', 'center' => '中央揃え', 'right' => '右揃え'];

    public const VERTICAL_ALIGNS = ['top' => '上揃え', 'middle' => '上下中央', 'bottom' => '下揃え'];

    public const OVERFLOWS = ['shrink' => '縮小して全体を表示', 'wrap' => '折り返して表示', 'clip' => 'はみ出す部分を表示しない'];

    /** Largest page side and position accepted, in mm (A0 is 1,189 mm). */
    private const MAX_MM = 1500.0;

    /**
     * @return list<array<string, mixed>> the elements, in drawing order
     */
    public static function normalize(mixed $elements, int $pageCount): array
    {
        if (! is_array($elements)) {
            return [];
        }

        $normalized = [];
        $ids = [];
        foreach (array_slice(array_values($elements), 0, self::MAX_ELEMENTS) as $index => $element) {
            if (! is_array($element)) {
                continue;
            }

            $item = self::element($element, max(1, $pageCount));
            if ($item === null) {
                continue;
            }

            // ids identify the elements in the designer: unique, generated when missing
            $id = $item['id'];
            if ($id === '' || isset($ids[$id])) {
                $id = 'e'.($index + 1).'-'.substr(md5(serialize($item).$index), 0, 6);
            }
            $ids[$id] = true;
            $item['id'] = $id;
            $normalized[] = $item;
        }

        return $normalized;
    }

    /** Name of a field or table column: letters, digits, _ and . (relation paths). */
    public static function isFieldName(mixed $name): bool
    {
        return is_string($name) && preg_match('/^[A-Za-z_][A-Za-z0-9_.]{0,99}$/', $name) === 1;
    }

    /**
     * @param  array<mixed>  $element
     * @return array<string, mixed>|null
     */
    private static function element(array $element, int $pageCount): ?array
    {
        $type = $element['type'] ?? null;
        if (! in_array($type, ['field', 'text', 'table'], true)) {
            return null;
        }

        $common = [
            'id' => is_string($element['id'] ?? null) ? substr(preg_replace('/[^A-Za-z0-9_-]/', '', $element['id']) ?? '', 0, 40) : '',
            'type' => $type,
            'page' => self::int($element['page'] ?? 0, 0, $pageCount - 1),
            'x' => self::mm($element['x'] ?? 0, 0),
            'y' => self::mm($element['y'] ?? 0, 0),
            'font' => self::choice($element['font'] ?? null, self::FONTS, 'gothic'),
            'size' => self::number($element['size'] ?? 10.5, 4, 72),
            'bold' => (bool) ($element['bold'] ?? false),
            'color' => is_string($element['color'] ?? null) && preg_match('/^#[0-9a-fA-F]{6}$/', $element['color']) === 1 ? strtolower($element['color']) : '#000000',
            'border' => (bool) ($element['border'] ?? false),
        ];

        if ($type === 'table') {
            return self::table($element, $common);
        }

        $item = [
            ...$common,
            'w' => self::mm($element['w'] ?? 40, 1),
            'h' => self::mm($element['h'] ?? 7, 1),
            'align' => self::choice($element['align'] ?? null, self::ALIGNS, 'left'),
            'valign' => self::choice($element['valign'] ?? null, self::VERTICAL_ALIGNS, 'middle'),
            'overflow' => self::choice($element['overflow'] ?? null, self::OVERFLOWS, 'shrink'),
        ];

        if ($type === 'text') {
            return [...$item, 'text' => self::string($element['text'] ?? '', 1000)];
        }

        if (! self::isFieldName($element['field'] ?? null)) {
            return null;
        }

        return [
            ...$item,
            'field' => $element['field'],
            'format' => (ValueFormat::tryFrom(is_string($element['format'] ?? null) ? $element['format'] : '') ?? ValueFormat::Display)->value,
            'prefix' => self::string($element['prefix'] ?? '', 40),
            'suffix' => self::string($element['suffix'] ?? '', 40),
        ];
    }

    /**
     * @param  array<mixed>  $element
     * @param  array<string, mixed>  $common
     * @return array<string, mixed>|null
     */
    private static function table(array $element, array $common): ?array
    {
        if (! self::isFieldName($element['table'] ?? null)) {
            return null;
        }

        $columns = [];
        foreach (array_slice(array_values(is_array($element['columns'] ?? null) ? $element['columns'] : []), 0, self::MAX_TABLE_COLUMNS) as $column) {
            if (! is_array($column) || ! self::isFieldName($column['field'] ?? null)) {
                continue;
            }
            $columns[] = [
                'field' => $column['field'],
                'width' => self::mm($column['width'] ?? 20, 1),
                'align' => self::choice($column['align'] ?? null, self::ALIGNS, 'left'),
                'format' => (ValueFormat::tryFrom(is_string($column['format'] ?? null) ? $column['format'] : '') ?? ValueFormat::Display)->value,
            ];
        }

        if ($columns === []) {
            return null;
        }

        return [
            ...$common,
            'table' => $element['table'],
            'rowHeight' => self::number($element['rowHeight'] ?? 7, 2, 100),
            'rows' => self::int($element['rows'] ?? 10, 1, 200),
            'columns' => $columns,
            'overflow' => self::choice($element['overflow'] ?? null, self::OVERFLOWS, 'shrink'),
        ];
    }

    private static function mm(mixed $value, float $min): float
    {
        return self::number($value, $min, self::MAX_MM);
    }

    private static function number(mixed $value, float $min, float $max): float
    {
        $number = is_numeric($value) ? (float) $value : $min;

        return round(max($min, min($max, $number)), 2);
    }

    private static function int(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, is_numeric($value) ? (int) $value : $min));
    }

    /** @param  array<string, string>  $choices */
    private static function choice(mixed $value, array $choices, string $default): string
    {
        return is_string($value) && array_key_exists($value, $choices) ? $value : $default;
    }

    private static function string(mixed $value, int $length): string
    {
        return is_scalar($value) ? mb_substr(str_replace("\r\n", "\n", (string) $value), 0, $length) : '';
    }
}
