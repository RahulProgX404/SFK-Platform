<?php

declare(strict_types=1);

namespace Modules\Core\Application\Numbering;

use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;
use Modules\Core\Models\DocumentSequence;

/**
 * Issues sequential document numbers (注番, 納品書番号, 請求書番号 …) without gaps caused by
 * races: the sequence row is locked (SELECT … FOR UPDATE) inside a transaction.
 *
 * Replaces the legacy pattern "max(existing) + 1" which could produce duplicates under
 * concurrent use (SYSTEM_BRAIN 17 D-GEN-1).
 *
 * Call next() inside the same transaction that saves the document so a rollback also rolls
 * back the number.
 */
final class DocumentNumberGenerator
{
    public function __construct(private readonly DatabaseManager $db) {}

    public function next(string $key): string
    {
        return $this->db->transaction(function () use ($key): string {
            $sequence = $this->lockedSequence($key);
            $value = $sequence->next_value;
            $sequence->next_value = $value + 1;
            $sequence->save();

            return $sequence->format($value);
        });
    }

    /** The number next() would return, without consuming it (display only). */
    public function peek(string $key): string
    {
        $sequence = DocumentSequence::query()->where('key', $key)->first() ?? $this->newFromConfig($key);

        return $sequence->format($sequence->next_value);
    }

    /** Migration helper: make sure the next number is at least $nextValue (e.g. max legacy number + 1). */
    public function ensureNextAtLeast(string $key, int $nextValue): void
    {
        $this->db->transaction(function () use ($key, $nextValue): void {
            $sequence = $this->lockedSequence($key);

            if ($sequence->next_value < $nextValue) {
                $sequence->next_value = $nextValue;
                $sequence->save();
            }
        });
    }

    private function lockedSequence(string $key): DocumentSequence
    {
        $template = $this->newFromConfig($key);

        DocumentSequence::query()->firstOrCreate(['key' => $key], [
            'prefix' => $template->prefix,
            'padding' => $template->padding,
            'next_value' => $template->next_value,
        ]);

        return DocumentSequence::query()->where('key', $key)->lockForUpdate()->firstOrFail();
    }

    private function newFromConfig(string $key): DocumentSequence
    {
        /** @var array{prefix?: string, padding?: int, start?: int}|null $definition */
        $definition = config("core.sequences.{$key}");

        if (! is_array($definition)) {
            throw new InvalidArgumentException("Unknown document sequence [{$key}]. Define it in core.sequences.");
        }

        return new DocumentSequence([
            'key' => $key,
            'prefix' => $definition['prefix'] ?? '',
            'padding' => $definition['padding'] ?? 7,
            'next_value' => $definition['start'] ?? 1,
        ]);
    }
}
