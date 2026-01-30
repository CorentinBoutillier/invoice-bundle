<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Pdp;

/**
 * Status codes for PDP invoice transmission lifecycle.
 *
 * Based on the French e-invoicing reform (Facture électronique 2026)
 * and common PDP platform statuses.
 */
enum PdpStatusCode: string
{
    /**
     * Invoice created locally, not yet submitted to PDP.
     */
    case PENDING = 'pending';

    /**
     * Invoice submitted to PDP, awaiting processing.
     */
    case SUBMITTED = 'submitted';

    /**
     * PDP has accepted the invoice for transmission.
     */
    case ACCEPTED = 'accepted';

    /**
     * Invoice rejected by PDP (validation error, format issue).
     */
    case REJECTED = 'rejected';

    /**
     * Invoice transmitted to recipient's PDP/PPF.
     */
    case TRANSMITTED = 'transmitted';

    /**
     * Invoice delivered to recipient.
     */
    case DELIVERED = 'delivered';

    /**
     * Recipient acknowledged receipt.
     */
    case ACKNOWLEDGED = 'acknowledged';

    /**
     * Invoice approved/accepted by recipient.
     */
    case APPROVED = 'approved';

    /**
     * Invoice refused by recipient.
     */
    case REFUSED = 'refused';

    /**
     * Invoice paid (lifecycle complete).
     */
    case PAID = 'paid';

    /**
     * Transmission failed (network error, timeout).
     */
    case FAILED = 'failed';

    /**
     * Invoice cancelled/withdrawn.
     */
    case CANCELLED = 'cancelled';

    /**
     * Check if this status indicates a successful state.
     */
    public function isSuccessful(): bool
    {
        return \in_array($this, [
            self::ACCEPTED,
            self::TRANSMITTED,
            self::DELIVERED,
            self::ACKNOWLEDGED,
            self::APPROVED,
            self::PAID,
        ], true);
    }

    /**
     * Check if this status indicates a failure state.
     */
    public function isFailure(): bool
    {
        return \in_array($this, [
            self::REJECTED,
            self::REFUSED,
            self::FAILED,
            self::CANCELLED,
        ], true);
    }

    /**
     * Check if this status is terminal (no further transitions expected).
     */
    public function isTerminal(): bool
    {
        return \in_array($this, [
            self::PAID,
            self::REJECTED,
            self::REFUSED,
            self::FAILED,
            self::CANCELLED,
        ], true);
    }

    /**
     * Check if this status is pending/in-progress.
     */
    public function isPending(): bool
    {
        return \in_array($this, [
            self::PENDING,
            self::SUBMITTED,
            self::ACCEPTED,
            self::TRANSMITTED,
            self::DELIVERED,
            self::ACKNOWLEDGED,
            self::APPROVED,
        ], true);
    }

    /**
     * Get human-readable label (French).
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::SUBMITTED => 'Soumise',
            self::ACCEPTED => 'Acceptée',
            self::REJECTED => 'Rejetée',
            self::TRANSMITTED => 'Transmise',
            self::DELIVERED => 'Livrée',
            self::ACKNOWLEDGED => 'Accusée de réception',
            self::APPROVED => 'Approuvée',
            self::REFUSED => 'Refusée',
            self::PAID => 'Payée',
            self::FAILED => 'Échec',
            self::CANCELLED => 'Annulée',
        };
    }
}
