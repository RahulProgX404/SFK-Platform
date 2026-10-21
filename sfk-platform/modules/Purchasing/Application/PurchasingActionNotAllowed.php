<?php

declare(strict_types=1);

namespace Modules\Purchasing\Application;

use RuntimeException;

/** A purchasing action that cannot be done; the message is shown to the user as it is. */
final class PurchasingActionNotAllowed extends RuntimeException {}
