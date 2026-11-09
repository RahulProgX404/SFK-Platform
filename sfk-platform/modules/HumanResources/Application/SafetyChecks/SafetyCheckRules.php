<?php

declare(strict_types=1);

namespace Modules\HumanResources\Application\SafetyChecks;

use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Modules\Core\Application\Records\RecordRules;
use Modules\Core\Application\Records\RecordValidator;
use Modules\HumanResources\Domain\HrChoices;
use Modules\HumanResources\Domain\HrFields;
use Modules\HumanResources\Models\SafetyCheck;
use Modules\HumanResources\Models\SafetyCheckItem;

/**
 * What a checklist of 17. 安全衛生チェック must be (user, 2026-11-09; SYSTEM_BRAIN APP_17 fields.md, BR-HR-05), on every
 * screen:
 * - required: 日付, 名前, 部署 (legacy) and at least one item;
 * - 日付 not in the future; one checklist per person and month (the monthly check — the person is 従業員名, or 名前 when
 *   the checklist is their own);
 * - every item filled in has its No (a whole number, each once in the checklist), 項目, 評価 and チェック (legacy: required
 *   on every row). An empty row is not saved and needs nothing.
 */
final class SafetyCheckRules implements RecordRules
{
    public function values(Model $record): array
    {
        if (! $record instanceof SafetyCheck) {
            return [];
        }

        return [
            ...$record->only(['user_id', 'employee_id', 'department', 'department_machine']),
            'checked_on' => $record->checked_on->toDateString(),
            'items' => $record->items->map(fn (SafetyCheckItem $row): array => [
                'number' => $row->number, 'item' => $row->item, 'answer' => $row->answer, 'checked' => $row->checked, 'notes' => $row->notes,
            ])->all(),
        ];
    }

    public function rules(array $data, ?Model $record): array
    {
        $users = Rule::exists((new User)->getTable(), 'id');
        $rows = array_filter((array) ($data['items'] ?? []), fn (mixed $row): bool => is_array($row) && ! self::isBlank($row));

        $rules = [
            'checked_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', $this->oncePerMonth($data, $record)],
            'user_id' => ['required', 'integer', $users],
            'employee_id' => ['nullable', 'integer', $users],
            'department' => ['required', Rule::in(HrChoices::DEPARTMENTS)],
            'department_machine' => ['nullable', 'string', 'max:255'],
            // no list fails `required`; a list of empty rows the closure
            'items' => ['required', 'array', 'max:60', function (string $attribute, mixed $value, Closure $fail) use ($rows): void {
                if ($rows === []) {
                    $fail('チェックリストに項目を1つ以上入力してください。');
                }
            }],
        ];

        $numbers = array_count_values(array_map(fn (array $row): string => (string) (is_numeric($row['number'] ?? null) ? (int) $row['number'] : ''), $rows));
        foreach ($rows as $key => $row) {
            // a whole number (the column keeps 2 decimals: 「1.00」 is 1)
            $rules["items.{$key}.number"] = ['required', 'numeric', 'between:1,999', function (string $attribute, mixed $value, Closure $fail) use ($numbers): void {
                if (is_numeric($value) && (float) $value !== floor((float) $value)) {
                    $fail('Noは整数で入力してください。');
                } elseif (is_numeric($value) && ($numbers[(string) (int) $value] ?? 0) > 1) {
                    $fail('同じNoの項目が2つあります。');
                }
            }];
            $rules["items.{$key}.item"] = ['required', 'string', 'max:4000'];
            $rules["items.{$key}.answer"] = ['required', Rule::in(HrChoices::ANSWERS)];
            // checked as true / 1 / ['✓'] (the check box list of the record form); `accepted` also runs on an empty value
            $rules["items.{$key}.checked"] = self::isChecked($row['checked'] ?? null) ? [] : ['accepted'];
            $rules["items.{$key}.notes"] = ['nullable', 'string', 'max:4000'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'checked_on.before_or_equal' => '日付に未来の日付は入力できません。',
            'items.required' => 'チェックリストに項目を1つ以上入力してください。',
            'items.*.checked.accepted' => 'チェックされていない項目があります。',
        ];
    }

    public function attributes(): array
    {
        $labels = [];
        foreach (['checked_on', 'user_id', 'employee_id', 'department', 'department_machine', 'items'] as $field) {
            $labels[$field] = HrFields::SAFETY[$field];
        }
        foreach (['number', 'item', 'answer', 'checked', 'notes'] as $column) {
            $labels['items.*.'.$column] = HrFields::SAFETY[$column];
        }

        return $labels;
    }

    /** A check given as true / 1 / '1' / ['✓'] (the check box list of the record form). */
    public static function isChecked(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || (is_array($value) && in_array(HrChoices::CHECK, $value, true));
    }

    /**
     * A row with nothing filled in (評価 keeps its default, a チェック alone does not count): not saved.
     *
     * @param  array<mixed>  $row
     */
    public static function isBlank(array $row): bool
    {
        return ! RecordValidator::filled($row['number'] ?? null) && ! RecordValidator::filled($row['item'] ?? null) && ! RecordValidator::filled($row['notes'] ?? null);
    }

    /** @param  array<string, mixed>  $data */
    private function oncePerMonth(array $data, ?Model $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($data, $record): void {
            $person = RecordValidator::filled($data['employee_id'] ?? null) ? $data['employee_id'] : ($data['user_id'] ?? null);
            if (! is_numeric($person) || ! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
                return;
            }

            $month = CarbonImmutable::parse($value);
            $other = SafetyCheck::query()
                ->whereBetween('checked_on', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
                ->where(fn (Builder $query): Builder => $query->where('employee_id', (int) $person)
                    ->orWhere(fn (Builder $own): Builder => $own->whereNull('employee_id')->where('user_id', (int) $person)))
                ->when($record?->getKey() !== null, fn (Builder $query): Builder => $query->whereKeyNot($record?->getKey()))
                ->value('id');

            if ($other !== null) {
                $fail("この人の{$month->format('Y年n月')}の安全衛生チェックは既にあります（レコード番号 {$other}）。");
            }
        };
    }
}
