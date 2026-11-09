<?php

declare(strict_types=1);

namespace Modules\Core\Filament\QuickEntry;

/**
 * One input of a quick-entry step (QuickEntry): how it is asked on a phone. The kinds:
 * - choice: big tap targets (one value; the step moves on when it is the step's only field), choices: toggles (several);
 * - person: the people as a list with a search box (one), people: the same with toggles (several);
 * - lookup: a search box over records (QuickEntry::lookupOptions()) with the chosen one shown as a card;
 * - select: a native drop-down (secondary fields), date / time: the native picker with one-tap chips,
 * - number: a numeric keyboard with its unit, text, textarea;
 * - custom: a partial view of the app (e.g. the 損害 of 14, the チェックリスト of 17), given `field`.
 */
final class QuickField
{
    /**
     * @param  array<int|string, string>  $options  value => label (choice, choices, person, people, select)
     * @param  array<string, string>  $chips  value => label: one tap fills the field (date, time, number)
     */
    private function __construct(
        public readonly string $name,
        public readonly string $kind,
        public readonly array $options = [],
        public readonly array $chips = [],
        public readonly string $unit = '',
        public readonly int $columns = 1,
        public readonly bool $required = false,
        public readonly ?string $view = null,
        public readonly string $inputMode = 'decimal',
        public readonly bool $inReview = false,
        public readonly string $placeholder = '',
    ) {}

    /** @param  array<int|string, string>  $options */
    public static function choice(string $name, array $options, int $columns = 1, bool $required = false): self
    {
        return new self($name, 'choice', options: $options, columns: $columns, required: $required);
    }

    /** @param  array<int|string, string>  $options */
    public static function choices(string $name, array $options, int $columns = 1, bool $required = false): self
    {
        return new self($name, 'choices', options: $options, columns: $columns, required: $required);
    }

    /** @param  array<int|string, string>  $options  user id => name */
    public static function person(string $name, array $options, bool $required = false): self
    {
        return new self($name, 'person', options: $options, required: $required);
    }

    /** @param  array<int|string, string>  $options  user id => name */
    public static function people(string $name, array $options): self
    {
        return new self($name, 'people', options: $options);
    }

    public static function lookup(string $name, bool $required = false, string $placeholder = ''): self
    {
        return new self($name, 'lookup', required: $required, placeholder: $placeholder);
    }

    /** @param  array<int|string, string>  $options */
    public static function select(string $name, array $options, bool $required = false): self
    {
        return new self($name, 'select', options: $options, required: $required);
    }

    /** @param  array<string, string>  $chips  Y-m-d => label */
    public static function date(string $name, array $chips = [], bool $required = false): self
    {
        return new self($name, 'date', chips: $chips, required: $required);
    }

    /** @param  array<string, string>  $chips  H:i => label */
    public static function time(string $name, array $chips = [], bool $required = false): self
    {
        return new self($name, 'time', chips: $chips, required: $required);
    }

    /** @param  array<string, string>  $chips */
    public static function number(string $name, string $unit = '', array $chips = [], bool $required = false, string $inputMode = 'decimal'): self
    {
        return new self($name, 'number', chips: $chips, unit: $unit, required: $required, inputMode: $inputMode);
    }

    public static function text(string $name, bool $required = false): self
    {
        return new self($name, 'text', required: $required);
    }

    public static function textarea(string $name, bool $required = false): self
    {
        return new self($name, 'textarea', required: $required);
    }

    public static function custom(string $name, string $view, bool $required = false): self
    {
        return new self($name, 'custom', required: $required, view: $view);
    }

    /** An optional field (追加情報) that is also listed on the check, e.g. 作業日 or 申請者. */
    public function inReview(): self
    {
        return new self($this->name, $this->kind, $this->options, $this->chips, $this->unit, $this->columns, $this->required, $this->view, $this->inputMode,
            true, $this->placeholder);
    }

    /** A tap moves on (a choice or a person that is the step's only field). */
    public function advancesOnTap(): bool
    {
        return in_array($this->kind, ['choice', 'person', 'lookup'], true);
    }
}
