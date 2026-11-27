<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Order;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * A customer may view and pay only their own orders.
 *
 * @extends Voter<self::VIEW|self::PAY, Order>
 */
final class OrderVoter extends Voter
{
    public const string VIEW = 'ORDER_VIEW';
    public const string PAY = 'ORDER_PAY';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::PAY], true) && $subject instanceof Order;
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        $user = $token->getUser();
        if (!$user instanceof ApiUser) {
            $vote?->addReason('No authenticated API user.');

            return false;
        }

        if (!$subject->isOwnedBy($user->getId())) {
            $vote?->addReason('The order belongs to another customer.');

            return false;
        }

        return true;
    }
}
