<?php

declare(strict_types=1);

namespace Modules\Core\Application\Legacy;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * One record of a legacy export (or one row of its table / サブテーブル): field code => {type, value}. The values as
 * the new system keeps them: texts trimmed (incl. the full-width space; blank = null), numbers, dates, times in the
 * app's time zone, users as their legacy login {code, name}, table rows as records.
 */
final readonly class LegacyRecord
{
    /** @param  array<string, mixed>  $fields */
    public function __construct(private array $fields, private ?string $rowId = null) {}

    /** The record number ($id; the record's number field holds the same). */
    public function id(): int
    {
        return (int) $this->raw('$id');
    }

    /** The id of a table row (row records only). */
    public function rowId(): ?string
    {
        return $this->rowId;
    }

    /** @return array<string, mixed> the record as exported: field code => {type, value} */
    public function toArray(): array
    {
        return $this->fields;
    }

    public function has(string $code): bool
    {
        return array_key_exists($code, $this->fields);
    }

    public function raw(string $code): mixed
    {
        $field = $this->fields[$code] ?? null;

        return is_array($field) ? ($field['value'] ?? null) : null;
    }

    public function type(string $code): ?string
    {
        $field = $this->fields[$code] ?? null;

        return is_array($field) && is_string($field['type'] ?? null) ? $field['type'] : null;
    }

    /** The text, trimmed (blank = null). */
    public function text(string $code): ?string
    {
        return self::trim($this->raw($code));
    }

    /** The text exactly as stored ('' for none): e.g. names other records refer to. */
    public function exactText(string $code): string
    {
        $value = $this->raw($code);

        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }

    /** A whole number (rounded; blank or not a number = null). */
    public function int(string $code): ?int
    {
        $value = self::number($this->raw($code));

        return $value !== null ? (int) round((float) $value) : null;
    }

    /** A number with decimals as a string ('12.5'; blank or not a number = null). */
    public function decimal(string $code): ?string
    {
        return self::number($this->raw($code));
    }

    /** A date as Y-m-d (blank or invalid = null). */
    public function date(string $code): ?string
    {
        $value = $this->text($code);
        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    /** A time of day as H:i (blank = null). */
    public function time(string $code): ?string
    {
        $value = $this->text($code);

        return $value !== null && preg_match('/^(\d{1,2}):(\d{2})/', $value, $m) === 1 ? sprintf('%02d:%s', (int) $m[1], $m[2]) : null;
    }

    /** A date and time (stored in UTC) in the app's time zone, as Y-m-d H:i:s. */
    public function dateTime(string $code): ?string
    {
        $value = $this->text($code);
        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->setTimezone((string) config('app.timezone'))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    /** @return list<string> the chosen options of a check box / multi-choice field */
    public function choices(string $code): array
    {
        return array_values(array_filter(array_map(fn (mixed $value): ?string => self::trim($value), (array) $this->raw($code))));
    }

    /** A check box with any option checked. */
    public function checked(string $code): bool
    {
        return $this->choices($code) !== [];
    }

    /** @return array{code: string, name: string}|null the (first) user of a user field / 作成者 / 更新者 */
    public function user(string $code): ?array
    {
        return $this->users($code)[0] ?? null;
    }

    /** @return list<array{code: string, name: string}> */
    public function users(string $code): array
    {
        $value = $this->raw($code);
        $list = is_array($value) && array_key_exists('code', $value) ? [$value] : (array) $value;

        $users = [];
        foreach ($list as $user) {
            if (is_array($user) && is_string($user['code'] ?? null) && $user['code'] !== '') {
                $users[] = ['code' => $user['code'], 'name' => (string) ($user['name'] ?? $user['code'])];
            }
        }

        return $users;
    }

    /** @return list<string> the names of the attached files (the export holds no file contents) */
    public function files(string $code): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $file): string => is_array($file) ? (string) ($file['name'] ?? '') : '',
            (array) $this->raw($code),
        )));
    }

    /** @return list<self> the rows of a table (サブテーブル) */
    public function table(string $code): array
    {
        $rows = [];
        foreach ((array) $this->raw($code) as $row) {
            if (is_array($row) && is_array($row['value'] ?? null)) {
                $rows[] = new self($row['value'], isset($row['id']) ? (string) $row['id'] : null);
            }
        }

        return $rows;
    }

    /** Whether nothing was entered in the given fields (e.g. an empty table row). */
    public function isBlank(string ...$codes): bool
    {
        foreach ($codes as $code) {
            $value = $this->raw($code);
            if (is_array($value) ? $value !== [] : self::trim($value) !== null) {
                return false;
            }
        }

        return true;
    }

    public function createdAt(): ?string
    {
        return $this->dateTime('作成日時') ?? $this->dateTimeOfType('CREATED_TIME');
    }

    public function updatedAt(): ?string
    {
        return $this->dateTime('更新日時') ?? $this->dateTimeOfType('UPDATED_TIME');
    }

    /** @return array{code: string, name: string}|null */
    public function creator(): ?array
    {
        return $this->user('作成者') ?? $this->userOfType('CREATOR');
    }

    /** @return array{code: string, name: string}|null */
    public function modifier(): ?array
    {
        return $this->user('更新者') ?? $this->userOfType('MODIFIER');
    }

    /** A text with white space (incl. the full-width space) trimmed, blank = null. */
    public static function trim(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        // trim() works on bytes and would cut Japanese characters
        $value = (string) preg_replace('/^[\s\x{3000}]+|[\s\x{3000}]+$/u', '', (string) $value);

        return $value === '' ? null : $value;
    }

    /** A number as a plain numeric string (full-width digits and commas allowed; null when it is none). */
    private static function number(mixed $value): ?string
    {
        $text = self::trim($value);
        if ($text === null) {
            return null;
        }

        $text = str_replace([',', '，'], '', mb_convert_kana($text, 'n'));

        return is_numeric($text) ? $text : null;
    }

    private function dateTimeOfType(string $type): ?string
    {
        foreach ($this->fields as $code => $field) {
            if (is_array($field) && ($field['type'] ?? null) === $type) {
                return $this->dateTime((string) $code);
            }
        }

        return null;
    }

    /** @return array{code: string, name: string}|null */
    private function userOfType(string $type): ?array
    {
        foreach ($this->fields as $code => $field) {
            if (is_array($field) && ($field['type'] ?? null) === $type) {
                return $this->user((string) $code);
            }
        }

        return null;
    }
}
