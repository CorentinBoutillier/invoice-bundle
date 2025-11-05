<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Event;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;

/**
 * Dispatched when an invoice is cancelled.
 */
readonly class InvoiceCancelledEvent
{
    public function __construct(
        public Invoice $invoice,
        public ?string $reason = null,
    ) {
    }
}
