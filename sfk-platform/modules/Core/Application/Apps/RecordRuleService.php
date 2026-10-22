<?php

declare(strict_types=1);

namespace Modules\Core\Application\Apps;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Domain\Apps\AccessSubject;
use Modules\Core\Domain\Filters\FilterFieldType;
use Modules\Core\Domain\Filters\FilterOperator;
use Modules\Core\Domain\Filters\RecordFilter;
use Modules\Core\Models\AppRecordRule;
use Modules\Core\Models\AppRecordRuleEntry;
use Modules\Core\Models\BusinessApp;

/**
 * レコードのアクセス権 of an app as the settings screen edits them: the rules in order, each with its condition rows
 * (field, comparison, value — stored as a 絞り込む filter) and its entries in order. Saving replaces all rules of the
 * app; a condition row that is not complete is refused (a dropped row would widen the rule). Administrators only.
 */
final class RecordRuleService
{
    /**
     * The form state of the app's rules.
     *
     * @param  array<string, FilterField>  $fields
     * @return list<array<string, mixed>>
     */
    public function state(BusinessApp $app, array $fields): array
    {
        return array_map(fn (AppRecordRule $rule): array => [
            'match' => ($rule->filter['match'] ?? RecordFilter::MATCH_ALL) === RecordFilter::MATCH_ANY ? RecordFilter::MATCH_ANY : RecordFilter::MATCH_ALL,
            'conditions' => array_map(fn (array $condition): array => self::conditionState($condition, $fields), array_values((array) ($rule->filter['conditions'] ?? []))),
            'entries' => $rule->entries->map(fn (AppRecordRuleEntry $entry): array => [
                'subject_type' => $entry->subject_type->value,
                'user_id' => $entry->user_id,
                'employee_category_id' => $entry->employee_category_id,
                'can_view' => $entry->can_view,
                'can_edit' => $entry->can_edit,
                'can_delete' => $entry->can_delete,
            ])->all(),
        ], $app->recordRules()->with('entries')->get()->all());
    }

    /**
     * Replaces the app's rules with the form state.
     *
     * @param  list<array<string, mixed>>  $rules
     * @param  array<string, FilterField>  $fields
     * @param  string  $statePath  error key (the form's rules repeater; the message names the rule)
     */
    public function save(BusinessApp $app, array $rules, array $fields, User $actor, string $statePath = 'rules'): void
    {
        if (! $actor->user_type->isAdministrator()) {
            throw new AppActionNotAllowed('アクセス権を設定できるのは管理者のみです。');
        }

        $types = array_map(fn (FilterField $field): FilterFieldType => $field->type, $fields);
        $filters = [];
        foreach ($rules as $index => $rule) {
            $rows = array_values((array) ($rule['conditions'] ?? []));
            $filter = RecordFilter::fromArray([
                'match' => ($rule['match'] ?? null) === RecordFilter::MATCH_ANY ? RecordFilter::MATCH_ANY : RecordFilter::MATCH_ALL,
                'conditions' => array_map(fn (mixed $row): array => self::conditionData(is_array($row) ? $row : [], $types), $rows),
            ], $types, []);

            if (count($filter->conditions) !== count($rows)) {
                throw ValidationException::withMessages([$statePath => ($index + 1).'番目の設定：条件を正しく入力してください。']);
            }
            if (array_values((array) ($rule['entries'] ?? [])) === []) {
                throw ValidationException::withMessages([$statePath => ($index + 1).'番目の設定：対象を1つ以上設定してください。']);
            }
            $filters[$index] = $filter->hasConditions() ? ['match' => $filter->match, 'conditions' => $filter->toArray()['conditions']] : null;
        }

        DB::transaction(function () use ($app, $rules, $filters): void {
            $app->recordRules()->delete();

            foreach ($rules as $index => $rule) {
                $saved = $app->recordRules()->create(['sort_order' => $index + 1, 'filter' => $filters[$index]]);
                foreach (array_values((array) ($rule['entries'] ?? [])) as $order => $entry) {
                    $saved->entries()->create([
                        'sort_order' => $order + 1,
                        'subject_type' => AccessSubject::tryFrom((string) ($entry['subject_type'] ?? '')) ?? AccessSubject::Everyone,
                        'user_id' => filled($entry['user_id'] ?? null) ? (int) $entry['user_id'] : null,
                        'employee_category_id' => filled($entry['employee_category_id'] ?? null) ? (int) $entry['employee_category_id'] : null,
                        'can_view' => (bool) ($entry['can_view'] ?? false),
                        'can_edit' => (bool) ($entry['can_edit'] ?? false),
                        'can_delete' => (bool) ($entry['can_delete'] ?? false),
                    ]);
                }
            }
        });
    }

    /**
     * A condition row of the form as filter data: text-like fields take `value` (次のいずれかと等しい: comma separated),
     * dates `date` (a fixed day), choice / user fields `choices`.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, FilterFieldType>  $types
     * @return array<string, mixed>
     */
    private static function conditionData(array $row, array $types): array
    {
        $field = (string) ($row['field'] ?? '');
        $type = $types[$field] ?? null;
        $operator = (string) ($row['operator'] ?? '');
        $value = trim((string) ($row['value'] ?? ''));

        $values = match (true) {
            $type === null => [],
            $type->isDate() => [(string) ($row['date'] ?? '')],
            $type === FilterFieldType::Choice || $type === FilterFieldType::User => array_values(array_map('strval', (array) ($row['choices'] ?? []))),
            $operator === FilterOperator::OneOf->value => preg_split('/\s*[,、，]\s*/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [],
            default => [$value],
        };

        return ['field' => $field, 'operator' => $operator, 'values' => $values, 'mode' => 'specified'];
    }

    /**
     * @param  array<string, mixed>  $condition  stored (FilterCondition::toArray())
     * @param  array<string, FilterField>  $fields
     * @return array<string, mixed>
     */
    private static function conditionState(array $condition, array $fields): array
    {
        $type = ($fields[(string) ($condition['field'] ?? '')] ?? null)?->type;
        $values = array_values((array) ($condition['values'] ?? []));

        return [
            'field' => $condition['field'] ?? null,
            'operator' => $condition['operator'] ?? null,
            'value' => $type !== null && ! $type->isDate() && $type !== FilterFieldType::Choice && $type !== FilterFieldType::User ? implode(', ', $values) : null,
            'date' => $type?->isDate() === true ? ($values[0] ?? null) : null,
            'choices' => $type === FilterFieldType::Choice || $type === FilterFieldType::User ? $values : [],
        ];
    }
}
