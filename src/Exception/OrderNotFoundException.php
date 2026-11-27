<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\Uid\Uuid;

#[WithHttpStatus(Response::HTTP_NOT_FOUND)]
final class OrderNotFoundException extends \RuntimeException
{
    public static function withId(Uuid $orderId): self
    {
        return new self(\sprintf('Order %s not found.', $orderId->toRfc4122()));
    }
}
