<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Dto\Error\ViolationResponseDto;
use App\Http\ProblemResponse;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Turns every exception into a JSON "problem details" response.
 *
 * Priority -64 runs after Symfony logs the exception (priority 0)
 * and before its own HTML error page (priority -128).
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -64)]
final readonly class JsonExceptionListener
{
    public function __construct(
        #[Autowire('%kernel.debug%')]
        private bool $debug,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $event->setResponse($this->createResponse($event->getThrowable()));
    }

    private function createResponse(\Throwable $exception): ProblemResponse
    {
        $violations = $this->findViolations($exception);
        if ($violations !== null) {
            return new ProblemResponse(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'The request contains invalid data.',
                $this->mapViolations($violations),
                title: 'Validation Failed',
            );
        }

        // Symfony HTTP exceptions: 400, 404, 415, 429, access denied, ...
        if ($exception instanceof HttpExceptionInterface) {
            return new ProblemResponse(
                $exception->getStatusCode(),
                $this->clientMessage($exception, $exception->getStatusCode()),
                headers: $exception->getHeaders(),
            );
        }

        // Domain exceptions declare their status with #[WithHttpStatus].
        $httpStatus = $this->findHttpStatus($exception);
        if ($httpStatus !== null) {
            return new ProblemResponse(
                $httpStatus->statusCode,
                $this->clientMessage($exception, $httpStatus->statusCode),
                headers: $httpStatus->headers,
            );
        }

        return new ProblemResponse(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $this->debug ? $exception::class.': '.$exception->getMessage() : 'An unexpected error occurred.',
        );
    }

    /**
     * 4xx messages are written for the client; 5xx messages may contain internals.
     */
    private function clientMessage(\Throwable $exception, int $status): string
    {
        if ($status >= 500 || $exception->getMessage() === '') {
            return Response::$statusTexts[$status] ?? 'Error';
        }

        return $exception->getMessage();
    }

    private function findHttpStatus(\Throwable $exception): ?WithHttpStatus
    {
        for ($class = new \ReflectionClass($exception); $class !== false; $class = $class->getParentClass()) {
            $attributes = $class->getAttributes(WithHttpStatus::class, \ReflectionAttribute::IS_INSTANCEOF);
            if ($attributes !== []) {
                return $attributes[0]->newInstance();
            }
        }

        return null;
    }

    private function findViolations(\Throwable $exception): ?ConstraintViolationListInterface
    {
        // #[MapRequestPayload] wraps validation errors in a 422 HttpException.
        $previous = $exception instanceof HttpExceptionInterface ? $exception->getPrevious() : $exception;

        return $previous instanceof ValidationFailedException ? $previous->getViolations() : null;
    }

    /**
     * @return list<ViolationResponseDto>
     */
    private function mapViolations(ConstraintViolationListInterface $list): array
    {
        $result = [];
        foreach ($list as $violation) {
            $result[] = new ViolationResponseDto($violation->getPropertyPath(), (string) $violation->getMessage());
        }

        return $result;
    }
}
