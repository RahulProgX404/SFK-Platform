<?php

declare(strict_types=1);

namespace Modules\Core\Application\Documents;

/** A put-out 帳票: file name with extension, media type and the bytes. */
final readonly class PrintedFile
{
    public function __construct(
        public string $name,
        public string $mimeType,
        public string $contents,
    ) {}

    public function isPdf(): bool
    {
        return $this->mimeType === 'application/pdf';
    }
}
