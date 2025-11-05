<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service;

/**
 * Calculates invoice due dates based on payment terms.
 *
 * Supports French standard payment terms:
 * - "comptant" = immediate (invoice date)
 * - "X jours net" = invoice date + X days
 * - "X jours fin de mois" = (invoice date + X days), then last day of that month
 *
 * Unknown or invalid terms default to "30 jours net".
 */
interface DueDateCalculatorInterface
{
    /**
     * Calculates the due date based on invoice date and payment terms.
     *
     * @param \DateTimeImmutable $invoiceDate The invoice date
     * @param string             $paymentTerms Payment terms (e.g., "30 jours net", "comptant")
     *
     * @return \DateTimeImmutable The calculated due date
     */
    public function calculate(
        \DateTimeImmutable $invoiceDate,
        string $paymentTerms,
    ): \DateTimeImmutable;
}
