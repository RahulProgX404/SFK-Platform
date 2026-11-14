<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use Illuminate\Support\Js;

/**
 * The content of an information tooltip (SFK UI 「情報ツールチップ」, core::filament.ui: tippy.js — Filament's `x-tooltip` —
 * with the light `sfk-tip` theme; user 2026-11-14). One structure for every kind:
 * - simple: one line (Tip::text('売上入力を開く'));
 * - detailed: a title, what the figure is, rows of label / value (a coloured dot per data series, ▲ / ▼ tones), the
 *   calculation and a note (Tip::make('前年同期比')->text(…)->row(…)->formula(…));
 * - data: a title and aligned rows (rankings, chart points).
 * Everything is escaped here; the HTML goes to the browser inside the trigger's `x-tooltip="{{ $tip->x() }}"`, so
 * opening a tooltip asks the server nothing.
 */
final class Tip
{
    private ?string $title = null;

    /** @var list<string> */
    private array $parts = [];

    /** @var list<string> rows waiting to be put in one <dl> */
    private array $rows = [];

    public static function make(?string $title = null): self
    {
        $tip = new self;
        $tip->title = $title;

        return $tip;
    }

    /** A simple tooltip: one short text. */
    public static function text(string $text): self
    {
        return self::make()->line($text);
    }

    /** A paragraph (what the figure is, which period). */
    public function line(string $text): self
    {
        $this->flushRows();
        $this->parts[] = '<p class="sfk-tip-text">'.e($text).'</p>';

        return $this;
    }

    /**
     * A row: label on the left, value on the right (tabular numbers).
     *
     * @param  string|null  $color  a dot before the label (a chart's data series)
     * @param  'up'|'down'|'flat'|null  $tone  green / red / grey value (comparison)
     */
    public function row(string $label, string $value, ?string $color = null, ?string $tone = null, bool $strong = false): self
    {
        $dot = $color !== null ? '<i class="sfk-tip-dot" style="background: '.e($color).'"></i>' : '';
        $this->rows[] = '<div'.($strong ? ' class="is-strong"' : '').'><dt>'.$dot.e($label).'</dt><dd'.($tone !== null ? ' class="is-'.e($tone).'"' : '').'>'.e($value).'</dd></div>';

        return $this;
    }

    /** A hairline between groups of rows. */
    public function divider(): self
    {
        $this->rows[] = '<div class="sfk-tip-divider" role="separator"></div>';

        return $this;
    }

    /** 計算方法: how the figure is calculated. */
    public function formula(string $formula): self
    {
        $this->flushRows();
        $this->parts[] = '<p class="sfk-tip-formula"><span>計算方法</span>'.e($formula).'</p>';

        return $this;
    }

    /** A note in muted text (why something is not shown, a limit of the data). */
    public function note(string $text): self
    {
        $this->flushRows();
        $this->parts[] = '<p class="sfk-tip-note">'.e($text).'</p>';

        return $this;
    }

    public function html(): string
    {
        $this->flushRows();

        return '<div class="sfk-tip">'.($this->title !== null ? '<p class="sfk-tip-title">'.e($this->title).'</p>' : '').implode('', $this->parts).'</div>';
    }

    /** The `x-tooltip` expression of the trigger (window.sfkTip, core::filament.ui, holds the shared settings). */
    public function x(): string
    {
        return 'sfkTip('.Js::from($this->html()).')';
    }

    /** ▲ 12.3% / ▼ 4.0% / ±0.0% of a percentage change; 1,000%超 above that. */
    public static function percent(float $change): string
    {
        if ($change == 0.0) {
            return '±0.0%';
        }

        return ($change > 0 ? '▲' : '▼').(abs($change) >= 1000 ? '1,000%超' : number_format(abs($change), 1).'%');
    }

    /** 'up' | 'down' | 'flat' of a change. */
    public static function tone(int|float $change): string
    {
        return $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat');
    }

    /** +¥1,200 / −¥1,200 / ±¥0 */
    public static function signedYen(int $amount): string
    {
        return ($amount > 0 ? '+' : ($amount < 0 ? '−' : '±')).AppFormat::yen(abs($amount));
    }

    private function flushRows(): void
    {
        if ($this->rows !== []) {
            $this->parts[] = '<dl class="sfk-tip-rows">'.implode('', $this->rows).'</dl>';
            $this->rows = [];
        }
    }
}
