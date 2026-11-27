<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Order;
use App\Enum\Currency;
use App\Security\ApiUser;
use App\Security\OrderVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Uid\Uuid;

final class OrderVoterTest extends TestCase
{
    private OrderVoter $voter;
    private Uuid $ownerId;
    private Order $order;

    protected function setUp(): void
    {
        $this->voter = new OrderVoter();
        $this->ownerId = Uuid::v7();
        $this->order = new Order($this->ownerId, Currency::UAH);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function attributes(): iterable
    {
        yield 'view' => [OrderVoter::VIEW];
        yield 'pay' => [OrderVoter::PAY];
    }

    #[DataProvider('attributes')]
    public function testOwnerIsGranted(string $attribute): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->tokenFor($this->ownerId), $attribute));
    }

    #[DataProvider('attributes')]
    public function testOtherCustomerIsDenied(string $attribute): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->tokenFor(Uuid::v7()), $attribute));
    }

    #[DataProvider('attributes')]
    public function testAnonymousIsDenied(string $attribute): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new NullToken(), $attribute));
    }

    public function testAbstainsOnOtherAttributes(): void
    {
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($this->tokenFor($this->ownerId), 'ORDER_DELETE'));
    }

    public function testAbstainsOnOtherSubjects(): void
    {
        $vote = $this->voter->vote($this->tokenFor($this->ownerId), new \stdClass(), [OrderVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $vote);
    }

    private function vote(TokenInterface $token, string $attribute): int
    {
        return $this->voter->vote($token, $this->order, [$attribute]);
    }

    private function tokenFor(Uuid $userId): TokenInterface
    {
        $user = new ApiUser($userId);

        return new UsernamePasswordToken($user, 'api', $user->getRoles());
    }
}
