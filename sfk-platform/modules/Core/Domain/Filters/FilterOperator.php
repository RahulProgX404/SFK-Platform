<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Filters;

/** Comparison of a 絞り込む condition, with the legacy labels. Which ones a field offers: FilterFieldType::operators(). */
enum FilterOperator: string
{
    case Contains = 'like';
    case NotContains = 'not_like';
    case Equals = 'eq';
    case NotEquals = 'ne';
    case OneOf = 'one_of';
    case LessOrEqual = 'lte';
    case Less = 'lt';
    case GreaterOrEqual = 'gte';
    case Greater = 'gt';
    case In = 'in';
    case NotIn = 'not_in';
    case Blank = 'blank';
    case NotBlank = 'not_blank';

    public function label(FilterFieldType $type): string
    {
        $isDate = $type === FilterFieldType::Date || $type === FilterFieldType::DateTime;

        return match ($this) {
            self::Contains => '次のキーワードを含む',
            self::NotContains => '次のキーワードを含まない',
            self::Equals => '＝（等しい）',
            self::NotEquals => '≠（等しくない）',
            self::OneOf => '次のいずれかと等しい',
            self::LessOrEqual => $isDate ? '≦（以前）' : '≦（以下）',
            self::Less => '＜（より前）',
            self::GreaterOrEqual => $isDate ? '≧（以降）' : '≧（以上）',
            self::Greater => '＞（より後）',
            self::In => '次のいずれかを含む',
            self::NotIn => '次のいずれも含まない',
            self::Blank => '空欄である',
            self::NotBlank => '空欄ではない',
        };
    }

    /** Operators that need no value (空欄である / 空欄ではない). */
    public function needsValue(): bool
    {
        return $this !== self::Blank && $this !== self::NotBlank;
    }
}
