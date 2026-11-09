<?php

declare(strict_types=1);

namespace Modules\Production\Application\AccidentReports;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Modules\Core\Application\Records\RecordRules;
use Modules\Core\Application\Records\RecordValidator;
use Modules\MasterData\Models\Machine;
use Modules\Orders\Models\Order;
use Modules\Production\Domain\ProductionChoices;
use Modules\Production\Domain\ProductionFields;
use Modules\Production\Models\AccidentDamage;
use Modules\Production\Models\AccidentReport;

/**
 * What a record of 14. 事故報告管理 must be (user, 2026-11-09; SYSTEM_BRAIN APP_14 fields.md), on every screen:
 * - required: 事故発生日, 報告者氏名, 担当機械 (legacy) and 事故内容(具体的な状況) — a report says what happened;
 * - 事故発生日 not in the future; 数量 and the figures of 損害 not below 0;
 * - 製造者2 only with a 製造者1, and not the same person; 営業関係者 each once;
 * - a 損害 row with a figure (枚数, 原価, 数量) has its 項目, 原価 and 数量 (小計 = 原価 × 数量); a row with only its 項目 —
 *   the legacy form's preset 紙 / 印刷 — is no damage and stays as it is.
 * Several reports of a day, a machine or a reporter are possible: nothing is unique.
 */
final class AccidentReportRules implements RecordRules
{
    private const TEXTS = ['description', 'cause', 'response', 'prevention', 'notes'];

    private const FIGURES = ['sheets', 'cost', 'quantity'];

    public function values(Model $record): array
    {
        if (! $record instanceof AccidentReport) {
            return [];
        }

        return [
            'occurred_on' => $record->occurred_on->toDateString(),
            ...$record->only(['reporter_id', 'order_id', 'quantity', 'machine_id', 'maker1_id', 'maker2_id', ...self::TEXTS]),
            'sales_users' => $record->salesUsers()->pluck('users.id')->map(fn (mixed $id): int => (int) $id)->all(),
            'damages' => $record->damages->map(fn (AccidentDamage $row): array => $row->only(['item', ...self::FIGURES]))->all(),
        ];
    }

    public function rules(array $data, ?Model $record): array
    {
        $figure = ['nullable', 'numeric', 'decimal:0,4', 'min:0', 'max:999999999999'];
        $users = Rule::exists((new User)->getTable(), 'id');

        $rules = [
            'occurred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reporter_id' => ['required', 'integer', $users],
            'order_id' => ['nullable', 'integer', Rule::exists((new Order)->getTable(), 'id')],
            'quantity' => $figure,
            'machine_id' => ['required', 'integer', Rule::exists((new Machine)->getTable(), 'id')],
            'maker1_id' => [Rule::requiredIf(RecordValidator::filled($data['maker2_id'] ?? null)), 'nullable', 'integer', $users],
            'maker2_id' => ['nullable', 'integer', $users, function (string $attribute, mixed $value, Closure $fail) use ($data): void {
                if (RecordValidator::sameId($value, $data['maker1_id'] ?? null)) {
                    $fail('製造者2には製造者1と別の人を選んでください。');
                }
            }],
            'sales_users' => ['nullable', 'array'],
            'sales_users.*' => ['integer', 'distinct', $users],
            'description' => ['required', 'string', 'max:4000'],
            'cause' => ['nullable', 'string', 'max:4000'],
            'response' => ['nullable', 'string', 'max:4000'],
            'prevention' => ['nullable', 'string', 'max:4000'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'damages' => ['nullable', 'array', 'max:50'],
        ];

        foreach ((array) ($data['damages'] ?? []) as $key => $row) {
            $row = is_array($row) ? $row : [];
            $priced = array_filter(self::FIGURES, fn (string $column): bool => RecordValidator::filled($row[$column] ?? null)) !== [];
            $rules["damages.{$key}.item"] = [Rule::requiredIf($priced), 'nullable', Rule::in(ProductionChoices::DAMAGE_ITEMS)];
            $rules["damages.{$key}.sheets"] = $figure;
            $rules["damages.{$key}.cost"] = [Rule::requiredIf($priced), ...$figure];
            $rules["damages.{$key}.quantity"] = [Rule::requiredIf($priced), ...$figure];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'occurred_on.before_or_equal' => '事故発生日に未来の日付は入力できません。',
            'maker1_id.required' => '製造者2を入力するときは製造者1も入力してください。',
            'sales_users.*.distinct' => '営業関係者に同じ人が2回選ばれています。',
            'damages.*.item.required' => '枚数・原価・数量を入力した損害は項目を選んでください。',
            'damages.*.cost.required' => '損害の原価を入力してください（小計 = 原価 × 数量）。',
            'damages.*.quantity.required' => '損害の数量を入力してください（小計 = 原価 × 数量）。',
        ];
    }

    public function attributes(): array
    {
        $label = fn (string $field): string => ProductionFields::ACCIDENT[$field];
        $labels = [];
        foreach (['occurred_on', 'reporter_id', 'order_id', 'quantity', 'machine_id', 'maker1_id', 'maker2_id', 'sales_users', ...self::TEXTS, 'damages'] as $field) {
            $labels[$field] = $label($field);
        }

        return [
            ...$labels,
            'sales_users.*' => $label('sales_users'),
            'damages.*.item' => $label('item'),
            'damages.*.sheets' => $label('sheets'),
            'damages.*.cost' => $label('cost'),
            'damages.*.quantity' => $label('damage_quantity'),
        ];
    }
}
