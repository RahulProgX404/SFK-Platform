<?php

declare(strict_types=1);

namespace Modules\Analytics\Domain;

use DateTimeImmutable;

/**
 * The days the sales data of 06. 売上入力 covers (dashboard refinement, 2026-11-14): from the first recorded 売上日 to
 * the last one — or to today while sales are still being entered. A day outside it has no data, which is not the same
 * as no sales: the legacy import ends on its export day (2026-10-16) and starts in May 2025, so before this a period
 * reaching past either end compared real sales with missing ones (「前期間比 ▼ 99%」, ¥0 months in 売上の推移).
 *
 * Sales are entered every working day, so the data counts as current until STALE_DAYS pass without any 売上日
 * (longer than the longest holidays, ゴールデンウィーク / 年末年始); after that it ends on the last recorded day.
 */
final readonly class SalesCoverage
{
    public const STALE_DAYS = 14;

    private function __construct(
        public ?DateTimeImmutable $first,
        public ?DateTimeImmutable $last,
        public DateTimeImmutable $today,
    ) {}

    /** From the first and last 売上日 (Y-m-d…, null without sales) and today. */
    public static function of(?string $first, ?string $last, DateTimeImmutable $today): self
    {
        $day = fn (?string $value): ?DateTimeImmutable => $value === null || $value === '' ? null : new DateTimeImmutable(substr($value, 0, 10));

        return new self($day($first), $day($last), $today->setTime(0, 0));
    }

    public function hasData(): bool
    {
        return $this->first !== null && $this->last !== null;
    }

    /** No 売上日 for more than STALE_DAYS: the data stops at the last recorded day. */
    public function isStale(): bool
    {
        return $this->last !== null && (int) $this->last->diff($this->today)->format('%r%a') > self::STALE_DAYS;
    }

    /** The last day with data: the last 売上日 when the data stopped, else today (or a later recorded day). */
    public function end(): ?DateTimeImmutable
    {
        if ($this->last === null) {
            return null;
        }

        return $this->isStale() ? $this->last : max($this->today, $this->last);
    }

    /** Every day of the period has data. */
    public function covers(Period $period): bool
    {
        return $this->hasData() && $period->from >= $this->first && $period->to <= $this->end();
    }

    /**
     * Why the period's figures are incomplete, for the card: 「売上データは2026/10/16まで」, 「…2025/05/01から」, both, or
     * 「2026/11/14時点」 for days still to come; null when the period is covered.
     */
    public function note(Period $period): ?string
    {
        if (! $this->hasData()) {
            return '売上データがありません';
        }
        if ($this->covers($period)) {
            return null;
        }

        $end = $this->end();
        $before = $period->from < $this->first;
        $after = $period->to > $end;
        if ($after && ! $before && ! $this->isStale()) {
            return $end->format('Y/m/d').'時点';
        }

        return '売上データは'.match (true) {
            $before && $after => $this->first->format('Y/m/d').'〜'.$end->format('Y/m/d'),
            $before => $this->first->format('Y/m/d').'から',
            default => $end->format('Y/m/d').'まで',
        };
    }

    /**
     * A calendar month in a 12-month chart: 'none' (no data in it — not drawn), 'partial' with the day the data starts
     * or ends in it, or 'full'.
     *
     * @return array{0: 'none'|'partial'|'full', 1: string|null} state, note (「10/16まで」「11/14時点」「5/10から」)
     */
    public function month(int $year, int $month): array
    {
        if (! $this->hasData()) {
            return ['none', null];
        }

        $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $last = $start->modify('last day of this month');
        $end = $this->end();
        if ($last < $this->first || $start > $end) {
            return ['none', null];
        }
        if ($end < $last) {
            return ['partial', $end->format('n/j').($this->isStale() ? 'まで' : '時点')];
        }
        if ($this->first > $start) {
            return ['partial', $this->first->format('n/j').'から'];
        }

        return ['full', null];
    }
}
