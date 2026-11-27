<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\IdempotencyKeyInProgressException;
use App\Exception\IdempotencyKeyReusedException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Runs an operation at most once per Idempotency-Key, using Redis key "idempotency:{key}".
 *
 * IN_PROGRESS (30 s): the first request is running; a parallel duplicate gets 409.
 *   The short TTL frees the key if the PHP process dies mid-request.
 * COMPLETED (24 h): the stored status and body are replayed without running the operation again.
 */
final readonly class IdempotencyService
{
    public const int IN_PROGRESS_TTL = 30;
    public const int COMPLETED_TTL = 86_400;

    private const string KEY_PREFIX = 'idempotency:';
    private const string IN_PROGRESS = 'IN_PROGRESS';
    private const string COMPLETED = 'COMPLETED';

    /** Deletes the key only while it still holds our IN_PROGRESS token. */
    private const string RELEASE_SCRIPT = <<<'LUA'
        local value = redis.call('GET', KEYS[1])
        if value and cjson.decode(value).token == ARGV[1] then
            return redis.call('DEL', KEYS[1])
        end
        return 0
        LUA;

    public function __construct(
        #[Autowire(service: 'app.redis')]
        private \Redis $redis,
    ) {
    }

    /**
     * @param string                         $scope     the request the key belongs to (user + order);
     *                                                  the same key with another scope is rejected
     * @param callable(): IdempotentResponse $operation runs only for the first request with this key
     *
     * @throws IdempotencyKeyInProgressException
     * @throws IdempotencyKeyReusedException
     */
    public function execute(string $key, string $scope, callable $operation): IdempotentResponse
    {
        $redisKey = self::KEY_PREFIX.$key;
        $token = bin2hex(random_bytes(16));

        // SET NX is atomic: of two parallel requests exactly one acquires the key.
        $acquired = $this->redis->set(
            $redisKey,
            $this->encode(['state' => self::IN_PROGRESS, 'scope' => $scope, 'token' => $token]),
            ['nx', 'ex' => self::IN_PROGRESS_TTL],
        );

        if ($acquired !== true) {
            return $this->replay($redisKey, $scope);
        }

        try {
            $response = $operation();
        } catch (\Throwable $exception) {
            // Failed requests are not stored, so the client may retry with the same key.
            $this->redis->eval(self::RELEASE_SCRIPT, [$redisKey, $token], 1);

            throw $exception;
        }

        $this->redis->setex($redisKey, self::COMPLETED_TTL, $this->encode([
            'state' => self::COMPLETED,
            'scope' => $scope,
            'status' => $response->status,
            'body' => $response->body,
        ]));

        return $response;
    }

    private function replay(string $redisKey, string $scope): IdempotentResponse
    {
        $raw = $this->redis->get($redisKey);
        if (!\is_string($raw)) {
            // The IN_PROGRESS marker expired between our SET and GET; let the client retry.
            throw new IdempotencyKeyInProgressException();
        }

        $record = json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);
        if (!\is_array($record) || ($record['scope'] ?? null) !== $scope) {
            throw new IdempotencyKeyReusedException();
        }

        if (($record['state'] ?? null) !== self::COMPLETED) {
            throw new IdempotencyKeyInProgressException();
        }

        return new IdempotentResponse((int) $record['status'], (string) $record['body'], replayed: true);
    }

    /**
     * @param array<string, string|int> $record
     */
    private function encode(array $record): string
    {
        return json_encode($record, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }
}
