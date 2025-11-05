<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Event;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;

/**
 * Dispatched when an invoice is updated.
 */
readonly class InvoiceUpdatedEvent
{
    /**
     * @param array<int, string> $changedFields List of field names that were changed
     */
    public function __construct(
        public Invoice $invoice,
        public array $changedFields,
    ) {
    }
}
