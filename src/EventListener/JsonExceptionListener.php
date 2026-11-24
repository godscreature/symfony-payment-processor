<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Dto\Error\ErrorResponse;
use App\Dto\Error\ViolationResponse;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
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
        $exception = $event->getThrowable();
        $headers = [];
        $violations = [];
        $list = $this->findViolations($exception);

        if ($list !== null) {
            $status = Response::HTTP_UNPROCESSABLE_ENTITY;
            $title = 'Validation Failed';
            $detail = 'The request contains invalid data.';
            $violations = $this->mapViolations($list);
        } elseif ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $headers = $exception->getHeaders();
            $title = Response::$statusTexts[$status] ?? 'Error';
            // 5xx messages may contain internals; 4xx messages are written for the client.
            $detail = $status < 500 && $exception->getMessage() !== '' ? $exception->getMessage() : $title;
        } else {
            $status = Response::HTTP_INTERNAL_SERVER_ERROR;
            $title = Response::$statusTexts[$status];
            $detail = $this->debug ? $exception::class.': '.$exception->getMessage() : 'An unexpected error occurred.';
        }

        $response = new JsonResponse(null, $status, $headers);
        $response->setEncodingOptions(\JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
        $response->setData(new ErrorResponse($status, $title, $detail, $violations));
        $response->headers->set('Content-Type', 'application/problem+json');

        $event->setResponse($response);
    }

    private function findViolations(\Throwable $exception): ?ConstraintViolationListInterface
    {
        // #[MapRequestPayload] wraps validation errors in a 422 HttpException.
        $previous = $exception instanceof HttpExceptionInterface ? $exception->getPrevious() : $exception;

        return $previous instanceof ValidationFailedException ? $previous->getViolations() : null;
    }

    /**
     * @return list<ViolationResponse>
     */
    private function mapViolations(ConstraintViolationListInterface $list): array
    {
        $result = [];
        foreach ($list as $violation) {
            $result[] = new ViolationResponse($violation->getPropertyPath(), (string) $violation->getMessage());
        }

        return $result;
    }
}
