<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\LeaveRequests\QuickEntry;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\QuickEntry\QuickEntry;
use Modules\Core\Filament\QuickEntry\QuickField;
use Modules\Core\Filament\QuickEntry\QuickPeople;
use Modules\Core\Filament\QuickEntry\QuickStep;
use Modules\HumanResources\Domain\HrChoices;
use Modules\HumanResources\Filament\Resources\LeaveRequests\LeaveRequestLayout;
use Modules\HumanResources\Filament\Resources\LeaveRequests\LeaveRequestResource;
use Modules\HumanResources\Models\LeaveRequest;

/**
 * 新規 of 16. 申請管理 as a quick entry (user, 2026-11-09; SYSTEM_BRAIN APP_16 fields.md): 申請区分, 申請分類 (taps), 対象日付
 * (今日 / 明日 / 明後日 with one tap), the times of a 遅刻 / 早退 in 30-minute steps (only asked for those, BR-HR-02) and
 * 申請理由; 申請者 is the signed-in user and 作業員名 empty unless changed in 追加情報. The request starts 申請前 with its
 * 作成者 as 作業者 (BR-HR-01), as before.
 */
final class QuickLeaveRequest extends QuickEntry
{
    /** 申請区分 and 申請分類 with a late arrival or an early leave: their times are asked. */
    public const TIMED = ['遅刻申請', '早退申請', '遅刻(時間欠勤)', '早退(時間欠勤)'];

    public static function resource(): string
    {
        return LeaveRequestResource::class;
    }

    public function heading(): string
    {
        return '申請を追加';
    }

    public function label(string $field): string
    {
        return LeaveRequestLayout::label($field);
    }

    protected function stepList(): array
    {
        $today = now();
        $hourly = in_array($this->data['category'] ?? null, HrChoices::HOURLY, true);

        return [
            QuickStep::make('type', $this->label('request_type'), [QuickField::choice('request_type', HrChoices::options(HrChoices::REQUEST_TYPES), 2, required: true)]),
            QuickStep::make('category', $this->label('category'), [QuickField::choice('category', HrChoices::options(HrChoices::CATEGORIES), 2, required: true)]),
            QuickStep::make('date', $this->label('target_on'), [QuickField::date('target_on', [
                $today->toDateString() => '今日',
                $today->copy()->addDay()->toDateString() => '明日',
                $today->copy()->addDays(2)->toDateString() => '明後日',
            ], required: true)]),
            // asked for a 遅刻 / 早退, required for an absence by the hour (LeaveRequestRules)
            QuickStep::make('times', '遅刻・早退', [
                QuickField::select('total_time', self::halfHours(30, 8 * 60), required: $hourly),
                QuickField::select('clock_time', self::halfHours(6 * 60, 22 * 60), required: $hourly),
            ])->when(fn (array $data): bool => array_intersect([(string) ($data['request_type'] ?? ''), (string) ($data['category'] ?? '')], self::TIMED) !== []),
            QuickStep::make('reason', $this->label('reason'), [QuickField::textarea('reason', required: true)]),
        ];
    }

    protected function detailList(): array
    {
        return [
            QuickField::select('applicant_id', QuickPeople::users(self::toId($this->data['applicant_id'] ?? null)), required: true)->inReview(),
            QuickField::select('worker_id', QuickPeople::employees(self::toId($this->data['worker_id'] ?? null))),
        ];
    }

    protected function defaults(): array
    {
        return [
            'request_type' => null, 'category' => null, 'target_on' => null, 'total_time' => null, 'clock_time' => null, 'reason' => '',
            'applicant_id' => self::userId(), 'worker_id' => null,
        ];
    }

    /** The rules of every screen of the app (its RecordRules). */
    protected function rules(): array
    {
        return $this->recordRules();
    }

    protected function create(array $values): Model
    {
        return LeaveRequest::query()->create([
            'request_type' => $values['request_type'],
            'category' => $values['category'],
            'target_on' => $values['target_on'],
            'total_time' => self::blank($values['total_time']),
            'clock_time' => self::blank($values['clock_time']),
            'reason' => trim((string) $values['reason']),
            'applicant_id' => self::toId($values['applicant_id']),
            'worker_id' => self::toId($values['worker_id']),
        ]);
    }

    /** レコードを再利用する: the request again, 申請前 (the legacy id and the status are not copied). */
    protected function copyOf(Model $source): array
    {
        if (! $source instanceof LeaveRequest) {
            return $this->defaults();
        }

        $time = fn (mixed $value): ?string => is_string($value) && $value !== '' ? substr($value, 0, 5) : null;

        return [
            'request_type' => $source->request_type,
            'category' => $source->category,
            'target_on' => $source->target_on->toDateString(),
            'total_time' => $time($source->getRawOriginal('total_time')),
            'clock_time' => $time($source->getRawOriginal('clock_time')),
            'reason' => (string) $source->reason,
            'applicant_id' => $source->applicant_id ?? self::userId(),
            'worker_id' => $source->worker_id,
        ];
    }

    /**
     * Times of the day in 30-minute steps, from / to minutes.
     *
     * @return array<string, string> H:i => H:i
     */
    public static function halfHours(int $from, int $to): array
    {
        $times = [];
        for ($minutes = $from; $minutes <= $to; $minutes += 30) {
            $time = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
            $times[$time] = $time;
        }

        return $times;
    }
}
