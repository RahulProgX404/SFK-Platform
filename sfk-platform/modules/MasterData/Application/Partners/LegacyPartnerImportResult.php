<?php

declare(strict_types=1);

namespace Modules\MasterData\Application\Partners;

/** Outcome of LegacyPartnerImport: counts and what needs a look (values left blank, files not taken over …). */
final readonly class LegacyPartnerImportResult
{
    /** @param  list<string>  $warnings */
    public function __construct(
        public int $created,
        public int $updated,
        public int $usersCreated,
        public array $warnings,
    ) {}
}
