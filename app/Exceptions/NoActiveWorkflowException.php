<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Exception thrown when no active workflow is found for a transaction category.
 *
 * Story 3.11 - Workflow Assignment on Transaction Creation
 */
class NoActiveWorkflowException extends RuntimeException {}
