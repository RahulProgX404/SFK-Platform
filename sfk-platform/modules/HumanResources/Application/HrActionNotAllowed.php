<?php

declare(strict_types=1);

namespace Modules\HumanResources\Application;

use RuntimeException;

/** An HR action that cannot be done; the message is shown to the user as it is. */
final class HrActionNotAllowed extends RuntimeException {}
