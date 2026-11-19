<?php

declare(strict_types=1);

namespace Modules\Core\Application\Export;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 「ファイルに書き出す」 of every app list (legacy ファイル書き出し; docs/legacy-customizations.md item 8): the records of the
 * list as filtered, searched and sorted, as CSV (UTF-8 with BOM, opens in Excel) — the list's database columns the user
 * may see (AppListRecords decides them), headed by their labels. Values are stored values (ids for references, enum
 * values, dates as Y-m-d H:i). Gated by the app's ファイル書き出し permission (AppAbility::Export). The company's real backup
 * is the database's own (docs/deploy-aws.md), not this file.
 */
final class ListCsvExport
{
    /**
     * @param  Builder<Model>  $query
     * @param  array<string, string>  $columns  attribute => header
     */
    public function download(Builder $query, array $columns, string $name): StreamedResponse
    {
        $fileName = $name.'_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query, $columns): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            fwrite($out, "\u{FEFF}");
            fputcsv($out, array_values($columns), escape: '');
            $query->chunk(500, function ($records) use ($out, $columns): void {
                foreach ($records as $record) {
                    fputcsv($out, $this->row($record, array_keys($columns)), escape: '');
                }
            });

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    public function row(Model $record, array $columns): array
    {
        return array_map(fn (string $column): string => self::scalar($record->getAttribute($column)), $columns);
    }

    private static function scalar(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i'),
            is_bool($value) => $value ? '1' : '0',
            is_array($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
            is_scalar($value) => (string) $value,
            default => '',
        };
    }
}
