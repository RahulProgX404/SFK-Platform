<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Calendar;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Stringable;

/**
 * A calendar month (or billing-cycle month, 月度). Used as the period of AR/AP ledgers.
 */
final readonly class YearMonth implements Stringable
{
    private function __construct(public int $year, public int $month)
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("Invalid month [{$month}].");
        }
    }

    public static function of(int $year, int $month): self
    {
        return new self($year, $month);
    }

    public static function fromDate(DateTimeInterface $date): self
    {
        return new self((int) $date->format('Y'), (int) $date->format('n'));
    }

    /** Parses "YYYY年MM月" (kintone label) or "YYYY-MM". */
    public static function parse(string $value): self
    {
        if (preg_match('/^(\d{4})(?:年|-)(\d{1,2})月?$/u', trim($value), $m) !== 1) {
            throw new InvalidArgumentException("Cannot parse year-month [{$value}].");
        }

        return new self((int) $m[1], (int) $m[2]);
    }

    public function next(): self
    {
        return $this->month === 12 ? new self($this->year + 1, 1) : new self($this->year, $this->month + 1);
    }

    public function previous(): self
    {
        return $this->month === 1 ? new self($this->year - 1, 12) : new self($this->year, $this->month - 1);
    }

    public function firstDay(): CarbonImmutable
    {
        return CarbonImmutable::create($this->year, $this->month, 1)->startOfDay();
    }

    public function lastDay(): CarbonImmutable
    {
        return $this->firstDay()->endOfMonth()->startOfDay();
    }

    public function equals(self $other): bool
    {
        return $this->year === $other->year && $this->month === $other->month;
    }

    /** "2026-07" — sortable key for storage. */
    public function key(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }

    /** "2026年07月" — label format used throughout the kintone system. */
    public function label(): string
    {
        return sprintf('%04d年%02d月', $this->year, $this->month);
    }

    public function __toString(): string
    {
        return $this->key();
    }
}
