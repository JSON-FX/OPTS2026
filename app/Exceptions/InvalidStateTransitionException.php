<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Exception thrown when an invalid state transition is attempted.
 *
 * Story 3.7 - Transaction State Machine
 */
class InvalidStateTransitionException extends RuntimeException {}
