<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Provider;

use CorentinBoutillier\InvoiceBundle\DTO\UserData;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Default UserProvider implementation using Symfony Security component.
 *
 * Returns the currently authenticated user from Symfony Security, or null if:
 * - No user is authenticated (anonymous context)
 * - Security component is not available (CLI, test context)
 * - Security component is not installed
 *
 * This provider works automatically with any Symfony UserInterface implementation.
 * It extracts user data using common methods (getUserIdentifier, getEmail, etc.).
 *
 * Applications can override this by providing their own UserProviderInterface implementation
 * with custom logic for retrieving user information.
 */
class SecurityUserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly ?Security $security = null,
    ) {
    }

    public function getCurrentUser(): ?UserData
    {
        // Security component not available (optional dependency)
        if ($this->security === null) {
            return null;
        }

        $user = $this->security->getUser();

        // No authenticated user
        if ($user === null) {
            return null;
        }

        // Extract user identifier (required by UserInterface)
        $identifier = $user->getUserIdentifier();

        // Try to extract name (common method names)
        $name = null;
        if (method_exists($user, 'getFullName')) {
            $name = $user->getFullName();
        } elseif (method_exists($user, 'getName')) {
            $name = $user->getName();
        } elseif (method_exists($user, 'getUsername')) {
            $name = $user->getUsername();
        }

        // Try to extract email (common method names)
        $email = null;
        if (method_exists($user, 'getEmail')) {
            $email = $user->getEmail();
        }

        return new UserData(
            id: $identifier,
            name: $name ?? $identifier, // Fallback to identifier if no name
            email: $email,
        );
    }
}
