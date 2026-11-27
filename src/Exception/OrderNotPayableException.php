<?php

declare(strict_types=1);

namespace App\Exception;

use App\Enum\OrderStatus;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\Uid\Uuid;

#[WithHttpStatus(Response::HTTP_CONFLICT)]
final class OrderNotPayableException extends \RuntimeException
{
    public static function forStatus(Uuid $orderId, OrderStatus $status): self
    {
        return new self(\sprintf('Order %s cannot be paid: its status is %s.', $orderId->toRfc4122(), $status->value));
    }
}
