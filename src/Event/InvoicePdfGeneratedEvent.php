<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Event;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;

/**
 * Dispatched when an invoice PDF is generated and stored.
 */
readonly class InvoicePdfGeneratedEvent
{
    public function __construct(
        public Invoice $invoice,
        public string $pdfPath, // Storage path to the generated PDF
    ) {
    }
}
