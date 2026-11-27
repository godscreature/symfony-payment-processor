<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

/**
 * The Idempotency-Key was already used for a different request (another order or another user).
 */
#[WithHttpStatus(Response::HTTP_UNPROCESSABLE_ENTITY)]
final class IdempotencyKeyReusedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('This Idempotency-Key was already used for a different request.');
    }
}
