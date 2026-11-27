<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Dto\Order\CreateOrderRequestDto;
use App\Dto\Order\OrderItemRequestDto;
use App\Enum\Currency;
use App\Service\OrderService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

abstract class ApiTestCase extends WebTestCase
{
    protected const string OWNER_ID = '0190f7d2-1a2b-7c3d-8e4f-5a6b7c8d9e0f';
    protected const string OTHER_USER_ID = '0190f7d2-9999-7c3d-8e4f-5a6b7c8d9e0f';
    protected const string PRODUCT_ID = '0190f7d2-7c4e-7a3b-9f1e-2d4c5b6a7e8f';
    /** 2 × 1999 */
    protected const int ORDER_TOTAL = 3998;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        // Limiter state lives in Redis and survives between tests, so start each test from zero.
        self::getContainer()->get('limiter.create_order')->create('127.0.0.1')->reset();
        self::getContainer()->get('limiter.pay_order')->create('127.0.0.1')->reset();
    }

    protected function createOrder(Currency $currency = Currency::UAH, string $customerId = self::OWNER_ID): Uuid
    {
        $items = [new OrderItemRequestDto(self::PRODUCT_ID, 2, 1999)];
        $request = new CreateOrderRequestDto($customerId, $currency->value, $items);

        return self::getContainer()->get(OrderService::class)->create($request)->getId();
    }

    /**
     * @param array<string, string> $headers header name => value
     */
    protected function request(string $method, string $uri, array $headers = []): void
    {
        $server = [];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $this->client->request($method, $uri, server: $server);
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseData(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        /* @var array<string, mixed> $data */
        return $data;
    }
}
