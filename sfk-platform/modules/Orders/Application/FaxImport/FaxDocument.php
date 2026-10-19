<?php

declare(strict_types=1);

namespace Modules\Orders\Application\FaxImport;

/** An uploaded purchase order: its content (base64), media type and file name. */
final readonly class FaxDocument
{
    public const MEDIA_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'application/pdf'];

    public function __construct(
        public string $base64,
        public string $mediaType,
        public string $fileName,
    ) {}

    /** From the file's bytes; the media type by MIME type, else by extension (default JPEG, like the legacy tool). */
    public static function fromContents(string $contents, ?string $mimeType, string $fileName): self
    {
        $type = match (true) {
            in_array($mimeType, self::MEDIA_TYPES, true) => (string) $mimeType,
            $mimeType === 'image/jpg' => 'image/jpeg',
            default => match (strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
                'pdf' => 'application/pdf',
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => 'image/jpeg',
            },
        };

        return new self(base64_encode($contents), $type, $fileName);
    }

    public function isPdf(): bool
    {
        return $this->mediaType === 'application/pdf';
    }
}
