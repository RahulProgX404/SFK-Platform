<?php

declare(strict_types=1);

namespace Modules\Core\Application\Legacy;

use Illuminate\Support\Facades\DB;

/**
 * 取引先 of the legacy records (taken over by 01 before the other apps): by 得意先ID (the partners keep it as
 * `legacy_id`), or by 取引先名 where a record holds only the name (legacy lookups copied it exactly; trailing spaces
 * and full-width spaces are compared loosely when the exact name is not found).
 */
final class LegacyPartners
{
    /** @var array<int, int>|null 得意先ID => id */
    private ?array $ids = null;

    /** @var array<string, int>|null 取引先名 => id */
    private ?array $names = null;

    /** @var array<string, int>|null loose name => id */
    private ?array $looseNames = null;

    public function id(?int $legacyId): ?int
    {
        if ($legacyId === null) {
            return null;
        }

        $this->ids ??= DB::table('partners')->whereNotNull('legacy_id')->pluck('id', 'legacy_id')->map(fn (mixed $id): int => (int) $id)->all();

        return $this->ids[$legacyId] ?? null;
    }

    public function named(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        if ($this->names === null) {
            $this->names = [];
            $this->looseNames = [];
            foreach (DB::table('partners')->orderBy('id')->get(['id', 'name']) as $partner) {
                $this->names[(string) $partner->name] ??= (int) $partner->id;
                $this->looseNames[self::loose((string) $partner->name)] ??= (int) $partner->id;
            }
        }

        return $this->names[$name] ?? $this->looseNames[self::loose($name)] ?? null;
    }

    /** By 得意先ID, else by name. */
    public function find(?int $legacyId, ?string $name): ?int
    {
        return $this->id($legacyId) ?? $this->named($name);
    }

    public function forget(): void
    {
        $this->ids = null;
        $this->names = null;
        $this->looseNames = null;
    }

    private static function loose(string $name): string
    {
        return (string) preg_replace('/[\s\x{3000}]+/u', '', mb_convert_kana($name, 'asKV'));
    }
}
