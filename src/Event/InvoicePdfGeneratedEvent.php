<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Event;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;

/**
 * Dispatched when an invoice PDF is generated and stored.
 */
final class InvoicePdfGeneratedEvent
{
    public function __construct(
        public readonly Invoice $invoice,
        public readonly string $pdfContent, // Binary content of the generated PDF
    ) {
    }
}
