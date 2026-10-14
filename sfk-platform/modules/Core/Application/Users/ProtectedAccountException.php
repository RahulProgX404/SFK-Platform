<?php

declare(strict_types=1);

namespace Modules\Core\Application\Users;

use RuntimeException;

/** Thrown when user management tries to change an account it does not own (env super admin, oneself). */
final class ProtectedAccountException extends RuntimeException {}
