<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\NumberGenerator;

use CorentinBoutillier\InvoiceBundle\DTO\CompanyData;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Repository\InvoiceSequenceRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Génère des numéros de facture uniques avec séquence thread-safe.
 *
 * Ce service utilise InvoiceSequence pour garantir l'unicité et la séquentialité
 * des numéros de factures, avec support des exercices comptables non-calendaires.
 */
final class InvoiceNumberGenerator implements InvoiceNumberGeneratorInterface
{
    private const int DEFAULT_SEQUENCE_PADDING = 4;
    private const string INVOICE_PREFIX = 'FA';
    private const string CREDIT_NOTE_PREFIX = 'AV';

    public function __construct(
        private readonly InvoiceSequenceRepository $sequenceRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly int $sequencePadding = self::DEFAULT_SEQUENCE_PADDING,
    ) {
    }

    public function generate(Invoice $invoice, CompanyData $company): string
    {
        // 1. Ensure sequence exists for this invoice date/company/type
        //    This creates the sequence if it doesn't exist and handles fiscal year calculation
        $this->sequenceRepository->findOrCreateSequence(
            companyId: $invoice->getCompanyId(),
            invoiceDate: $invoice->getDate(),
            type: $invoice->getType(),
            fiscalYearStartMonth: $company->fiscalYearStartMonth,
            fiscalYearStartDay: $company->fiscalYearStartDay,
        );

        // 2. Calculate fiscal year for this invoice (same logic as repository)
        $fiscalYear = $this->calculateFiscalYear(
            invoiceDate: $invoice->getDate(),
            fiscalYearStartMonth: $company->fiscalYearStartMonth,
            fiscalYearStartDay: $company->fiscalYearStartDay,
        );

        // 3. Get sequence with pessimistic write lock (MUST be in transaction)
        $sequence = $this->sequenceRepository->findForUpdate(
            companyId: $invoice->getCompanyId(),
            fiscalYear: $fiscalYear,
            type: $invoice->getType(),
        );

        if (null === $sequence) {
            throw new \RuntimeException(\sprintf(
                'InvoiceSequence not found after creation for company %s, fiscal year %d, type %s',
                $invoice->getCompanyId() ?? 'null',
                $fiscalYear,
                $invoice->getType()->value,
            ));
        }

        // 4. Get next number and increment sequence
        $nextNumber = $sequence->getNextNumber();
        $sequence->incrementLastNumber();

        // 5. Flush to persist the increment (within transaction)
        $this->entityManager->flush();

        // 6. Format the invoice number
        return $this->formatNumber(
            type: $invoice->getType(),
            fiscalYear: $fiscalYear,
            sequenceNumber: $nextNumber,
        );
    }

    /**
     * Calculate fiscal year from invoice date and fiscal year settings.
     *
     * If the invoice date is before the fiscal year start date in the calendar year,
     * it belongs to the previous fiscal year.
     *
     * Example with fiscal year Nov-Oct:
     * - Invoice dated 2024-10-31 → Fiscal year 2023
     * - Invoice dated 2024-11-01 → Fiscal year 2024
     */
    private function calculateFiscalYear(
        \DateTimeImmutable $invoiceDate,
        int $fiscalYearStartMonth,
        int $fiscalYearStartDay,
    ): int {
        $year = (int) $invoiceDate->format('Y');

        // Create the fiscal year start date for this calendar year
        $fiscalYearStart = new \DateTimeImmutable(
            \sprintf('%04d-%02d-%02d', $year, $fiscalYearStartMonth, $fiscalYearStartDay),
        );

        // If invoice date is before fiscal year start, it belongs to previous fiscal year
        if ($invoiceDate < $fiscalYearStart) {
            return $year - 1;
        }

        return $year;
    }

    /**
     * Format the invoice number with prefix, year, and padded sequence.
     *
     * Format: {PREFIX}-{YEAR}-{SEQUENCE}
     * - Invoices: FA-2025-0001
     * - Credit notes: AV-2025-0042
     */
    private function formatNumber(
        InvoiceType $type,
        int $fiscalYear,
        int $sequenceNumber,
    ): string {
        $prefix = InvoiceType::INVOICE === $type ? self::INVOICE_PREFIX : self::CREDIT_NOTE_PREFIX;

        // Pad sequence number with leading zeros (up to configured padding)
        $paddedSequence = str_pad(
            string: (string) $sequenceNumber,
            length: $this->sequencePadding,
            pad_string: '0',
            pad_type: \STR_PAD_LEFT,
        );

        return \sprintf('%s-%d-%s', $prefix, $fiscalYear, $paddedSequence);
    }
}
