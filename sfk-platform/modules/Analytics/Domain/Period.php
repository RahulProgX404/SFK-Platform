<?php

declare(strict_types=1);

namespace Modules\Analytics\Domain;

use DateTimeImmutable;

/**
 * The days the dashboard shows (the date button of its header): from and to, both included. Compared with the period
 * of the same length just before it and with the same days a year earlier.
 */
final readonly class Period
{
    private function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
    ) {}

    /** The last 30 days up to the day (the dashboard's default). */
    public static function lastDays(DateTimeImmutable $day, int $days = 30): self
    {
        return new self($day->modify('-'.($days - 1).' days')->setTime(0, 0), $day->setTime(0, 0));
    }

    /** The choices of a card that has no date button of its own (個人の概要 on a user's page): key => label. */
    public const PRESETS = ['month' => '今月', 'lastMonth' => '先月', '30d' => '過去30日間', 'fiscalYear' => '今年度'];

    /** A preset up to the day: this month, last month, the last 30 days or this business year (May–April). */
    public static function preset(string $preset, DateTimeImmutable $day): self
    {
        $day = $day->setTime(0, 0);

        return match ($preset) {
            'month' => new self($day->modify('first day of this month'), $day),
            'lastMonth' => new self($day->modify('first day of last month'), $day->modify('last day of last month')),
            'fiscalYear' => new self(new DateTimeImmutable(FiscalYear::days(FiscalYear::startingYear($day))[0]), $day),
            default => self::lastDays($day),
        };
    }

    /** From two Y-m-d dates (in any order); the default when one is not a date. */
    public static function between(?string $from, ?string $to, DateTimeImmutable $today): self
    {
        $start = self::date($from);
        $end = self::date($to);
        if ($start === null || $end === null) {
            return self::lastDays($today);
        }

        return $start <= $end ? new self($start, $end) : new self($end, $start);
    }

    /** Y-m-d of the first day */
    public function start(): string
    {
        return $this->from->format('Y-m-d');
    }

    /** Y-m-d of the day after the last one: compare `< after`, so a date stored with a time still counts. */
    public function after(): string
    {
        return $this->to->modify('+1 day')->format('Y-m-d');
    }

    public function days(): int
    {
        return (int) $this->from->diff($this->to)->days + 1;
    }

    /** The period of the same length just before this one. */
    public function previous(): self
    {
        return new self($this->from->modify('-'.$this->days().' days'), $this->from->modify('-1 day'));
    }

    /** The same days a year earlier. */
    public function lastYear(): self
    {
        return new self($this->from->modify('-1 year'), $this->to->modify('-1 year'));
    }

    /**
     * The calendar months the period touches.
     *
     * @return list<array{0: int, 1: int}> year, month
     */
    public function months(): array
    {
        $months = [];
        $month = $this->from->modify('first day of this month');
        while ($month <= $this->to) {
            $months[] = [(int) $month->format('Y'), (int) $month->format('n')];
            $month = $month->modify('+1 month');
        }

        return $months;
    }

    /**
     * The period cut into up to $count equal parts (the small bars of 売上概要).
     *
     * @return list<array{0: string, 1: string}> Y-m-d from, Y-m-d after
     */
    public function parts(int $count): array
    {
        $count = max(1, min($count, $this->days()));
        $size = (int) ceil($this->days() / $count);
        $parts = [];
        for ($start = $this->from; $start <= $this->to; $start = $start->modify('+'.$size.' days')) {
            $end = min($start->modify('+'.($size - 1).' days'), $this->to);
            $parts[] = [$start->format('Y-m-d'), $end->modify('+1 day')->format('Y-m-d')];
        }

        return $parts;
    }

    /** 「2026/10/07 〜 2026/11/05」 */
    public function label(): string
    {
        return $this->from->format('Y/m/d').' 〜 '.$this->to->format('Y/m/d');
    }

    private static function date(?string $value): ?DateTimeImmutable
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
