<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\BagSchedules\Board;

use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Modules\Core\Application\Calendar\CompanyCalendar;
use Modules\Core\Application\History\RecordHistory;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Calendar\CalendarDayType;
use Modules\Production\Application\Schedules\ProductionSchedule;
use Modules\Production\Application\Schedules\ScheduleRejected;
use Modules\Production\Filament\Resources\BagSchedules\BagScheduleResource;
use Modules\Production\Http\Controllers\ProductionScheduleScript;
use Modules\Production\Models\BagSchedule;
use Throwable;

/**
 * The 製袋予定表 of 12. 製袋予定管理 (view 製袋予定表 of the list): production-schedule.js draws and changes it in the
 * browser and calls these methods for its data (adapters.livewire). Every call answers {ok: true, …} or {ok: false,
 * error} and checks the app's rights (追加 / 編集 / 削除, レコードのアクセス権) itself; saved changes go to 変更履歴.
 */
final class BagScheduleBoard extends Component
{
    /** Longest period the board asks for at once (a month and the week around it). */
    private const MAX_DAYS = 62;

    public function render(): View
    {
        return view('production::filament.bag-schedule-board', [
            'script' => ProductionScheduleScript::url(),
            'options' => [
                'lines' => array_map(
                    fn (array $line): array => array_diff_key($line, ['machine' => true]),
                    app(ProductionSchedule::class)->lines(),
                ),
                'permissions' => [
                    'canCreate' => BagScheduleResource::canCreate(),
                    'canEdit' => BagScheduleResource::appCan(AppAbility::Update),
                    'canDelete' => BagScheduleResource::appCan(AppAbility::Delete),
                ],
                'initialView' => 'week',
                'syncUrl' => true,
                'urlParams' => ['date' => 'date', 'view' => 'span'],
                'loadFonts' => false,
            ],
        ]);
    }

    /** @return array<string, mixed> {ok, items} of the days from–to */
    #[Renderless]
    public function scheduleItems(string $from, string $to): array
    {
        return $this->attempt(BagScheduleResource::canViewAny(), function () use ($from, $to): array {
            self::period($from, $to);

            return ['items' => app(ProductionSchedule::class)->items(self::visible(), $from, $to)];
        });
    }

    /** @return array<string, mixed> {ok, days}: the 会社カレンダー's marked days from–to (Sundays are closed by the board itself) */
    #[Renderless]
    public function scheduleCalendar(string $from, string $to): array
    {
        return $this->attempt(BagScheduleResource::canViewAny(), function () use ($from, $to): array {
            [$start, $end] = self::period($from, $to);

            return ['days' => array_map(fn (CalendarDayType $type): string => strtoupper($type->value), app(CompanyCalendar::class)->markedBetween($start, $end))];
        });
    }

    /** @return array<string, mixed> {ok, match}: 担当者 / 品名 / 部数 of 06. 売上入力 for the 注番, match null when none */
    #[Renderless]
    public function scheduleLookup(string $orderNumber): array
    {
        $allowed = BagScheduleResource::canCreate() || BagScheduleResource::appCan(AppAbility::Update);

        return $this->attempt($allowed, fn (): array => ['match' => app(ProductionSchedule::class)->lookup($orderNumber)]);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed> {ok, item}
     */
    #[Renderless]
    public function scheduleCreate(array $item): array
    {
        return $this->attempt(BagScheduleResource::canCreate(), function () use ($item): array {
            $schedule = app(ProductionSchedule::class)->create($item);
            $this->remember($schedule);

            return ['item' => app(ProductionSchedule::class)->item($schedule->refresh())];
        });
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed> {ok, item}
     */
    #[Renderless]
    public function scheduleUpdate(string $id, array $patch): array
    {
        $schedule = self::find($id);

        return $this->attempt($schedule !== null && BagScheduleResource::canEdit($schedule), function () use ($schedule, $patch): array {
            /** @var BagSchedule $schedule */
            app(ProductionSchedule::class)->update($schedule, $patch);
            $this->remember($schedule);

            return ['item' => app(ProductionSchedule::class)->item($schedule->refresh())];
        }, missing: $schedule === null);
    }

    /** @return array<string, mixed> {ok} */
    #[Renderless]
    public function scheduleRemove(string $id): array
    {
        $schedule = self::find($id);

        return $this->attempt($schedule !== null && BagScheduleResource::canDelete($schedule), function () use ($schedule): array {
            $schedule?->delete();

            return [];
        }, missing: $schedule === null);
    }

    /** @return array<string, mixed> {ok, item}: コピー of a dragged schedule */
    #[Renderless]
    public function scheduleCopy(string $id, string $date, string $line): array
    {
        $source = self::find($id);

        return $this->attempt($source !== null && BagScheduleResource::canCreate(), function () use ($source, $date, $line): array {
            /** @var BagSchedule $source */
            $copy = app(ProductionSchedule::class)->copy($source, $date, $line);
            $this->remember($copy);

            return ['item' => app(ProductionSchedule::class)->item($copy->refresh())];
        }, missing: $source === null);
    }

    /**
     * @param  Closure(): array<string, mixed>  $action
     * @return array<string, mixed>
     */
    private function attempt(bool $allowed, Closure $action, bool $missing = false): array
    {
        if ($missing) {
            return ['ok' => false, 'error' => 'この予定は既に存在しません。'];
        }
        if (! $allowed) {
            return ['ok' => false, 'error' => 'この操作を行う権限がありません。'];
        }

        try {
            return ['ok' => true, ...$action()];
        } catch (ScheduleRejected $rejected) {
            return ['ok' => false, 'error' => $rejected->getMessage()];
        } catch (Throwable $exception) {
            report($exception);

            return ['ok' => false];
        }
    }

    /** Saved changes go to 変更履歴 when the app keeps it. */
    private function remember(BagSchedule $schedule): void
    {
        $app = BagScheduleResource::app();
        $user = auth()->user();
        if ($app === null || $app->record_history_enabled) {
            app(RecordHistory::class)->record($schedule, $user instanceof User ? $user->id : null);
        }
    }

    /** A schedule the user may see (レコードのアクセス権). */
    private static function find(string $id): ?BagSchedule
    {
        $schedule = ctype_digit($id) ? self::visible()->find((int) $id) : null;

        return $schedule instanceof BagSchedule ? $schedule : null;
    }

    /** @return Builder<BagSchedule> */
    private static function visible(): Builder
    {
        /** @var Builder<BagSchedule> $query */
        $query = BagScheduleResource::getEloquentQuery();

        return $query;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private static function period(string $from, string $to): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $from);
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $to);
        if (! $start instanceof CarbonImmutable || ! $end instanceof CarbonImmutable || $start->toDateString() !== $from || $end->toDateString() !== $to
            || $end->lt($start) || $start->diffInDays($end) > self::MAX_DAYS) {
            throw new ScheduleRejected('日付の形式が正しくありません。');
        }

        return [$start, $end];
    }
}
