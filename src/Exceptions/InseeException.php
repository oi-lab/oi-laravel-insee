<?php

namespace OiLab\OiLaravelInsee\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Common parent of the exceptions thrown by the typed search methods.
 */
class InseeException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $statusCode = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }
}
