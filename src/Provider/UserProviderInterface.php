<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Provider;

use CorentinBoutillier\InvoiceBundle\DTO\UserData;

/**
 * Provides current authenticated user information for audit trail.
 *
 * This interface MUST be implemented by the application to integrate with
 * the application's authentication system (e.g., Symfony Security, custom auth).
 *
 * The bundle does NOT provide a default implementation as user management
 * is application-specific.
 *
 * Usage:
 * - Called by InvoiceHistorySubscriber to record who performed an action
 * - Should return UserData with current user's id, name, and email
 * - Return null in contexts without authentication (CLI commands, cron jobs)
 *
 * Example implementation with Symfony Security:
 *
 * <code>
 * class SecurityUserProvider implements UserProviderInterface
 * {
 *     public function __construct(private Security $security) {}
 *
 *     public function getCurrentUser(): ?UserData
 *     {
 *         $user = $this->security->getUser();
 *         if (!$user instanceof YourUserEntity) {
 *             return null;
 *         }
 *
 *         return new UserData(
 *             id: (string) $user->getId(),
 *             name: $user->getFullName(),
 *             email: $user->getEmail(),
 *         );
 *     }
 * }
 * </code>
 */
interface UserProviderInterface
{
    /**
     * Returns the currently authenticated user or null if no user is authenticated.
     *
     * This method is called when recording invoice actions for audit trail.
     * It should be fast (no heavy database queries) and safe to call multiple times.
     *
     * @return UserData|null The current user or null in CLI/cron contexts
     */
    public function getCurrentUser(): ?UserData;
}
