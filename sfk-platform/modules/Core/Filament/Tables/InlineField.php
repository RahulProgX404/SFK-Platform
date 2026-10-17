<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Tables;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;
use Modules\Core\Domain\People\EmploymentStatus;

/**
 * A field of an app list that インライン編集 (legacy inline edit: pencil on a row → inputs in its cells → 保存 /
 * キャンセル) can change, with its input and its rules (keep them like the app's form). Returned by
 * AppListRecords::inlineFields() and drawn by AppTextColumn in the row being edited.
 */
final class InlineField
{
    private const TEXT = 'text';

    private const TEXTAREA = 'textarea';

    private const NUMBER = 'number';

    private const SELECT = 'select';

    private const CHECKBOXES = 'checkboxes';

    private const CHECKBOX = 'checkbox';

    private const USERS = 'users';

    private bool $required = false;

    private ?int $maxLength = null;

    private ?int $minValue = null;

    private ?int $maxValue = null;

    private int $decimals = 0;

    private ?string $regex = null;

    /** @var array{0: string, 1: string}|null table, column */
    private ?array $unique = null;

    /** @var list<string> options kept on records but not offered (legacy disabled options) */
    private array $retired = [];

    /** @param  array<int|string, string>  $options  value => label */
    private function __construct(
        public readonly string $name,
        private readonly string $type,
        private readonly array $options = [],
        private readonly ?string $checkboxLabel = null,
    ) {}

    public static function text(string $name): self
    {
        return new self($name, self::TEXT);
    }

    public static function textarea(string $name): self
    {
        return new self($name, self::TEXTAREA);
    }

    /** @param  int  $decimals  decimal places allowed (0 = whole numbers), e.g. 4 for unit prices */
    public static function number(string $name, int $decimals = 0): self
    {
        $field = new self($name, self::NUMBER);
        $field->decimals = $decimals;

        return $field;
    }

    /** @param  array<int|string, string>  $options  drop-down (with 「-----」 for blank unless required) or radio buttons */
    public static function select(string $name, array $options): self
    {
        return new self($name, self::SELECT, $options);
    }

    /** @param  array<int|string, string>  $options  check boxes, several allowed */
    public static function checkboxes(string $name, array $options): self
    {
        return new self($name, self::CHECKBOXES, $options);
    }

    /** One check box (e.g. 「✓」 / 「手形」), stored as a boolean. */
    public static function checkbox(string $name, string $label = '✓'): self
    {
        return new self($name, self::CHECKBOX, checkboxLabel: $label);
    }

    /** ユーザー選択 (several users), stored by the page (e.g. a pivot table). */
    public static function users(string $name): self
    {
        return new self($name, self::USERS);
    }

    public function required(bool $required = true): self
    {
        $this->required = $required;

        return $this;
    }

    public function maxLength(int $length): self
    {
        $this->maxLength = $length;

        return $this;
    }

    public function minValue(int $value): self
    {
        $this->minValue = $value;

        return $this;
    }

    public function maxValue(int $value): self
    {
        $this->maxValue = $value;

        return $this;
    }

    public function regex(string $pattern): self
    {
        $this->regex = $pattern;

        return $this;
    }

    /**
     * Options of select() a record may keep but nobody may choose anew (legacy disabled options): offered only on the
     * record that has one.
     *
     * @param  list<string>  $values
     */
    public function retiredOptions(array $values): self
    {
        $this->retired = $values;

        return $this;
    }

    /** Unique in the table (the edited record excluded). */
    public function unique(string $table, ?string $column = null): self
    {
        $this->unique = [$table, $column ?? $this->name];

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->type === self::CHECKBOXES || $this->type === self::USERS;
    }

    public function isBoolean(): bool
    {
        return $this->type === self::CHECKBOX;
    }

    /**
     * Validation rules by path below the edited data (the field, and `<field>.*` for several values).
     *
     * @return array<string, list<mixed>>
     */
    public function rules(Model $record): array
    {
        $presence = $this->required ? 'required' : 'nullable';

        return match ($this->type) {
            self::CHECKBOX => [$this->name => ['boolean']],
            self::USERS => [
                $this->name => [$presence, 'array'],
                $this->name.'.*' => ['integer', Rule::exists('users', 'id')],
            ],
            self::CHECKBOXES => [
                $this->name => [$presence, 'array'],
                $this->name.'.*' => [Rule::in(array_map('strval', array_keys($this->options)))],
            ],
            self::SELECT => [$this->name => [$presence, Rule::in(array_map('strval', array_keys($this->options)))]],
            self::NUMBER => [$this->name => array_values(array_filter([
                $presence,
                ...($this->decimals > 0 ? ['numeric', 'decimal:0,'.$this->decimals] : ['integer']),
                $this->minValue !== null ? 'min:'.$this->minValue : null,
                $this->maxValue !== null ? 'max:'.$this->maxValue : null,
            ]))],
            default => [$this->name => array_values(array_filter([
                $presence,
                'string',
                'max:'.($this->maxLength ?? ($this->type === self::TEXTAREA ? 4000 : 255)),
                $this->regex !== null ? 'regex:'.$this->regex : null,
                $this->unique !== null ? Rule::unique($this->unique[0], $this->unique[1])->ignore($record->getKey(), $record->getKeyName()) : null,
            ]))],
        };
    }

    /**
     * The input bound to `<statePath>.<name>`, with the field's error below it.
     *
     * @param  mixed  $state  the field's current value (users already chosen stay choosable after they left)
     */
    public function toHtml(string $statePath, string $label, MessageBag $errors, mixed $state = null): string
    {
        $model = e($statePath.'.'.$this->name);
        $aria = 'aria-label="'.e($label).'"';
        $keys = 'wire:keydown.enter.prevent="saveInlineEdit" wire:keydown.escape="cancelInlineEdit"';

        $input = match ($this->type) {
            self::TEXTAREA => '<textarea class="sfk-inline-input" rows="2" wire:model="'.$model.'" wire:keydown.escape="cancelInlineEdit" '.$aria.'></textarea>',
            self::NUMBER => '<input type="number" class="sfk-inline-input sfk-inline-number"'.($this->decimals > 0 ? ' step="any"' : '')
                .' wire:model="'.$model.'" '.$keys.' '.$aria.'>',
            self::SELECT => '<select class="sfk-inline-input" wire:model="'.$model.'" '.$aria.'>'
                .($this->required ? '' : '<option value="">-----</option>')
                .self::optionsHtml(array_filter(
                    $this->options,
                    fn (int|string $value): bool => ! in_array((string) $value, $this->retired, true) || (string) $value === (string) $state,
                    ARRAY_FILTER_USE_KEY,
                )).'</select>',
            self::USERS => '<select class="sfk-inline-input sfk-inline-users" multiple size="4" wire:model="'.$model.'" '.$aria.'>'
                .self::optionsHtml(self::userOptions(array_map('intval', (array) $state))).'</select>',
            self::CHECKBOXES => '<div class="sfk-inline-checks" role="group" '.$aria.'>'.implode('', array_map(
                fn (int|string $value, string $option): string => '<label class="sfk-inline-check"><input type="checkbox" value="'.e((string) $value).'" wire:model="'.$model.'"> '.e($option).'</label>',
                array_keys($this->options),
                $this->options,
            )).'</div>',
            self::CHECKBOX => '<label class="sfk-inline-check"><input type="checkbox" wire:model="'.$model.'" '.$aria.'> '.e((string) $this->checkboxLabel).'</label>',
            default => '<input type="text" class="sfk-inline-input" wire:model="'.$model.'" '.$keys.' '.$aria.'>',
        };

        $error = $errors->first($statePath.'.'.$this->name) ?: $errors->first($statePath.'.'.$this->name.'.*');

        return '<div class="sfk-inline-field" x-on:click.stop>'.$input
            .($error !== '' ? '<p class="sfk-inline-error" role="alert">'.e($error).'</p>' : '')
            .'</div>';
    }

    /**
     * Users who can sign in, and the given ones.
     *
     * @param  list<int>  $chosen
     * @return array<int, string>
     */
    private static function userOptions(array $chosen): array
    {
        /** @var array<int, string> $users */
        $users = User::query()
            ->where(fn ($query) => $query
                ->whereIn('employment_status', array_map(
                    fn (EmploymentStatus $status): string => $status->value,
                    array_filter(EmploymentStatus::cases(), fn (EmploymentStatus $status): bool => $status->canSignIn()),
                ))
                ->orWhereIn('id', $chosen))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return $users;
    }

    /** @param  array<int|string, string>  $options */
    private static function optionsHtml(array $options): string
    {
        $html = '';
        foreach ($options as $value => $label) {
            $html .= '<option value="'.e((string) $value).'">'.e($label).'</option>';
        }

        return $html;
    }
}
