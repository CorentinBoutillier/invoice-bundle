<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Event;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;

/**
 * Dispatched when an invoice is fully paid.
 */
readonly class InvoicePaidEvent
{
    public function __construct(
        public Invoice $invoice,
        public \DateTimeImmutable $paidAt,
    ) {
    }
}
