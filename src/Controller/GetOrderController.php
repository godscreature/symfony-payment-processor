<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Order\OrderResponseDto;
use App\Exception\OrderNotFoundException;
use App\Repository\OrderRepositoryInterface;
use App\Security\OrderVoter;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[OA\Tag(name: 'Orders')]
final class GetOrderController extends AbstractController
{
    public function __construct(
        // CachedOrderRepository: served from Redis after the first read.
        private readonly OrderRepositoryInterface $orders,
    ) {
    }

    #[Route('/api/v1/orders/{id}', name: 'api_orders_get', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED')]
    #[Security(name: 'UserId')]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Order details',
        content: new OA\JsonContent(ref: new Model(type: OrderResponseDto::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: Response::HTTP_UNAUTHORIZED)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: Response::HTTP_FORBIDDEN)]
    #[OA\Response(ref: '#/components/responses/NotFound', response: Response::HTTP_NOT_FOUND)]
    public function __invoke(Uuid $id): JsonResponse
    {
        $order = $this->orders->findById($id) ?? throw OrderNotFoundException::withId($id);
        $this->denyAccessUnlessGranted(OrderVoter::VIEW, $order);

        return $this->json(OrderResponseDto::fromEntity($order));
    }
}
