<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class CreateOrderControllerTest extends WebTestCase
{
    private const string URL = '/api/v1/orders';
    private const string CUSTOMER_ID = '0190f7d2-1a2b-7c3d-8e4f-5a6b7c8d9e0f';
    private const string PRODUCT_A = '0190f7d2-7c4e-7a3b-9f1e-2d4c5b6a7e8f';
    private const string PRODUCT_B = '0190f7d2-7c4e-7a3b-9f1e-2d4c5b6a7e90';

    /** Test client address; TRUSTED_PROXIES in .env.test treats it as the load balancer. */
    private const string TRUSTED_PROXY_IP = '127.0.0.1';
    private const string CLIENT_A_IP = '198.51.100.1';
    private const string CLIENT_B_IP = '198.51.100.2';
    private const string UNTRUSTED_IP = '203.0.113.7';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        // The limiter state lives in Redis and survives between tests, so start each test from zero.
        $limiter = self::getContainer()->get('limiter.create_order');
        foreach ([self::TRUSTED_PROXY_IP, self::CLIENT_A_IP, self::CLIENT_B_IP, self::UNTRUSTED_IP] as $ip) {
            $limiter->create($ip)->reset();
        }
    }

    public function testCreatesOrder(): void
    {
        $this->post([
            'customerId' => self::CUSTOMER_ID,
            'currency' => 'UAH',
            'items' => [
                ['productId' => self::PRODUCT_A, 'quantity' => 2, 'unitPrice' => 1999],
                ['productId' => self::PRODUCT_B, 'quantity' => 1, 'unitPrice' => 500],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('X-RateLimit-Limit', '20');
        self::assertResponseHeaderSame('X-RateLimit-Remaining', '19');
        $data = $this->responseData();

        self::assertTrue(Uuid::isValid((string) $data['id']));
        self::assertSame(self::CUSTOMER_ID, $data['customerId']);
        self::assertSame('NEW', $data['status']);
        self::assertSame('UAH', $data['currency']);
        self::assertSame(2 * 1999 + 500, $data['totalAmount']);
        self::assertIsString($data['createdAt']);
        self::assertCount(2, $data['items']);

        // The order and its items are really in the database.
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $order = $entityManager->find(Order::class, Uuid::fromString((string) $data['id']));
        self::assertNotNull($order);
        self::assertSame(OrderStatus::NEW, $order->getStatus());
        self::assertSame(4498, $order->getTotalAmount());
        self::assertSame(1, $order->getVersion());
        self::assertCount(2, $order->getItems());
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string>         $expectedPaths
     */
    #[DataProvider('invalidPayloads')]
    public function testRejectsInvalidPayload(array $payload, array $expectedPaths): void
    {
        $this->post($payload);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');

        $data = $this->responseData();
        self::assertSame(422, $data['status']);
        self::assertIsArray($data['violations']);

        $paths = array_column($data['violations'], 'propertyPath');
        foreach ($expectedPaths as $path) {
            self::assertContains($path, $paths);
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>}>
     */
    public static function invalidPayloads(): iterable
    {
        $validItem = ['productId' => self::PRODUCT_A, 'quantity' => 1, 'unitPrice' => 100];

        yield 'invalid customer UUID' => [
            ['customerId' => 'not-a-uuid', 'currency' => 'UAH', 'items' => [$validItem]],
            ['customerId'],
        ];
        yield 'unsupported currency' => [
            ['customerId' => self::CUSTOMER_ID, 'currency' => 'GBP', 'items' => [$validItem]],
            ['currency'],
        ];
        yield 'empty items' => [
            ['customerId' => self::CUSTOMER_ID, 'currency' => 'USD', 'items' => []],
            ['items'],
        ];
        yield 'non-positive quantity and price, bad product UUID' => [
            ['customerId' => self::CUSTOMER_ID, 'currency' => 'EUR', 'items' => [
                ['productId' => 'x', 'quantity' => 0, 'unitPrice' => -5],
            ]],
            ['items[0].productId', 'items[0].quantity', 'items[0].unitPrice'],
        ];
        yield 'quantity above the limit' => [
            ['customerId' => self::CUSTOMER_ID, 'currency' => 'EUR', 'items' => [
                ['productId' => self::PRODUCT_A, 'quantity' => 10_001, 'unitPrice' => 1],
            ]],
            ['items[0].quantity'],
        ];
        yield 'wrong field type' => [
            ['customerId' => self::CUSTOMER_ID, 'currency' => 'EUR', 'items' => [
                ['productId' => self::PRODUCT_A, 'quantity' => 'two', 'unitPrice' => 1],
            ]],
            ['items[0].quantity'],
        ];
    }

    public function testRateLimiterReturns429AfterTwentyRequests(): void
    {
        // Invalid requests count too: the limit is checked before the body is parsed.
        for ($i = 1; $i <= 20; ++$i) {
            $this->post([]);
            self::assertResponseStatusCodeSame(422, \sprintf('Request #%d should not be rate limited.', $i));
            // Every response reports how many requests are left in the window.
            self::assertResponseHeaderSame('X-RateLimit-Limit', '20');
            self::assertResponseHeaderSame('X-RateLimit-Remaining', (string) (20 - $i));
        }

        $this->post([]);

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');
        self::assertResponseHeaderSame('X-RateLimit-Limit', '20');
        self::assertResponseHeaderSame('X-RateLimit-Remaining', '0');
        self::assertSame(429, $this->responseData()['status']);
    }

    public function testBehindTrustedProxyLimitsByRealClientIp(): void
    {
        // The proxy appends the real client IP; whatever the client put on the left is ignored.
        for ($i = 1; $i <= 20; ++$i) {
            $this->post([], forwardedFor: \sprintf('5.5.5.%d, %s', $i, self::CLIENT_A_IP));
            self::assertResponseStatusCodeSame(422);
        }

        $this->post([], forwardedFor: '6.6.6.6, '.self::CLIENT_A_IP);
        self::assertResponseStatusCodeSame(429);

        // Another client behind the same proxy has its own limit.
        $this->post([], forwardedFor: self::CLIENT_B_IP);
        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('X-RateLimit-Remaining', '19');
    }

    public function testIgnoresForwardedForFromUntrustedAddress(): void
    {
        // A client connecting directly cannot dodge the limit by faking X-Forwarded-For.
        for ($i = 1; $i <= 20; ++$i) {
            $this->post([], remoteAddr: self::UNTRUSTED_IP, forwardedFor: \sprintf('5.5.5.%d', $i));
            self::assertResponseStatusCodeSame(422);
        }

        $this->post([], remoteAddr: self::UNTRUSTED_IP, forwardedFor: '6.6.6.6');
        self::assertResponseStatusCodeSame(429);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function post(
        array $payload,
        string $remoteAddr = self::TRUSTED_PROXY_IP,
        ?string $forwardedFor = null,
    ): void {
        $server = ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => $remoteAddr];
        if ($forwardedFor !== null) {
            $server['HTTP_X_FORWARDED_FOR'] = $forwardedFor;
        }

        $this->client->request(
            'POST',
            self::URL,
            server: $server,
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function responseData(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        /* @var array<string, mixed> $data */
        return $data;
    }
}
