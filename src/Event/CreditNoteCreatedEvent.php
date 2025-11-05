<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Event;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;

/**
 * Dispatched when a credit note is created.
 */
readonly class CreditNoteCreatedEvent
{
    public function __construct(
        public Invoice $creditNote,              // The credit note itself (type = CREDIT_NOTE)
        public ?Invoice $originalInvoice = null, // The original invoice being credited (optional)
    ) {
    }
}
