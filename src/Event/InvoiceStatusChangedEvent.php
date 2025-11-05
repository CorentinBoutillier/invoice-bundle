<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Event;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceStatus;

/**
 * Dispatched when an invoice status changes.
 */
readonly class InvoiceStatusChangedEvent
{
    public function __construct(
        public Invoice $invoice,
        public InvoiceStatus $oldStatus,
        public InvoiceStatus $newStatus,
    ) {
    }
}
