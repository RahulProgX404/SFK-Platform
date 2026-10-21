<?php

declare(strict_types=1);

namespace Modules\Sales\Application;

use RuntimeException;

/** A sales operation that cannot be done (e.g. a sale already on a delivery note); the message is shown to the user. */
final class SalesActionNotAllowed extends RuntimeException {}
