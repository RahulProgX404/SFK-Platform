<?php

declare(strict_types=1);

namespace Modules\Core\Application\Records;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Checks values against an app's RecordRules and reports the errors on the screen's own paths (`data.<path>` of a form,
 * `inlineData.<path>` of インライン編集), only for the fields the screen lets the user change — an error elsewhere could not
 * be fixed there and is reported as one message instead (`$elsewhere`).
 */
final class RecordValidator
{
    /**
     * The errors of the values, by path.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, list<string>>
     */
    public static function errors(RecordRules $rules, array $data, ?Model $record): array
    {
        $validator = Validator::make($data, $rules->rules($data, $record), $rules->messages(), $rules->attributes());

        /** @var array<string, list<string>> $errors */
        $errors = $validator->fails() ? $validator->errors()->toArray() : [];

        return $errors;
    }

    /**
     * The errors of the fields the screen shows ($fields: top-level attributes) under `<prefix>.<path>`, and the messages
     * of the others (fields the screen does not offer, e.g. hidden by フィールドのアクセス権).
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $fields
     * @return array{0: array<string, list<string>>, 1: list<string>}
     */
    public static function split(RecordRules $rules, array $data, ?Model $record, string $prefix, array $fields): array
    {
        $shown = [];
        $other = [];
        foreach (self::errors($rules, $data, $record) as $path => $messages) {
            if (in_array(explode('.', $path)[0], $fields, true)) {
                $shown[$prefix.'.'.$path] = $messages;
            } else {
                $other = [...$other, ...$messages];
            }
        }

        return [$shown, array_values(array_unique($other))];
    }

    /**
     * @param  array<string, list<string>>  $errors
     *
     * @throws ValidationException
     */
    public static function throwIfAny(array $errors): void
    {
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** A value entered (blank text, null and an empty list are not). */
    public static function filled(mixed $value): bool
    {
        return match (true) {
            is_string($value) => trim($value) !== '',
            is_array($value) => $value !== [],
            default => $value !== null && $value !== false,
        };
    }

    /** Minutes of a time (H:i or H:i:s), null for none. */
    public static function minutes(mixed $value): ?int
    {
        return is_string($value) && preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($value), $match) === 1 ? (int) $match[1] * 60 + (int) $match[2] : null;
    }

    /** The same id (a user, a record) given as a number or as text. */
    public static function sameId(mixed $a, mixed $b): bool
    {
        return is_numeric($a) && is_numeric($b) && (int) $a === (int) $b;
    }
}
