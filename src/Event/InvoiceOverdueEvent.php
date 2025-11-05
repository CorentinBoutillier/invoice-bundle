<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Event;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;

/**
 * Dispatched when an invoice becomes overdue.
 */
readonly class InvoiceOverdueEvent
{
    public function __construct(
        public Invoice $invoice,
        public int $daysOverdue,
    ) {
    }
}
