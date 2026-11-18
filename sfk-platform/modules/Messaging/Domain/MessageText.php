<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain;

/**
 * The text of a message as stored: Unicode kept as typed (日本語, नेपाली, emoji), line breaks unified, control
 * characters other than the line break and tab removed, trailing white space trimmed.
 */
final class MessageText
{
    public static function clean(?string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", (string) $text);
        $text = (string) preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $text);

        // not trim(): it works on bytes, and the ideographic space (U+3000) shares its bytes with every hiragana
        return (string) preg_replace('/^[\s\x{3000}]+|[\s\x{3000}]+$/u', '', $text);
    }

    /** One line of preview for the inbox and notifications. */
    public static function preview(string $text, int $length = 60): string
    {
        $line = trim((string) preg_replace('/\s+/u', ' ', $text));

        return mb_strlen($line) > $length ? mb_substr($line, 0, $length).'…' : $line;
    }
}
