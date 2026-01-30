<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\EReporting\Enum;

/**
 * Payment status for e-reporting purposes.
 *
 * Tracks whether a transaction has been paid for e-reporting submission.
 * E-reporting includes both transaction data and payment information.
 */
enum EReportingPaymentStatus: string
{
    /**
     * Transaction not yet paid.
     */
    case NOT_PAID = 'not_paid';

    /**
     * Transaction partially paid.
     */
    case PARTIALLY_PAID = 'partially_paid';

    /**
     * Transaction fully paid.
     */
    case FULLY_PAID = 'fully_paid';

    /**
     * Check if fully paid.
     */
    public function isPaid(): bool
    {
        return self::FULLY_PAID === $this;
    }

    /**
     * Check if partially paid.
     */
    public function isPartiallyPaid(): bool
    {
        return self::PARTIALLY_PAID === $this;
    }

    /**
     * Get the French label.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::NOT_PAID => 'Non payé',
            self::PARTIALLY_PAID => 'Partiellement payé',
            self::FULLY_PAID => 'Payé',
        };
    }
}
