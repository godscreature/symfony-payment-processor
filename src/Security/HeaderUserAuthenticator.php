<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\ProblemResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Simulated API token: the caller sends its user id (UUID) in X-User-Id.
 *
 * No header: the request stays anonymous, and an endpoint that needs a user answers 401
 * through start(). An invalid header: 401 from onAuthenticationFailure().
 */
final class HeaderUserAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public const string HEADER = 'X-User-Id';

    public function supports(Request $request): bool
    {
        return $request->headers->has(self::HEADER);
    }

    public function authenticate(Request $request): Passport
    {
        $userId = (string) $request->headers->get(self::HEADER);
        if (!Uuid::isValid($userId)) {
            throw new CustomUserMessageAuthenticationException(self::HEADER.' must be a valid UUID.');
        }

        return new SelfValidatingPassport(
            new UserBadge($userId, static fn (string $id): ApiUser => new ApiUser(Uuid::fromString($id))),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Continue to the controller.
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new ProblemResponse(
            Response::HTTP_UNAUTHORIZED,
            strtr($exception->getMessageKey(), $exception->getMessageData()),
        );
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new ProblemResponse(
            Response::HTTP_UNAUTHORIZED,
            \sprintf('Authentication required: send your user id in the %s header.', self::HEADER),
        );
    }
}
