<?php

/*
 * 絞り込む of the app lists: the legacy comparisons per field type, the date periods behind TODAY(), THIS_MONTH(),
 * LAST_MONTH() … (filters in SYSTEM_BRAIN 03_APPS/<app>/views.md) and the cleaning of dialog input.
 */

use Carbon\CarbonImmutable;
use Modules\Core\Domain\Filters\DateMode;
use Modules\Core\Domain\Filters\FilterFieldType;
use Modules\Core\Domain\Filters\FilterOperator;
use Modules\Core\Domain\Filters\RecordFilter;

/** @param  array{0: CarbonImmutable, 1: CarbonImmutable}|null  $period */
function periodText(?array $period): ?string
{
    return $period === null ? null : $period[0]->format('Y-m-d H:i').' – '.$period[1]->format('Y-m-d H:i');
}

it('offers the legacy comparisons for each field type, the first one being the default', function () {
    expect(FilterFieldType::Text->operators()[0])->toBe(FilterOperator::Contains)
        ->and(FilterFieldType::Text->operators())->toContain(FilterOperator::OneOf)
        ->and(FilterFieldType::Textarea->operators())->toContain(FilterOperator::Blank, FilterOperator::NotBlank)
        ->and(FilterFieldType::Choice->operators())->toBe([FilterOperator::In, FilterOperator::NotIn])
        ->and(FilterFieldType::DateTime->operators()[0])->toBe(FilterOperator::Equals)
        ->and(FilterOperator::Equals->label(FilterFieldType::DateTime))->toBe('＝（等しい）')
        ->and(FilterOperator::In->label(FilterFieldType::Choice))->toBe('次のいずれかを含む')
        ->and(FilterOperator::LessOrEqual->label(FilterFieldType::Date))->toBe('≦（以前）')
        ->and(FilterOperator::LessOrEqual->label(FilterFieldType::Number))->toBe('≦（以下）')
        ->and(DateMode::Specified->label(FilterFieldType::DateTime))->toBe('日時を指定');
});

it('turns relative dates into periods, weeks running Sunday to Saturday', function () {
    $now = CarbonImmutable::parse('2026-10-16 14:30', 'Asia/Tokyo'); // a Friday

    expect(periodText(DateMode::Today->period($now)))->toBe('2026-10-16 00:00 – 2026-10-17 00:00')
        ->and(periodText(DateMode::Yesterday->period($now)))->toBe('2026-10-15 00:00 – 2026-10-16 00:00')
        ->and(periodText(DateMode::ThisWeek->period($now, sub: 'all')))->toBe('2026-10-11 00:00 – 2026-10-18 00:00')
        ->and(periodText(DateMode::LastWeek->period($now, sub: '1')))->toBe('2026-10-05 00:00 – 2026-10-06 00:00')
        ->and(periodText(DateMode::ThisMonth->period($now, sub: 'all')))->toBe('2026-10-01 00:00 – 2026-11-01 00:00')
        ->and(periodText(DateMode::LastMonth->period($now, sub: 'last')))->toBe('2026-09-30 00:00 – 2026-10-01 00:00')
        ->and(periodText(DateMode::NextMonth->period($now, sub: '15')))->toBe('2026-11-15 00:00 – 2026-11-16 00:00')
        ->and(DateMode::LastMonth->period($now, sub: '31'))->toBeNull()
        ->and(periodText(DateMode::ThisYear->period($now)))->toBe('2026-01-01 00:00 – 2027-01-01 00:00');
});

it('reads a given day, with or without a time', function () {
    $now = CarbonImmutable::parse('2026-10-16 14:30', 'Asia/Tokyo');

    expect(periodText(DateMode::Specified->period($now, '2026-10-01')))->toBe('2026-10-01 00:00 – 2026-10-02 00:00')
        ->and(periodText(DateMode::Specified->period($now, '2026-10-01', '09:15')))->toBe('2026-10-01 09:15 – 2026-10-01 09:16')
        ->and(DateMode::Specified->period($now, '2026-02-30'))->toBeNull()
        ->and(DateMode::Specified->period($now, 'yesterday'))->toBeNull();
});

it('keeps only valid rows of the dialog input', function () {
    $types = ['name' => FilterFieldType::Text, 'closing_day' => FilterFieldType::Choice, 'amount' => FilterFieldType::Number, 'created_at' => FilterFieldType::DateTime];

    $filter = RecordFilter::fromArray([
        'match' => 'or',
        'conditions' => [
            ['field' => 'name', 'operator' => 'like', 'values' => ['印刷']],
            ['field' => 'name', 'operator' => 'in', 'values' => ['x']],          // not a text comparison
            ['field' => 'secret', 'operator' => 'like', 'values' => ['x']],      // unknown field
            ['field' => 'closing_day', 'operator' => 'in', 'values' => []],      // nothing chosen
            ['field' => 'closing_day', 'operator' => 'not_in', 'values' => ['', '5日', '5日']],
            ['field' => 'amount', 'operator' => 'gte', 'values' => ['abc']],     // not a number
            ['field' => 'created_at', 'operator' => 'eq', 'values' => [''], 'mode' => 'specified'],
            ['field' => 'created_at', 'operator' => 'lt', 'values' => [''], 'mode' => 'last_month', 'sub' => 'all'],
        ],
        'sorts' => [['field' => 'name', 'direction' => 'desc'], ['field' => 'name', 'direction' => 'asc'], ['field' => 'secret']],
    ], $types, ['name']);

    expect($filter->match)->toBe(RecordFilter::MATCH_ANY)
        ->and(array_map(fn ($condition) => $condition->toArray(), $filter->conditions))->toBe([
            ['field' => 'name', 'operator' => 'like', 'values' => ['印刷'], 'mode' => null, 'time' => null, 'sub' => null],
            ['field' => 'closing_day', 'operator' => 'not_in', 'values' => ['', '5日'], 'mode' => null, 'time' => null, 'sub' => null],
            ['field' => 'created_at', 'operator' => 'lt', 'values' => [''], 'mode' => 'last_month', 'time' => null, 'sub' => 'all'],
        ])
        ->and($filter->sorts)->toBe([['field' => 'name', 'direction' => 'desc']])
        ->and(RecordFilter::fromArray('nonsense', $types, [])->isEmpty())->toBeTrue();
});
