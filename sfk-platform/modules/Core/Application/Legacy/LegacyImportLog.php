<?php

declare(strict_types=1);

namespace Modules\Core\Application\Legacy;

use Closure;

/**
 * What an import of one legacy app did: records created / updated / skipped and the warnings (values the new system
 * cannot hold, references not found, what is not taken over), in Japanese for the people checking the result.
 */
final class LegacyImportLog
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    /** @var list<string> */
    public array $warnings = [];

    /** @param  (Closure(int): void)|null  $progress  called with the number of records done, every few hundred */
    public function __construct(private readonly ?Closure $progress = null) {}

    public function created(): void
    {
        $this->created++;
        $this->tick();
    }

    public function updated(): void
    {
        $this->updated++;
        $this->tick();
    }

    public function skipped(string $reason): void
    {
        $this->skipped++;
        $this->warn($reason);
        $this->tick();
    }

    public function warn(string $warning): void
    {
        $this->warnings[] = $warning;
    }

    /**
     * An option value the new system offers, else null — reported as 「<label>: <field>「value」は選択肢にないため空欄にしました」.
     *
     * @param  list<string>  $options
     */
    public function choice(?string $value, array $options, string $label, string $field): ?string
    {
        if ($value === null || in_array($value, $options, true)) {
            return $value;
        }

        $this->warn("{$label}: {$field}「{$value}」は選択肢にないため空欄にしました");

        return null;
    }

    /**
     * An option value kept even when the new system no longer offers it (a plain text column: e.g. options removed
     * from the legacy app later stay on its old records) — reported.
     *
     * @param  list<string>  $options
     */
    public function keptChoice(?string $value, array $options, string $label, string $field): ?string
    {
        if ($value !== null && ! in_array($value, $options, true)) {
            $this->warn("{$label}: {$field}「{$value}」は現在の選択肢にありません（そのまま移行しました）");
        }

        return $value;
    }

    public function done(): int
    {
        return $this->created + $this->updated + $this->skipped;
    }

    private function tick(): void
    {
        if ($this->progress !== null && $this->done() % 500 === 0) {
            ($this->progress)($this->done());
        }
    }
}
