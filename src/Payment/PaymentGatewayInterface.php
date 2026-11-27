<?php

declare(strict_types=1);

namespace App\Payment;

use App\Entity\Order;
use App\Enum\Currency;
use App\Exception\PaymentFailedException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Strategy: one implementation per payment provider. Every implementation is tagged
 * automatically and collected by PaymentProcessor.
 */
#[AutoconfigureTag(self::TAG)]
interface PaymentGatewayInterface
{
    public const string TAG = 'app.payment_gateway';

    public function supports(Currency $currency): bool;

    /**
     * @throws PaymentFailedException when the provider declines the charge
     */
    public function charge(Order $order): PaymentResultDto;
}
