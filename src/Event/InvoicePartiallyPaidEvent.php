<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Event;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;

/**
 * Dispatched when an invoice receives a partial payment.
 */
readonly class InvoicePartiallyPaidEvent
{
    public function __construct(
        public Invoice $invoice,
        public Money $amountPaid,      // Amount paid in this payment
        public Money $remainingAmount, // Remaining amount still due
    ) {
    }
}
