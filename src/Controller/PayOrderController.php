<?php

declare(strict_types=1);

namespace App\Controller;

use App\Attribute\RateLimit;
use App\Dto\Error\ErrorResponseDto;
use App\Dto\Order\OrderResponseDto;
use App\Exception\OrderNotFoundException;
use App\Repository\OrderRepositoryInterface;
use App\Security\ApiUser;
use App\Security\OrderVoter;
use App\Service\IdempotencyService;
use App\Service\IdempotentResponse;
use App\Service\OrderService;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[OA\Tag(name: 'Orders')]
final class PayOrderController extends AbstractController
{
    private const string IDEMPOTENCY_HEADER = 'Idempotency-Key';

    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly OrderService $orderService,
        private readonly IdempotencyService $idempotency,
    ) {
    }

    #[Route(
        '/api/v1/orders/{id}/pay',
        name: 'api_orders_pay',
        requirements: ['id' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[RateLimit('pay_order')]
    #[IsGranted('IS_AUTHENTICATED')]
    #[Security(name: 'UserId')]
    #[OA\Post(
        description: 'Charges the order through the gateway for its currency (Monobank: UAH, Stripe: USD/EUR). '
            .'Repeating the request with the same Idempotency-Key returns the first result without charging again. '
            .'Every response carries X-RateLimit-Limit and X-RateLimit-Remaining '
            .'(10 requests per minute per client IP).',
    )]
    #[OA\Parameter(
        name: self::IDEMPOTENCY_HEADER,
        description: 'Unique UUID per payment attempt. Reuse it only to retry the same payment.',
        in: 'header',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'uuid'),
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Order paid (or the stored result of an earlier request with the same key)',
        headers: [
            new OA\Header(ref: '#/components/headers/Idempotent-Replayed', header: 'Idempotent-Replayed'),
            new OA\Header(ref: '#/components/headers/X-RateLimit-Limit', header: 'X-RateLimit-Limit'),
            new OA\Header(ref: '#/components/headers/X-RateLimit-Remaining', header: 'X-RateLimit-Remaining'),
        ],
        content: new OA\JsonContent(ref: new Model(type: OrderResponseDto::class)),
    )]
    #[OA\Response(
        response: Response::HTTP_BAD_REQUEST,
        description: 'Idempotency-Key header is missing or not a UUID',
        content: new OA\JsonContent(
            ref: new Model(type: ErrorResponseDto::class),
            example: [
                'status' => 400,
                'title' => 'Bad Request',
                'detail' => 'Idempotency-Key header must be a valid UUID.',
                'violations' => [],
            ],
        ),
    )]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: Response::HTTP_UNAUTHORIZED)]
    #[OA\Response(
        response: Response::HTTP_PAYMENT_REQUIRED,
        description: 'The payment provider declined the charge',
        content: new OA\JsonContent(
            ref: new Model(type: ErrorResponseDto::class),
            example: [
                'status' => 402,
                'title' => 'Payment Required',
                'detail' => 'Payment was declined by stripe (status "card_declined").',
                'violations' => [],
            ],
        ),
    )]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: Response::HTTP_FORBIDDEN)]
    #[OA\Response(ref: '#/components/responses/NotFound', response: Response::HTTP_NOT_FOUND)]
    #[OA\Response(
        response: Response::HTTP_CONFLICT,
        description: 'The order is already paid or cancelled, or a request with this Idempotency-Key is still running',
        content: new OA\JsonContent(
            ref: new Model(type: ErrorResponseDto::class),
            example: [
                'status' => 409,
                'title' => 'Conflict',
                'detail' => 'Order 0190f7d2-1a2b-7c3d-8e4f-5a6b7c8d9e0f cannot be paid: its status is PAID.',
                'violations' => [],
            ],
        ),
    )]
    #[OA\Response(
        response: Response::HTTP_UNPROCESSABLE_ENTITY,
        description: 'This Idempotency-Key was already used for another order or user',
        content: new OA\JsonContent(
            ref: new Model(type: ErrorResponseDto::class),
            example: [
                'status' => 422,
                'title' => 'Unprocessable Content',
                'detail' => 'This Idempotency-Key was already used for a different request.',
                'violations' => [],
            ],
        ),
    )]
    #[OA\Response(
        response: Response::HTTP_TOO_MANY_REQUESTS,
        description: 'Rate limit exceeded (10 requests per minute per client IP)',
        headers: [
            new OA\Header(ref: '#/components/headers/Retry-After', header: 'Retry-After'),
            new OA\Header(ref: '#/components/headers/X-RateLimit-Limit', header: 'X-RateLimit-Limit'),
            new OA\Header(ref: '#/components/headers/X-RateLimit-Remaining', header: 'X-RateLimit-Remaining'),
        ],
        content: new OA\JsonContent(
            ref: new Model(type: ErrorResponseDto::class),
            example: [
                'status' => 429,
                'title' => 'Too Many Requests',
                'detail' => 'Too many requests. Please retry later.',
                'violations' => [],
            ],
        ),
    )]
    public function __invoke(Uuid $id, Request $request, #[CurrentUser] ApiUser $user): JsonResponse
    {
        $idempotencyKey = (string) $request->headers->get(self::IDEMPOTENCY_HEADER);
        if (!Uuid::isValid($idempotencyKey)) {
            throw new BadRequestHttpException(self::IDEMPOTENCY_HEADER.' header must be a valid UUID.');
        }

        $order = $this->orders->findById($id) ?? throw OrderNotFoundException::withId($id);
        $this->denyAccessUnlessGranted(OrderVoter::PAY, $order);

        $result = $this->idempotency->execute(
            $idempotencyKey,
            $user->getUserIdentifier().':'.$id->toRfc4122(),
            function () use ($id): IdempotentResponse {
                $paidOrder = OrderResponseDto::fromEntity($this->orderService->pay($id));
                $body = json_encode($paidOrder, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);

                return new IdempotentResponse(Response::HTTP_OK, $body);
            },
        );

        return new JsonResponse(
            $result->body,
            $result->status,
            ['Idempotent-Replayed' => $result->replayed ? 'true' : 'false'],
            json: true,
        );
    }
}
