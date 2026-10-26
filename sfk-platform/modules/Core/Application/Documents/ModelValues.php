<?php

declare(strict_types=1);

namespace Modules\Core\Application\Documents;

use BackedEnum;
use DateTimeInterface;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Core\Domain\Documents\DocumentValue;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Values read from a model itself, for fields the record page draws as HTML (e.g. the ユーザー情報 popup of 作成者):
 * a relation gives the name of the related record(s) (「creator」 → the user, 「customer_id」 → the partner), an
 * attribute its value. Also turns any value into the plain text a 帳票 prints.
 */
final class ModelValues
{
    /** Attributes naming a related record, in order of preference. */
    private const NAME_ATTRIBUTES = ['name', 'title', 'label', 'number', 'code'];

    public static function of(Model $record, string $field): DocumentValue
    {
        $relation = self::relation($record, $field)
            ?? (str_ends_with($field, '_id') ? self::relation($record, Str::camel(substr($field, 0, -3))) : null);

        if ($relation !== null) {
            try {
                return self::value($record->getRelationValue($relation));
            } catch (Throwable) {
                return DocumentValue::empty();
            }
        }

        try {
            return self::value($record->getAttribute($field));
        } catch (Throwable) {
            return DocumentValue::empty();
        }
    }

    public static function value(mixed $value): DocumentValue
    {
        if ($value instanceof Collection || is_array($value)) {
            $texts = array_values(array_filter(array_map(self::text(...), $value instanceof Collection ? $value->all() : $value), fn (string $text): bool => $text !== ''));

            return DocumentValue::text(implode('、', $texts));
        }

        return new DocumentValue(self::text($value), self::raw($value));
    }

    /** The printed text of a value. */
    public static function text(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            $value instanceof Htmlable => self::plain($value->toHtml()),
            $value instanceof HasLabel => self::plain((string) $value->getLabel()),
            $value instanceof BackedEnum => method_exists($value, 'label') ? (string) $value->label() : (string) $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            $value instanceof Model => self::name($value),
            $value instanceof EloquentCollection, $value instanceof Collection => implode('、', array_filter(array_map(self::text(...), $value->all()))),
            is_bool($value) => $value ? '✓' : '',
            is_scalar($value) => (string) $value,
            is_array($value) => implode('、', array_filter(array_map(self::text(...), $value))),
            default => '',
        };
    }

    /** The stored value behind a text (for 書式): numbers, strings and dates. */
    public static function raw(mixed $value): int|float|string|bool|DateTimeInterface|null
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface, is_scalar($value) => $value,
            default => null,
        };
    }

    /** Text of HTML: tags removed, entities decoded, white space collapsed. */
    public static function plain(string $html): string
    {
        $text = html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/[ \t]*\n[ \t\n]*/u', "\n", (string) preg_replace('/[ \t]+/u', ' ', $text)));
    }

    private static function name(Model $model): string
    {
        foreach (self::NAME_ATTRIBUTES as $attribute) {
            $value = $model->getAttribute($attribute);
            if (filled($value) && is_scalar($value)) {
                return (string) $value;
            }
        }

        return (string) $model->getKey();
    }

    /** The relation method of that name, when the model declares one (its return type is a relation). */
    private static function relation(Model $record, string $name): ?string
    {
        if ($name === '' || ! method_exists($record, $name)) {
            return null;
        }

        $type = (new ReflectionMethod($record, $name))->getReturnType();

        return $type instanceof ReflectionNamedType && is_a($type->getName(), Relation::class, true) ? $name : null;
    }
}
