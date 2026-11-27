<?php

declare(strict_types=1);

namespace App\Http;

use App\Dto\Error\ErrorResponseDto;
use App\Dto\Error\ViolationResponseDto;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The single place that renders API errors as application/problem+json,
 * used by the exception listener and by the authenticator.
 */
final class ProblemResponse extends JsonResponse
{
    /**
     * @param list<ViolationResponseDto>         $violations
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(
        int $status,
        string $detail,
        array $violations = [],
        array $headers = [],
        ?string $title = null,
    ) {
        parent::__construct(null, $status, $headers);

        $title ??= Response::$statusTexts[$status] ?? 'Error';

        $this->setEncodingOptions(\JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
        $this->setData(new ErrorResponseDto($status, $title, $detail, $violations));
        $this->headers->set('Content-Type', 'application/problem+json');
    }
}
