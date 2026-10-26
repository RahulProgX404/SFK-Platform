<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Documents;

use DateTimeInterface;

/**
 * The value of a field for a 帳票: the text shown on the record page and the stored value behind it (a number, a date,
 * a string or null), which the 書式 of a placed field formats instead of the text.
 */
final readonly class DocumentValue
{
    public function __construct(
        public string $text,
        public int|float|string|bool|DateTimeInterface|null $raw = null,
    ) {}

    public static function text(string $text): self
    {
        return new self($text, $text);
    }

    public static function empty(): self
    {
        return new self('', null);
    }
}
