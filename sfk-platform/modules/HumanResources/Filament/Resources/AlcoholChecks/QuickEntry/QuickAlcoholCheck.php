<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\AlcoholChecks\QuickEntry;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\QuickEntry\QuickEntry;
use Modules\Core\Filament\QuickEntry\QuickField;
use Modules\Core\Filament\QuickEntry\QuickPeople;
use Modules\Core\Filament\QuickEntry\QuickStep;
use Modules\HumanResources\Domain\HrChoices;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\AlcoholCheckLayout;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\AlcoholCheckResource;
use Modules\HumanResources\Models\AlcoholCheck;

/**
 * 新規 of 18. アルコールチェック as a quick entry (user, 2026-11-09; SYSTEM_BRAIN APP_18 fields.md, BR-HR-04): 運転前 — 車両番号,
 * 確認方法, 酒気帯び運転の有無, 確認者 (taps), then 検査時刻 (now), 指示項目 (the legacy text) and その他必要な項目 — and
 * 運転後 the same, starting from the vehicle, the method and the checker of 運転前; 「運転後はあとで入力」 saves 運転前 only
 * (運転後 is added on the record later). 検査日 is today and 氏名 the signed-in user unless changed in 追加情報. 有 notifies
 * the administrators at once, as before (AlcoholAlert on the saved record).
 */
final class QuickAlcoholCheck extends QuickEntry
{
    /** The fields of 運転後 (emptied by 「運転後はあとで入力」). */
    public const AFTER = ['after_time', 'after_vehicle', 'after_method', 'after_detected', 'after_checker_id', 'after_instruction', 'after_notes'];

    public static function resource(): string
    {
        return AlcoholCheckResource::class;
    }

    public function heading(): string
    {
        return 'アルコールチェックを追加';
    }

    public function label(string $field): string
    {
        return AlcoholCheckLayout::label($field);
    }

    /** 「車両番号(運転後)」: the two blocks share their labels. */
    public function reviewLabel(string $field): string
    {
        return AlcoholCheckLayout::label($field, qualified: true);
    }

    protected function stepList(): array
    {
        // the confirmer is someone else than the driver (AlcoholCheckRules): the driver is not offered
        $checkers = fn (string $field): array => array_diff_key(QuickPeople::users(self::toId($this->data[$field] ?? null)),
            [(int) ($this->data['driver_id'] ?? 0) => true]);
        $steps = [];
        foreach (['before' => '運転前', 'after' => '運転後'] as $block => $section) {
            // 運転前 is required as a whole (BR-HR-04); 運転後 once it is begun (AlcoholCheckRules)
            $required = $block === 'before';
            $blockSteps = [
                QuickStep::make($block.'_vehicle', $this->label($block.'_vehicle'), [QuickField::choice($block.'_vehicle', HrChoices::options(HrChoices::VEHICLES), required: $required)]),
                QuickStep::make($block.'_method', $this->label($block.'_method'), [QuickField::choice($block.'_method', HrChoices::options(HrChoices::METHODS), 3, $required)]),
                QuickStep::make($block.'_detected', $this->label($block.'_detected'), [QuickField::choice($block.'_detected', HrChoices::options(HrChoices::DETECTED), 2, $required)]),
                QuickStep::make($block.'_checker', $this->label($block.'_checker_id'), [QuickField::person($block.'_checker_id', $checkers($block.'_checker_id'), $required)]),
                QuickStep::make($block.'_more', $this->label($block.'_time').'・'.$this->label($block.'_instruction'), [
                    QuickField::time($block.'_time', [now()->format('H:i') => '今'], $required),
                    QuickField::text($block.'_instruction'),
                    QuickField::textarea($block.'_notes'),
                ]),
            ];
            foreach ($blockSteps as $index => $step) {
                $step = $step->section($section);
                $steps[] = $block === 'after' && $index === 0 ? $step->skip('運転後はあとで入力', self::AFTER) : $step;
            }
        }

        return $steps;
    }

    protected function detailList(): array
    {
        $today = now();

        return [
            QuickField::date('checked_on', [$today->toDateString() => '今日', $today->copy()->subDay()->toDateString() => '昨日'], required: true)->inReview(),
            QuickField::select('driver_id', QuickPeople::users(self::toId($this->data['driver_id'] ?? null)), required: true)->inReview(),
            QuickField::select('employee_id', QuickPeople::employees(self::toId($this->data['employee_id'] ?? null))),
        ];
    }

    protected function defaults(): array
    {
        return [
            'checked_on' => now()->toDateString(), 'driver_id' => self::userId(), 'employee_id' => null,
            'before_time' => now()->format('H:i'), 'before_vehicle' => null, 'before_method' => null, 'before_detected' => null, 'before_checker_id' => null,
            'before_instruction' => HrChoices::BEFORE_INSTRUCTION, 'before_notes' => '',
            'after_time' => null, 'after_vehicle' => null, 'after_method' => null, 'after_detected' => null, 'after_checker_id' => null,
            'after_instruction' => HrChoices::AFTER_INSTRUCTION, 'after_notes' => '',
        ];
    }

    /** 運転後 starts from the vehicle, the method and the checker of 運転前, at the time it is entered. */
    protected function entering(string $step): void
    {
        $from = ['after_vehicle' => 'before_vehicle', 'after_method' => 'before_method', 'after_checker' => 'before_checker_id'][$step] ?? null;
        $field = $step === 'after_checker' ? 'after_checker_id' : $step;
        if ($from !== null && blank($this->data[$field] ?? null)) {
            $this->data[$field] = $this->data[$from] ?? null;
        }
        if ($step === 'after_more' && blank($this->data['after_time'] ?? null)) {
            $this->data['after_time'] = now()->format('H:i');
        }
    }

    /** The rules of every screen of the app (its RecordRules). */
    protected function rules(): array
    {
        return $this->recordRules();
    }

    protected function create(array $values): Model
    {
        $attributes = [];
        foreach ($values as $field => $value) {
            $attributes[$field] = match (true) {
                str_ends_with($field, '_id') => self::toId($value),
                default => self::blank(is_string($value) ? trim($value) : $value),
            };
        }

        return AlcoholCheck::query()->create($attributes);
    }

    /** レコードを再利用する: a new day's check — the date, the times, the results and the notes start afresh. */
    protected function copyOf(Model $source): array
    {
        if (! $source instanceof AlcoholCheck) {
            return $this->defaults();
        }

        $copy = $this->defaults();
        foreach (['driver_id', 'employee_id', 'before_vehicle', 'before_method', 'before_checker_id', 'before_instruction', 'after_vehicle', 'after_method',
            'after_checker_id', 'after_instruction'] as $field) {
            $copy[$field] = $source->getAttribute($field) ?? $copy[$field];
        }

        return $copy;
    }
}
