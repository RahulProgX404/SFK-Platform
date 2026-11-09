<?php

declare(strict_types=1);

namespace Modules\HumanResources\Application\LeaveRequests;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Modules\Core\Application\Records\RecordRules;
use Modules\Core\Application\Records\RecordValidator;
use Modules\HumanResources\Domain\HrChoices;
use Modules\HumanResources\Domain\HrFields;
use Modules\HumanResources\Models\LeaveRequest;

/**
 * What a request of 16. 申請管理 must be (user, 2026-11-09; SYSTEM_BRAIN APP_16 fields.md), on every screen:
 * - required: 申請区分, 申請分類, 対象日付, 申請者, 申請理由 (legacy);
 * - an absence by the hour (遅刻(時間欠勤) / 早退(時間欠勤)) gives its 合計時間 (more than 0) and its 出勤・退勤時間; times in
 *   30-minute steps (BR-HR-02);
 * - one request per person, 対象日付 and 申請分類 — the person is 作業員名, or 申請者 when the request is their own (an
 *   administrator often files for an employee).
 * 申請区分 and 申請分類 may combine freely (e.g. 早退申請 with 半休(午後欠勤)), as in the legacy records.
 */
final class LeaveRequestRules implements RecordRules
{
    private const HALF_HOUR = 'regex:/^([01]\d|2[0-3]):(00|30)(:00)?$/';

    public function values(Model $record): array
    {
        if (! $record instanceof LeaveRequest) {
            return [];
        }

        return [
            ...$record->only(['request_type', 'category', 'reason', 'applicant_id', 'worker_id']),
            'target_on' => $record->target_on->toDateString(),
            'total_time' => $record->getRawOriginal('total_time'),
            'clock_time' => $record->getRawOriginal('clock_time'),
        ];
    }

    public function rules(array $data, ?Model $record): array
    {
        $hourly = in_array($data['category'] ?? null, HrChoices::HOURLY, true);
        $users = Rule::exists((new User)->getTable(), 'id');

        return [
            'request_type' => ['required', Rule::in(HrChoices::REQUEST_TYPES)],
            'category' => ['required', Rule::in(HrChoices::CATEGORIES)],
            'target_on' => ['required', 'date_format:Y-m-d', $this->once($data, $record)],
            'total_time' => [Rule::requiredIf($hourly), 'nullable', self::HALF_HOUR, function (string $attribute, mixed $value, Closure $fail): void {
                if (RecordValidator::minutes($value) === 0) {
                    $fail('遅刻・早退の合計時間は30分以上で入力してください。');
                }
            }],
            'clock_time' => [Rule::requiredIf($hourly), 'nullable', self::HALF_HOUR],
            'reason' => ['required', 'string', 'max:4000'],
            'applicant_id' => ['required', 'integer', $users],
            'worker_id' => ['nullable', 'integer', $users],
        ];
    }

    public function messages(): array
    {
        return [
            'total_time.required' => '時間欠勤の申請は遅刻・早退の合計時間を入力してください。',
            'clock_time.required' => '時間欠勤の申請は遅刻・早退の出勤・退勤時間を入力してください。',
            'total_time.regex' => '30分単位で入力してください。',
            'clock_time.regex' => '30分単位で入力してください。',
        ];
    }

    public function attributes(): array
    {
        $labels = [];
        foreach (['request_type', 'category', 'target_on', 'total_time', 'clock_time', 'reason', 'applicant_id', 'worker_id'] as $field) {
            $labels[$field] = HrFields::LEAVE[$field];
        }

        return $labels;
    }

    /**
     * The same person's request of the same 対象日付 and 申請分類 (another record): the date is taken.
     *
     * @param  array<string, mixed>  $data
     */
    private function once(array $data, ?Model $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($data, $record): void {
            $person = $data['worker_id'] ?? null;
            $person = RecordValidator::filled($person) ? $person : ($data['applicant_id'] ?? null);
            $category = $data['category'] ?? null;
            if (! is_numeric($person) || ! is_string($category) || ! is_string($value)) {
                return;
            }

            $other = LeaveRequest::query()
                ->whereDate('target_on', $value)
                ->where('category', $category)
                ->where(fn (Builder $query): Builder => $query->where('worker_id', (int) $person)
                    ->orWhere(fn (Builder $own): Builder => $own->whereNull('worker_id')->where('applicant_id', (int) $person)))
                ->when($record?->getKey() !== null, fn (Builder $query): Builder => $query->whereKeyNot($record?->getKey()))
                ->value('id');

            if ($other !== null) {
                $fail("同じ人の同じ日付・申請分類の申請が既にあります（申請管理ID {$other}）。");
            }
        };
    }
}
