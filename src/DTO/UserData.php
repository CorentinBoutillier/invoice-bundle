<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\DTO;

/**
 * User data snapshot for audit trail (InvoiceHistory).
 *
 * Contains minimal user information to record who performed an invoice action.
 * Used by InvoiceHistorySubscriber to log user actions.
 */
readonly class UserData
{
    public function __construct(
        public string $id,           // User ID (flexible: UUID, int as string, etc.)
        public ?string $name = null, // User display name for readability
        public ?string $email = null, // User email for contact
    ) {
    }
}
