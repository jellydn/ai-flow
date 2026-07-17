<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Expected, user-actionable run failures (bad input, missing/private repo, etc.).
 * Safe to show to the user and should not be reported to Sentry as product bugs.
 */
class UserFacingRunException extends RuntimeException {}
