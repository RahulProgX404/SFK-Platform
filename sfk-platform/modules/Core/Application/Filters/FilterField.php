<?php

declare(strict_types=1);

namespace Modules\Core\Application\Filters;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Domain\Filters\DateMode;
use Modules\Core\Domain\Filters\FilterCondition;
use Modules\Core\Domain\Filters\FilterFieldType;
use Modules\Core\Domain\Filters\FilterOperator;

/**
 * A field offered in the 絞り込む dialog of an app list (AppListRecords::filterFields()). By default it filters and
 * sorts on the column of the same name; relationship() filters a ユーザー選択 through a many-to-many relation and
 * query() takes over the filtering completely (e.g. a choice stored in several boolean columns). Fields filtered
 * that way are not offered under ソート.
 */
final class FilterField
{
    /** Value of ログインユーザー in a user condition (the legacy LOGINUSER()). */
    public const LOGIN_USER = 'login_user';

    private string $column;

    private ?string $relationship = null;

    private ?string $relatedColumn = null;

    /** @var (Closure(Builder<*>, FilterCondition): void)|null */
    private ?Closure $query = null;

    private bool $sortable = true;

    /** @param  array<string, string>|Closure(): array<string, string>  $options */
    private function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly FilterFieldType $type,
        private array|Closure $options = [],
    ) {
        $this->column = $name;
    }

    public static function text(string $name, string $label): self
    {
        return new self($name, $label, FilterFieldType::Text);
    }

    public static function textarea(string $name, string $label): self
    {
        return new self($name, $label, FilterFieldType::Textarea);
    }

    public static function number(string $name, string $label): self
    {
        return new self($name, $label, FilterFieldType::Number);
    }

    public static function date(string $name, string $label): self
    {
        return new self($name, $label, FilterFieldType::Date);
    }

    public static function dateTime(string $name, string $label): self
    {
        return new self($name, $label, FilterFieldType::DateTime);
    }

    /** @param  array<string, string>|Closure(): array<string, string>  $options  stored value => label */
    public static function choice(string $name, string $label, array|Closure $options): self
    {
        return new self($name, $label, FilterFieldType::Choice, $options);
    }

    /** A user column (作成者, 更新者 …) or, with relationship(), a ユーザー選択 field. */
    public static function user(string $name, string $label): self
    {
        return new self($name, $label, FilterFieldType::User, fn (): array => User::query()->orderBy('name')->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, int|string $id): array => [(string) $id => $name])
            ->all());
    }

    /** Database column, when it differs from the field name. */
    public function column(string $column): self
    {
        $this->column = $column;

        return $this;
    }

    /** Filters through a relation: records having (not having) a related row whose $column is one of the values. */
    public function relationship(string $relationship, string $column = 'id'): self
    {
        $this->relationship = $relationship;
        $this->relatedColumn = $column;
        $this->sortable = false;

        return $this;
    }

    /** @param  Closure(Builder<*>, FilterCondition): void  $query */
    public function query(Closure $query): self
    {
        $this->query = $query;
        $this->sortable = false;

        return $this;
    }

    public function sortable(bool $sortable = true): self
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getRelationship(): ?string
    {
        return $this->relationship;
    }

    public function getRelatedColumn(): ?string
    {
        return $this->relatedColumn;
    }

    /** @return (Closure(Builder<*>, FilterCondition): void)|null */
    public function getQuery(): ?Closure
    {
        return $this->query;
    }

    /** @return array<string, string> */
    public function getOptions(): array
    {
        return $this->options instanceof Closure ? ($this->options)() : $this->options;
    }

    /**
     * What the dialog needs: comparisons, list-box options ("--" for blank, ログインユーザー first for users) and the
     * date value modes.
     *
     * @return array{name: string, label: string, type: string, operators: list<array{value: string, label: string, needsValue: bool}>, options: list<array{value: string, label: string}>, modes: list<array{value: string, label: string, subs: list<array{value: string, label: string}>}>}
     */
    public function toDialog(): array
    {
        $options = [];
        if ($this->type === FilterFieldType::Choice || $this->type === FilterFieldType::User) {
            $options[] = ['value' => '', 'label' => '--'];
            if ($this->type === FilterFieldType::User) {
                $options[] = ['value' => self::LOGIN_USER, 'label' => 'ログインユーザー'];
            }
            foreach ($this->getOptions() as $value => $label) {
                $options[] = ['value' => (string) $value, 'label' => $label];
            }
        }

        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type->value,
            'operators' => array_map(fn (FilterOperator $operator): array => [
                'value' => $operator->value,
                'label' => $operator->label($this->type),
                'needsValue' => $operator->needsValue(),
            ], $this->type->operators()),
            'options' => $options,
            'modes' => $this->type->isDate() ? array_map(fn (DateMode $mode): array => [
                'value' => $mode->value,
                'label' => $mode->label($this->type),
                'subs' => array_map(
                    fn (string $value, string $label): array => ['value' => (string) $value, 'label' => $label],
                    array_keys($mode->subOptions()),
                    $mode->subOptions(),
                ),
            ], DateMode::cases()) : [],
        ];
    }
}
