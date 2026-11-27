<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Error\ErrorResponseDto;
use App\Dto\Order\ListOrdersQueryDto;
use App\Dto\Order\OrderListResponseDto;
use App\Enum\OrderStatus;
use App\Repository\OrderRepositoryInterface;
use App\Security\ApiUser;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Orders')]
final class ListOrdersController extends AbstractController
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
    ) {
    }

    #[Route('/api/v1/orders', name: 'api_orders_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED')]
    #[Security(name: 'UserId')]
    #[OA\Get(
        description: 'Orders of the current user (X-User-Id), newest first. '
            .'Line items are not included; get them with GET /api/v1/orders/{id}.',
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'One page of orders',
        content: new OA\JsonContent(ref: new Model(type: OrderListResponseDto::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: Response::HTTP_UNAUTHORIZED)]
    #[OA\Response(
        response: Response::HTTP_UNPROCESSABLE_ENTITY,
        description: 'Invalid query parameters',
        content: new OA\JsonContent(
            ref: new Model(type: ErrorResponseDto::class),
            example: [
                'status' => 422,
                'title' => 'Validation Failed',
                'detail' => 'The request contains invalid data.',
                'violations' => [
                    ['propertyPath' => 'limit', 'message' => 'This value should be between 1 and 100.'],
                ],
            ],
        ),
    )]
    public function __invoke(
        #[CurrentUser] ApiUser $user,
        #[MapQueryString] ListOrdersQueryDto $query = new ListOrdersQueryDto(),
    ): JsonResponse {
        $status = $query->status === null ? null : OrderStatus::from($query->status);

        $orders = $this->orders->findByCustomer($user->getId(), $status, $query->limit, $query->offset());
        $total = $this->orders->countByCustomer($user->getId(), $status);

        return $this->json(OrderListResponseDto::fromEntities($orders, $total, $query));
    }
}
