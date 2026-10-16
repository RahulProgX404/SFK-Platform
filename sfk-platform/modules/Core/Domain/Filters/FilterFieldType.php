<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Filters;

/** Kind of a field in the 絞り込む dialog; decides its comparisons and value input (legacy field types). */
enum FilterFieldType: string
{
    /** 文字列（1行）, リンク */
    case Text = 'text';
    /** 文字列（複数行） */
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case DateTime = 'datetime';
    /** ドロップダウン, ラジオボタン, チェックボックス, 複数選択: chosen from a list box ("--" = blank) */
    case Choice = 'choice';
    /** ユーザー選択, 作成者, 更新者: chosen from a list box incl. ログインユーザー */
    case User = 'user';

    /** @return list<FilterOperator> in the legacy order; the first one is the default. */
    public function operators(): array
    {
        return match ($this) {
            self::Text => [FilterOperator::Contains, FilterOperator::NotContains, FilterOperator::Equals, FilterOperator::NotEquals, FilterOperator::OneOf],
            self::Textarea => [FilterOperator::Contains, FilterOperator::NotContains, FilterOperator::Blank, FilterOperator::NotBlank],
            self::Number => [FilterOperator::Equals, FilterOperator::NotEquals, FilterOperator::LessOrEqual, FilterOperator::GreaterOrEqual],
            self::Date, self::DateTime => [
                FilterOperator::Equals, FilterOperator::NotEquals, FilterOperator::LessOrEqual, FilterOperator::Less,
                FilterOperator::GreaterOrEqual, FilterOperator::Greater,
            ],
            self::Choice, self::User => [FilterOperator::In, FilterOperator::NotIn],
        };
    }

    public function isDate(): bool
    {
        return $this === self::Date || $this === self::DateTime;
    }
}
