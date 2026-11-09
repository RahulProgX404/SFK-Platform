<?php

declare(strict_types=1);

namespace Modules\Core\Filament\QuickEntry;

use Closure;

/**
 * One screen of a quick entry (QuickEntry): its title, the section it belongs to (e.g. 運転前 / 運転後, shown above the
 * title), its fields, when it is asked (`when`, from the entered values) and an optional way past the rest of the steps
 * (`skip`: a button that empties the given fields and goes to the check, e.g. 「運転後はあとで入力」).
 */
final class QuickStep
{
    /**
     * @param  list<QuickField>  $fields
     * @param  (Closure(array<string, mixed>): bool)|null  $when
     * @param  array{label: string, fields: list<string>}|null  $skip
     */
    private function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly array $fields,
        public readonly string $section = '',
        public readonly ?Closure $when = null,
        public readonly ?array $skip = null,
    ) {}

    /** @param  list<QuickField>  $fields */
    public static function make(string $key, string $title, array $fields): self
    {
        return new self($key, $title, $fields);
    }

    public function section(string $section): self
    {
        return new self($this->key, $this->title, $this->fields, $section, $this->when, $this->skip);
    }

    /** @param  Closure(array<string, mixed>): bool  $when */
    public function when(Closure $when): self
    {
        return new self($this->key, $this->title, $this->fields, $this->section, $when, $this->skip);
    }

    /** @param  list<string>  $fields  emptied by the button */
    public function skip(string $label, array $fields): self
    {
        return new self($this->key, $this->title, $this->fields, $this->section, $this->when, ['label' => $label, 'fields' => $fields]);
    }

    /** @param  array<string, mixed>  $data */
    public function isAsked(array $data): bool
    {
        return $this->when === null || ($this->when)($data);
    }

    /** @return list<string> */
    public function fieldNames(): array
    {
        return array_map(fn (QuickField $field): string => $field->name, $this->fields);
    }

    /** A tap on a choice moves on: the step has only that field. */
    public function advancesOnTap(): bool
    {
        return count($this->fields) === 1 && $this->fields[0]->advancesOnTap();
    }
}
