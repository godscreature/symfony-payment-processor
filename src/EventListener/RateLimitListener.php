<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Attribute\RateLimit;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimit as LimitState;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Enforces #[RateLimit] on controllers and reports the limit state
 * in X-RateLimit-Limit / X-RateLimit-Remaining on every response of a limited route,
 * including 422 and 429 error responses.
 */
#[AsEventListener(event: KernelEvents::CONTROLLER, method: 'onController')]
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onResponse')]
final readonly class RateLimitListener
{
    private const string REQUEST_ATTRIBUTE = '_rate_limit';

    public function __construct(
        #[AutowireLocator('rate_limiter', indexAttribute: 'name')]
        private ContainerInterface $limiters,
    ) {
    }

    public function onController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        foreach ($event->getAttributes(RateLimit::class) as $attribute) {
            $factory = $this->limiters->get($attribute->limiter);
            if (!$factory instanceof RateLimiterFactoryInterface) {
                throw new \LogicException(\sprintf('Rate limiter "%s" is not configured.', $attribute->limiter));
            }

            $limit = $factory->create($request->getClientIp() ?? 'unknown')->consume();

            // With several limiters on one route, report the one closest to running out.
            $current = $request->attributes->get(self::REQUEST_ATTRIBUTE);
            if (!$current instanceof LimitState || $limit->getRemainingTokens() < $current->getRemainingTokens()) {
                $request->attributes->set(self::REQUEST_ATTRIBUTE, $limit);
            }

            if (!$limit->isAccepted()) {
                $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());

                throw new TooManyRequestsHttpException($retryAfter, 'Too many requests. Please retry later.');
            }
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $limit = $event->getRequest()->attributes->get(self::REQUEST_ATTRIBUTE);
        if (!$limit instanceof LimitState) {
            return;
        }

        $event->getResponse()->headers->add([
            'X-RateLimit-Limit' => (string) $limit->getLimit(),
            'X-RateLimit-Remaining' => (string) $limit->getRemainingTokens(),
        ]);
    }
}
