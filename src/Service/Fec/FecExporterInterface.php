<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\Fec;

/**
 * FEC (Fichier des Écritures Comptables) exporter interface.
 *
 * Exports finalized invoices to French legal FEC format for tax compliance.
 *
 * Format specifications:
 * - 18 mandatory columns with pipe separator (|)
 * - Header row with column names
 * - Date format: YYYYMMDD
 * - Amount format: period as decimal separator (1200.00)
 * - Double-entry accounting: debits = credits
 * - Only FINALIZED invoices included
 *
 * Legal framework: Article A.47 A-1 du Livre des Procédures Fiscales (LPF).
 *
 * @see https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000027804775
 */
interface FecExporterInterface
{
    /**
     * Export invoices to FEC format.
     *
     * Returns CSV content with:
     * - Header line: JournalCode|JournalLib|...|Idevise
     * - Data lines: One line per accounting entry (customer, sales, VAT)
     *
     * Each invoice generates 3+ lines:
     * - Customer debit/credit (account 411000)
     * - Sales credit/debit (account 707000)
     * - VAT collected credit/debit (account 445710, one line per VAT rate)
     *
     * @param \DateTimeImmutable $startDate Start of fiscal period (inclusive)
     * @param \DateTimeImmutable $endDate   End of fiscal period (inclusive)
     * @param int|null           $companyId Optional company filter for multi-company setups
     *
     * @return string CSV content with pipe separator, ready to write to file
     */
    public function export(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        ?int $companyId = null,
    ): string;
}
