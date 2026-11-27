<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

/**
 * Another request with the same Idempotency-Key is still being processed.
 */
#[WithHttpStatus(Response::HTTP_CONFLICT)]
final class IdempotencyKeyInProgressException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('A request with this Idempotency-Key is already being processed. Retry later.');
    }
}
