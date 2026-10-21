<?php

declare(strict_types=1);

namespace Modules\Purchasing\Domain\Imports;

use DateTimeImmutable;

/**
 * A CSV file as read by ファイルから読み込む (legacy CSV import of 05 / 15): the first line names the columns by their
 * field labels, every further non-empty line is a record. UTF-8 (with or without BOM) and Shift_JIS files are read.
 */
final class CsvSheet
{
    /**
     * @param  list<string>  $headers
     * @param  array<int, array<string, string>>  $rows  line number => label => value (trimmed)
     */
    private function __construct(public readonly array $headers, public readonly array $rows) {}

    public static function parse(string $contents): self
    {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        } elseif (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = (string) mb_convert_encoding($contents, 'UTF-8', 'SJIS-win');
        }

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return new self([], []);
        }
        fwrite($handle, $contents);
        rewind($handle);

        $headers = [];
        $rows = [];
        $line = 0;
        while (($cells = fgetcsv($handle, escape: '')) !== false) {
            $line++;
            $cells = array_map(fn (mixed $cell): string => trim((string) $cell), $cells);
            if ($headers === []) {
                $headers = $cells;

                continue;
            }
            if (implode('', $cells) === '') {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $row[$header] = $cells[$index] ?? '';
                }
            }
            $rows[$line] = $row;
        }
        fclose($handle);

        return new self($headers, $rows);
    }

    public function has(string $header): bool
    {
        return in_array($header, $this->headers, true);
    }

    /** A date written 2026-10-05, 2026/10/5 or 20261005 (null when it is not one). */
    public static function date(string $value): ?string
    {
        foreach (['!Y-n-j', '!Y/n/j', '!Ymd'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            // a day like 2026/02/30 parses with a warning: not a date
            if ($date !== false && ($errors === false || $errors['warning_count'] + $errors['error_count'] === 0)) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /** A number as written, without thousands separators and ¥ (null when it is not one). */
    public static function number(string $value): ?string
    {
        $value = str_replace([',', '¥', '\\', '￥'], '', $value);

        return is_numeric($value) ? $value : null;
    }
}
