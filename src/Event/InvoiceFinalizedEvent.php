<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Event;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;

/**
 * Dispatched when an invoice is finalized (assigned a number and made immutable).
 */
readonly class InvoiceFinalizedEvent
{
    public function __construct(
        public Invoice $invoice,
        public string $number, // Invoice number (e.g., FA-2025-0001)
    ) {
    }
}
