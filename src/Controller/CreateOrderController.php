<?php

declare(strict_types=1);

namespace App\Controller;

use App\Attribute\RateLimit;
use App\Dto\Error\ErrorResponse;
use App\Dto\Order\CreateOrderRequest;
use App\Dto\Order\OrderResponse;
use App\Service\OrderService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Orders')]
final class CreateOrderController extends AbstractController
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    #[Route('/api/v1/orders', name: 'api_orders_create', methods: ['POST'])]
    #[RateLimit('create_order')]
    #[OA\Response(
        response: Response::HTTP_CREATED,
        description: 'Order created',
        headers: [
            new OA\Header(ref: '#/components/headers/X-RateLimit-Limit', header: 'X-RateLimit-Limit'),
            new OA\Header(ref: '#/components/headers/X-RateLimit-Remaining', header: 'X-RateLimit-Remaining'),
        ],
        content: new OA\JsonContent(ref: new Model(type: OrderResponse::class)),
    )]
    #[OA\Response(
        response: Response::HTTP_BAD_REQUEST,
        description: 'Malformed JSON body',
        headers: [
            new OA\Header(ref: '#/components/headers/X-RateLimit-Limit', header: 'X-RateLimit-Limit'),
            new OA\Header(ref: '#/components/headers/X-RateLimit-Remaining', header: 'X-RateLimit-Remaining'),
        ],
        content: new OA\JsonContent(
            ref: new Model(type: ErrorResponse::class),
            example: ['status' => 400, 'title' => 'Bad Request', 'detail' => 'Request payload contains invalid "json" data.', 'violations' => []],
        ),
    )]
    #[OA\Response(
        response: Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
        description: 'Request body is not JSON (Content-Type must be application/json)',
        headers: [
            new OA\Header(ref: '#/components/headers/X-RateLimit-Limit', header: 'X-RateLimit-Limit'),
            new OA\Header(ref: '#/components/headers/X-RateLimit-Remaining', header: 'X-RateLimit-Remaining'),
        ],
        content: new OA\JsonContent(
            ref: new Model(type: ErrorResponse::class),
            example: ['status' => 415, 'title' => 'Unsupported Media Type', 'detail' => 'Unsupported format, expects "json", but "txt" given.', 'violations' => []],
        ),
    )]
    #[OA\Response(
        response: Response::HTTP_UNPROCESSABLE_ENTITY,
        description: 'Validation failed',
        headers: [
            new OA\Header(ref: '#/components/headers/X-RateLimit-Limit', header: 'X-RateLimit-Limit'),
            new OA\Header(ref: '#/components/headers/X-RateLimit-Remaining', header: 'X-RateLimit-Remaining'),
        ],
        content: new OA\JsonContent(
            ref: new Model(type: ErrorResponse::class),
            example: ['status' => 422, 'title' => 'Validation Failed', 'detail' => 'The request contains invalid data.', 'violations' => [
                ['propertyPath' => 'customerId', 'message' => 'This is not a valid UUID.'],
                ['propertyPath' => 'items[0].quantity', 'message' => 'This value should be positive.'],
            ]],
        ),
    )]
    #[OA\Response(
        response: Response::HTTP_TOO_MANY_REQUESTS,
        description: 'Rate limit exceeded (20 requests per minute per client IP)',
        headers: [
            new OA\Header(ref: '#/components/headers/Retry-After', header: 'Retry-After'),
            new OA\Header(ref: '#/components/headers/X-RateLimit-Limit', header: 'X-RateLimit-Limit'),
            new OA\Header(ref: '#/components/headers/X-RateLimit-Remaining', header: 'X-RateLimit-Remaining'),
        ],
        content: new OA\JsonContent(
            ref: new Model(type: ErrorResponse::class),
            example: ['status' => 429, 'title' => 'Too Many Requests', 'detail' => 'Too many requests. Please retry later.', 'violations' => []],
        ),
    )]
    public function __invoke(#[MapRequestPayload(acceptFormat: 'json')] CreateOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->create($request);

        return $this->json(OrderResponse::fromEntity($order), Response::HTTP_CREATED);
    }
}
