<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;

interface InvoiceFinalizerInterface
{
    /**
     * Finalize an invoice by assigning a number, generating PDF, and storing it.
     *
     * @throws \InvalidArgumentException if invoice cannot be finalized (invalid status, no lines, etc.)
     * @throws \CorentinBoutillier\InvoiceBundle\Exception\InvoiceFinalizationException if finalization fails
     */
    public function finalize(Invoice $invoice): void;
}
