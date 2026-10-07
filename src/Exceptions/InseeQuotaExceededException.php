<?php

namespace OiLab\OiLaravelInsee\Exceptions;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * The hourly or per-minute quota is used up (HTTP 429, exhausted quota headers
 * or the local limiter). Nothing should be retried before `$retryAt`.
 */
class InseeQuotaExceededException extends InseeException
{
    public function __construct(string $message, public readonly CarbonImmutable $retryAt, ?int $statusCode = 429, ?Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
    }
}
