<?php

declare(strict_types=1);

namespace Modules\Orders\Application\FaxImport;

/**
 * Reads the fields of a faxed purchase order (image or PDF) as the JSON of FaxOrderPrompt. Bound to
 * ClaudeFaxOrderReader; tests bind a fake.
 */
interface FaxOrderReader
{
    /**
     * @return array<string, mixed>
     *
     * @throws FaxImportFailed
     */
    public function read(FaxDocument $document): array;

    /**
     * Reads the document again, looking hard at 検印欄, after a first reading that found no stamp there.
     *
     * @param  array<string, mixed>  $firstReading
     * @param  list<string>  $candidates  configured surnames seen in other fields
     * @return array<string, mixed>
     *
     * @throws FaxImportFailed
     */
    public function rereadInspectionStamp(FaxDocument $document, array $firstReading, array $candidates): array;
}
