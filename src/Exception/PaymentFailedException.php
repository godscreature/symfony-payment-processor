<?php

declare(strict_types=1);

namespace App\Exception;

use App\Enum\PaymentGateway;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(Response::HTTP_PAYMENT_REQUIRED)]
final class PaymentFailedException extends \RuntimeException
{
    public static function declined(PaymentGateway $gateway, string $providerStatus): self
    {
        return new self(\sprintf('Payment was declined by %s (status "%s").', $gateway->value, $providerStatus));
    }
}
