<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Documents;

/**
 * ファイル名 of a 出力設定: text with fields in braces, e.g. 「{number}_{customer_id}」 for the legacy 見積書番号_得意先名
 * (SYSTEM_BRAIN 08_PRINTCREATOR, APP_09), and 「{ユーザー選択}」 for a field the user chooses when putting the 帳票 out.
 * Characters a file name cannot hold become 「_」.
 */
final class FileNamePattern
{
    /** The field the user chooses when putting the 帳票 out (ファイル名にフィールドを追加 → ユーザーが選択するフィールド). */
    public const CHOSEN = '{ユーザー選択}';

    private const MAX_LENGTH = 150;

    /**
     * The file name without extension, or null when it comes out empty. CHOSEN becomes the $chosen field (nothing
     * when none was chosen).
     *
     * @param  callable(string): string  $value  the text of a field
     */
    public static function render(string $pattern, callable $value, ?string $chosen = null): ?string
    {
        $pattern = str_replace(self::CHOSEN, $chosen !== null && self::isField($chosen) ? '{'.$chosen.'}' : '', $pattern);
        $name = (string) preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z0-9_.]{0,99})\}/',
            fn (array $match): string => $value($match[1]),
            $pattern,
        );

        return self::clean($name);
    }

    /** A text usable as a file name, or null when nothing is left. */
    public static function clean(string $name): ?string
    {
        $name = (string) preg_replace('/[\\\\\/:*?"<>|\x00-\x1F\x7F]+/u', '_', $name);
        $name = trim((string) preg_replace('/\s+/u', ' ', $name), ' ._');
        $name = mb_substr($name, 0, self::MAX_LENGTH);

        return $name === '' ? null : $name;
    }

    /** Whether the user chooses a field of the name when putting the 帳票 out. */
    public static function asksForField(?string $pattern): bool
    {
        return $pattern !== null && str_contains($pattern, self::CHOSEN);
    }

    public static function isField(string $name): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_.]{0,99}$/', $name) === 1;
    }

    /** @return list<string> the fields a pattern uses */
    public static function fields(string $pattern): array
    {
        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_.]{0,99})\}/', $pattern, $matches);

        return array_values(array_unique($matches[1]));
    }
}
