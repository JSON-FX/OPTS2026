<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class SSOUnavailableException extends RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, public readonly ?int $retryAfter = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function retryHeaders(): array
    {
        return ['Cache-Control' => 'no-store', ...($this->retryAfter === null ? [] : [
            'Retry-After' => (string) max(1, min(300, $this->retryAfter)),
        ])];
    }
}
