<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\EReporting;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\EReporting\Dto\EReportingTransaction;
use CorentinBoutillier\InvoiceBundle\EReporting\Dto\ReportingResult;
use CorentinBoutillier\InvoiceBundle\EReporting\Dto\ReportingSummary;
use CorentinBoutillier\InvoiceBundle\EReporting\Enum\ReportingFrequency;

/**
 * Interface for e-reporting services.
 *
 * Handles the submission of e-reporting data to the French tax administration.
 */
interface EReportingServiceInterface
{
    /**
     * Create an e-reporting transaction from an invoice.
     */
    public function createTransactionFromInvoice(Invoice $invoice): EReportingTransaction;

    /**
     * Get a summary for a reporting period.
     *
     * @param EReportingTransaction[] $transactions
     */
    public function getSummary(
        \DateTimeImmutable $periodStart,
        \DateTimeImmutable $periodEnd,
        ReportingFrequency $frequency,
        array $transactions,
    ): ReportingSummary;

    /**
     * Submit e-reporting data for a period.
     *
     * @param EReportingTransaction[] $transactions
     */
    public function submit(array $transactions): ReportingResult;

    /**
     * Check if an invoice requires e-reporting.
     */
    public function requiresEReporting(Invoice $invoice): bool;
}
