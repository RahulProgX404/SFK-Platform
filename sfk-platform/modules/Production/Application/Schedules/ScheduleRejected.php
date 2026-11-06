<?php

declare(strict_types=1);

namespace Modules\Production\Application\Schedules;

use RuntimeException;

/** A change of the 製袋予定表 that is not saved; the message is shown to the user as it is. */
final class ScheduleRejected extends RuntimeException {}
