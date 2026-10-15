<?php

declare(strict_types=1);

namespace Modules\Core\Application\Apps;

use RuntimeException;

/** An app / space operation the acting user may not perform; the message is shown to the user. */
final class AppActionNotAllowed extends RuntimeException {}
