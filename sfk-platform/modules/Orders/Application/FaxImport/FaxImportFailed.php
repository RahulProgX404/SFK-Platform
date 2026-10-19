<?php

declare(strict_types=1);

namespace Modules\Orders\Application\FaxImport;

use RuntimeException;

/** The purchase order could not be read; the message is shown to the user as it is. */
final class FaxImportFailed extends RuntimeException {}
