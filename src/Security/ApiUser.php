<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The caller identified by the X-User-Id header. Its id is the customerId of the orders it owns.
 */
final readonly class ApiUser implements UserInterface
{
    public function __construct(
        private Uuid $id,
    ) {
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->id->toRfc4122();
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    /**
     * No credentials are stored. The attribute tells Symfony 7.3+ not to call this method.
     */
    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }
}
