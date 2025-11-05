<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Event;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;

/**
 * Dispatched when a new invoice is created.
 */
readonly class InvoiceCreatedEvent
{
    public function __construct(
        public Invoice $invoice,
    ) {
    }
}
