<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

/**
 * A summary card (core::filament.app-summary): the list summaries of the apps (AppListRecords::summaryCards()) and the
 * cards of a record page (Records\RecordTabs).
 */
final class SummaryCard
{
    /**
     * kind: count (件数), yen (an amount), signed (an amount, green above / red below zero), percent or number; the tone
     * (blue | green | amber | red | purple | slate) and the icon follow the kind unless given.
     *
     * @return array{label: string, value: string, tone: string, icon: string}
     */
    public static function make(string $label, int|float|string|null $value, string $kind = 'yen', ?string $tone = null, ?string $icon = null): array
    {
        $number = is_numeric($value) ? (float) $value : null;

        return [
            'label' => $label,
            'value' => match (true) {
                $number === null => '—',
                $kind === 'count' => number_format((int) $number).'件',
                $kind === 'percent' => $number.'%',
                $kind === 'number' => rtrim(rtrim(number_format($number, 2), '0'), '.'),
                default => AppFormat::yen((int) round($number)),
            },
            'tone' => $tone ?? match (true) {
                $kind === 'count' => 'slate',
                in_array($kind, ['signed', 'percent'], true) => $number !== null && $number < 0 ? 'red' : 'green',
                default => 'blue',
            },
            'icon' => $icon ?? match ($kind) {
                'count' => 'heroicon-o-document-text',
                'signed' => $number !== null && $number < 0 ? 'heroicon-o-arrow-trending-down' : 'heroicon-o-arrow-trending-up',
                'percent' => 'heroicon-o-chart-pie',
                'number' => 'heroicon-o-cube',
                default => 'heroicon-o-banknotes',
            },
        ];
    }
}
