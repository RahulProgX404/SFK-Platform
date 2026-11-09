<?php

declare(strict_types=1);

namespace Modules\HumanResources\Application\AlcoholChecks;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Modules\Core\Application\Records\RecordRules;
use Modules\Core\Application\Records\RecordValidator;
use Modules\HumanResources\Domain\HrChoices;
use Modules\HumanResources\Domain\HrFields;
use Modules\HumanResources\Models\AlcoholCheck;

/**
 * What a record of 18. アルコールチェック must be (user, 2026-11-09; SYSTEM_BRAIN APP_18, BR-HR-04: the check before and
 * after driving with its confirmer and method, one record per driving day), on every screen:
 * - required: 検査日, 氏名 (legacy) and the whole 運転前 — 検査時刻, 車両番号, 確認方法, 酒気帯び運転の有無, 確認者;
 * - 検査日 not in the future; one record per 氏名 and 検査日;
 * - 確認者 someone else than 氏名 (a driver does not confirm themselves), before and after;
 * - 運転後 may be added later, but once one of its results is entered (車両番号, 確認方法, 酒気帯び運転の有無, 確認者) it is
 *   complete, with a 検査時刻 after the one of 運転前 (a time alone — the legacy default — is no 運転後).
 */
final class AlcoholCheckRules implements RecordRules
{
    private const TIME = 'regex:/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/';

    /** The results of a block that make it begun. */
    private const RESULTS = ['vehicle', 'method', 'detected', 'checker_id'];

    public function values(Model $record): array
    {
        if (! $record instanceof AlcoholCheck) {
            return [];
        }

        $values = ['checked_on' => $record->checked_on->toDateString()];
        foreach (array_keys(HrFields::ALCOHOL) as $field) {
            if (in_array($field, ['driver_id', 'employee_id'], true) || str_starts_with($field, 'before_') || str_starts_with($field, 'after_')) {
                $values[$field] = str_ends_with($field, '_time') ? $record->getRawOriginal($field) : $record->getAttribute($field);
            }
        }

        return $values;
    }

    public function rules(array $data, ?Model $record): array
    {
        $users = Rule::exists((new User)->getTable(), 'id');
        $after = array_filter(self::RESULTS, fn (string $field): bool => RecordValidator::filled($data['after_'.$field] ?? null)) !== [];

        $rules = [
            'checked_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', $this->oncePerDay($data, $record)],
            'driver_id' => ['required', 'integer', $users],
            'employee_id' => ['nullable', 'integer', $users],
        ];
        foreach (['before' => true, 'after' => $after] as $block => $required) {
            $presence = $required ? 'required' : 'nullable';
            $rules += [
                $block.'_time' => [$presence, self::TIME],
                $block.'_vehicle' => [$presence, Rule::in(HrChoices::VEHICLES)],
                $block.'_method' => [$presence, Rule::in(HrChoices::METHODS)],
                $block.'_detected' => [$presence, Rule::in(HrChoices::DETECTED)],
                $block.'_checker_id' => [$presence, 'integer', $users, function (string $attribute, mixed $value, Closure $fail) use ($data): void {
                    if (RecordValidator::sameId($value, $data['driver_id'] ?? null)) {
                        $fail('確認者には氏名と別の人を選んでください。');
                    }
                }],
                $block.'_instruction' => ['nullable', 'string', 'max:255'],
                $block.'_notes' => ['nullable', 'string', 'max:4000'],
            ];
        }
        if ($after) {
            $rules['after_time'][] = function (string $attribute, mixed $value, Closure $fail) use ($data): void {
                $before = RecordValidator::minutes($data['before_time'] ?? null);
                $after = RecordValidator::minutes($value);
                if ($before !== null && $after !== null && $after <= $before) {
                    $fail('検査時刻(運転後)は検査時刻(運転前)より後の時刻を入力してください。');
                }
            };
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = ['checked_on.before_or_equal' => '検査日に未来の日付は入力できません。'];
        foreach (['time', 'vehicle', 'method', 'detected', 'checker_id'] as $field) {
            $label = HrFields::ALCOHOL['after_'.$field];
            $messages['before_'.$field.'.required'] = '運転前の'.HrFields::ALCOHOL['before_'.$field].'を入力してください。';
            $messages['after_'.$field.'.required'] = '運転後を入力するときは'.$label.'も入力してください。';
        }

        return $messages;
    }

    public function attributes(): array
    {
        $labels = [];
        foreach (array_keys(HrFields::ALCOHOL) as $field) {
            $labels[$field] = HrFields::alcohol($field, qualified: true);
        }

        return $labels;
    }

    /** @param  array<string, mixed>  $data */
    private function oncePerDay(array $data, ?Model $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($data, $record): void {
            $driver = $data['driver_id'] ?? null;
            if (! is_numeric($driver) || ! is_string($value)) {
                return;
            }

            $other = AlcoholCheck::query()
                ->whereDate('checked_on', $value)
                ->where('driver_id', (int) $driver)
                ->when($record?->getKey() !== null, fn (Builder $query): Builder => $query->whereKeyNot($record?->getKey()))
                ->value('id');

            if ($other !== null) {
                $fail("この人のこの日のアルコールチェックは既にあります（レコード番号 {$other}）。");
            }
        };
    }
}
