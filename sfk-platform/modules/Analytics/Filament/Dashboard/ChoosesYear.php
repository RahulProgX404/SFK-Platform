<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Dashboard;

/**
 * The year choice of a 12-month chart card (personal-growth.png): this year and the four before it; this year's
 * months after the current one have no value yet.
 */
trait ChoosesYear
{
    /** the chosen year (wire:model of the year choice); blank = this year */
    public string $year = '';

    /** @return list<int> this year first */
    protected function yearChoices(): array
    {
        $now = (int) $this->today()->format('Y');

        return range($now, $now - 4);
    }

    protected function chosenYear(): int
    {
        $choices = $this->yearChoices();

        return in_array((int) $this->year, $choices, true) ? (int) $this->year : $choices[0];
    }

    /**
     * The months of this year after the current one become null (no line drawn there yet).
     *
     * @template T of int|float
     *
     * @param  array<int, T>  $values  month (1–12) => value
     * @return array<int, T|null>
     */
    protected function upToToday(array $values, int $year): array
    {
        $today = $this->today();
        if ($year < (int) $today->format('Y')) {
            return $values;
        }

        $last = (int) $today->format('n');
        foreach ($values as $month => $value) {
            if ($month > $last) {
                $values[$month] = null;
            }
        }

        return $values;
    }
}
