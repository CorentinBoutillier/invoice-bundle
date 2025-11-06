<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\Fec;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Repository\InvoiceRepository;

/**
 * FEC (Fichier des Écritures Comptables) exporter implementation.
 *
 * Generates French legal accounting export format for tax compliance.
 */
final class FecExporter implements FecExporterInterface
{
    /** FEC header column names (18 mandatory columns). */
    private const HEADER_COLUMNS = [
        'JournalCode',
        'JournalLib',
        'EcritureNum',
        'EcritureDate',
        'CompteNum',
        'CompteLib',
        'CompAuxNum',
        'CompAuxLib',
        'PieceRef',
        'PieceDate',
        'EcritureLib',
        'Debit',
        'Credit',
        'EcritureLet',
        'DateLet',
        'ValidDate',
        'Montantdevise',
        'Idevise',
    ];

    /** Account labels per French PCG (Plan Comptable Général). */
    private const ACCOUNT_LABELS = [
        '411000' => 'Clients',
        '707000' => 'Ventes de marchandises',
        '445710' => 'TVA collectée 20%',
        '445712' => 'TVA collectée 10%',
        '445711' => 'TVA collectée 5.5%',
        '445713' => 'TVA collectée 2.1%',
    ];

    /** Counter for sequential EcritureNum generation. */
    private int $ecritureCounter = 0;

    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly string $customerAccount,
        private readonly string $salesAccount,
        private readonly string $vatCollectedAccount,
        private readonly string $journalCode,
        private readonly string $journalLabel,
    ) {
    }

    public function export(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        ?int $companyId = null,
    ): string {
        // Reset counter for each export
        $this->ecritureCounter = 0;

        // Fetch finalized invoices in date range
        $invoices = $this->invoiceRepository->findForFecExport($startDate, $endDate, $companyId);

        // Build CSV lines
        $lines = [$this->getHeaderLine()];

        foreach ($invoices as $invoice) {
            $invoiceLines = $this->createInvoiceLines($invoice);
            $lines = [...$lines, ...$invoiceLines];
        }

        return implode("\n", $lines);
    }

    /**
     * Generate FEC header line with 18 column names.
     */
    private function getHeaderLine(): string
    {
        return implode('|', self::HEADER_COLUMNS);
    }

    /**
     * Create FEC lines for a single invoice.
     *
     * Generates 3+ lines per invoice:
     * - Customer line (debit for invoice, credit for credit note)
     * - Sales line (credit for invoice, debit for credit note)
     * - VAT lines (one per distinct VAT rate)
     *
     * @return string[]
     */
    private function createInvoiceLines(Invoice $invoice): array
    {
        $isCreditNote = InvoiceType::CREDIT_NOTE === $invoice->getType();

        $lines = [
            $this->createCustomerLine($invoice, $isCreditNote),
            $this->createSalesLine($invoice, $isCreditNote),
        ];

        // Add VAT lines (one per distinct VAT rate)
        $vatLines = $this->createVatLines($invoice, $isCreditNote);
        $lines = [...$lines, ...$vatLines];

        return $lines;
    }

    /**
     * Create customer accounting line (account 411000).
     *
     * Invoice: DEBIT customer (receivable)
     * Credit note: CREDIT customer (refund)
     */
    private function createCustomerLine(Invoice $invoice, bool $isCreditNote): string
    {
        $totalTtc = $invoice->getTotalIncludingVat();

        $debit = $isCreditNote ? '0.00' : $this->formatAmount($totalTtc);
        $credit = $isCreditNote ? $this->formatAmount($totalTtc) : '0.00';

        return $this->formatFecLine([
            $this->journalCode,                                          // JournalCode
            $this->journalLabel,                                         // JournalLib
            $this->getNextEcritureNum(),                                 // EcritureNum
            $this->formatDate($invoice->getDate()),                      // EcritureDate
            $this->customerAccount,                                      // CompteNum
            self::ACCOUNT_LABELS[$this->customerAccount] ?? 'Clients',   // CompteLib
            '',                                                          // CompAuxNum (empty)
            $invoice->getCustomerName(),                                 // CompAuxLib
            $invoice->getNumber() ?? '',                                 // PieceRef
            $this->formatDate($invoice->getDate()),                      // PieceDate
            $this->getInvoiceLabel($invoice),                            // EcritureLib
            $debit,                                                      // Debit
            $credit,                                                     // Credit
            '',                                                          // EcritureLet (empty)
            '',                                                          // DateLet (empty)
            $this->formatDate($invoice->getDate()),                      // ValidDate
            '',                                                          // Montantdevise (empty)
            '',                                                          // Idevise (empty)
        ]);
    }

    /**
     * Create sales accounting line (account 707000).
     *
     * Invoice: CREDIT sales (revenue)
     * Credit note: DEBIT sales (refund)
     */
    private function createSalesLine(Invoice $invoice, bool $isCreditNote): string
    {
        $totalHt = $invoice->getSubtotalAfterDiscount();

        $debit = $isCreditNote ? $this->formatAmount($totalHt) : '0.00';
        $credit = $isCreditNote ? '0.00' : $this->formatAmount($totalHt);

        return $this->formatFecLine([
            $this->journalCode,                                                   // JournalCode
            $this->journalLabel,                                                  // JournalLib
            $this->getNextEcritureNum(),                                          // EcritureNum
            $this->formatDate($invoice->getDate()),                               // EcritureDate
            $this->salesAccount,                                                  // CompteNum
            self::ACCOUNT_LABELS[$this->salesAccount] ?? 'Ventes de marchandises', // CompteLib
            '',                                                                   // CompAuxNum (empty)
            $invoice->getCustomerName(),                                          // CompAuxLib
            $invoice->getNumber() ?? '',                                          // PieceRef
            $this->formatDate($invoice->getDate()),                               // PieceDate
            $this->getInvoiceLabel($invoice),                                     // EcritureLib
            $debit,                                                               // Debit
            $credit,                                                              // Credit
            '',                                                                   // EcritureLet (empty)
            '',                                                                   // DateLet (empty)
            $this->formatDate($invoice->getDate()),                               // ValidDate
            '',                                                                   // Montantdevise (empty)
            '',                                                                   // Idevise (empty)
        ]);
    }

    /**
     * Create VAT accounting lines (one per distinct VAT rate).
     *
     * Invoice: CREDIT VAT (collected)
     * Credit note: DEBIT VAT (refund)
     *
     * @return string[]
     */
    private function createVatLines(Invoice $invoice, bool $isCreditNote): array
    {
        $linesByRate = $this->groupLinesByVatRate($invoice);
        $vatLines = [];

        foreach ($linesByRate as $rateString => $lines) {
            $rate = (float) $rateString; // Convert string back to float
            $vatAmount = $this->calculateVatForLines($lines);

            $debit = $isCreditNote ? $this->formatAmount($vatAmount) : '0.00';
            $credit = $isCreditNote ? '0.00' : $this->formatAmount($vatAmount);

            $vatAccount = $this->getVatAccountForRate($rate);
            $vatLabel = $this->getVatLabelForRate($rate);

            $vatLines[] = $this->formatFecLine([
                $this->journalCode,                          // JournalCode
                $this->journalLabel,                         // JournalLib
                $this->getNextEcritureNum(),                 // EcritureNum
                $this->formatDate($invoice->getDate()),      // EcritureDate
                $vatAccount,                                 // CompteNum
                $vatLabel,                                   // CompteLib
                '',                                          // CompAuxNum (empty)
                '',                                          // CompAuxLib (empty for VAT)
                $invoice->getNumber() ?? '',                 // PieceRef
                $this->formatDate($invoice->getDate()),      // PieceDate
                $this->getVatLineLabel($invoice, $rate),     // EcritureLib
                $debit,                                      // Debit
                $credit,                                     // Credit
                '',                                          // EcritureLet (empty)
                '',                                          // DateLet (empty)
                $this->formatDate($invoice->getDate()),      // ValidDate
                '',                                          // Montantdevise (empty)
                '',                                          // Idevise (empty)
            ]);
        }

        return $vatLines;
    }

    /**
     * Group invoice lines by VAT rate.
     *
     * @return array<string, array<int, InvoiceLine>> Grouped lines indexed by VAT rate (as string)
     */
    private function groupLinesByVatRate(Invoice $invoice): array
    {
        $grouped = [];

        foreach ($invoice->getLines() as $line) {
            $rate = $line->getVatRate();
            $key = (string) $rate; // Convert to string to preserve decimal precision

            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }

            $grouped[$key][] = $line;
        }

        // Explicitly cast keys to string for PHPStan
        /** @var array<string, array<int, InvoiceLine>> */
        $result = [];
        foreach ($grouped as $key => $lines) {
            $result[(string) $key] = $lines;
        }

        /** @phpstan-ignore-next-line PHPStan doesn't recognize our string key casting */
        return $result;
    }

    /**
     * Calculate total VAT amount for a group of lines.
     *
     * @param array<int, InvoiceLine> $lines
     */
    private function calculateVatForLines(array $lines): Money
    {
        $totalVat = Money::fromCents(0);

        foreach ($lines as $line) {
            $totalVat = $totalVat->add($line->getVatAmount());
        }

        return $totalVat;
    }

    /**
     * Get VAT account number for a specific rate.
     */
    private function getVatAccountForRate(float $rate): string
    {
        // Default to configured VAT account (445710 for 20%)
        if (20.0 === $rate) {
            return $this->vatCollectedAccount;
        }

        // Use different accounts for other rates
        return match ($rate) {
            10.0 => '445712',
            5.5 => '445711',
            2.1 => '445713',
            default => $this->vatCollectedAccount,
        };
    }

    /**
     * Get VAT account label for a specific rate.
     */
    private function getVatLabelForRate(float $rate): string
    {
        $account = $this->getVatAccountForRate($rate);

        return self::ACCOUNT_LABELS[$account] ?? \sprintf('TVA collectée %.1f%%', $rate);
    }

    /**
     * Get invoice description for FEC line.
     */
    private function getInvoiceLabel(Invoice $invoice): string
    {
        $type = InvoiceType::CREDIT_NOTE === $invoice->getType() ? 'Avoir' : 'Facture';

        return \sprintf('%s %s', $type, $invoice->getNumber() ?? '');
    }

    /**
     * Get VAT line description.
     */
    private function getVatLineLabel(Invoice $invoice, float $rate): string
    {
        $type = InvoiceType::CREDIT_NOTE === $invoice->getType() ? 'Avoir' : 'Facture';

        return \sprintf('%s %s - TVA %.1f%%', $type, $invoice->getNumber() ?? '', $rate);
    }

    /**
     * Format date as YYYYMMDD.
     */
    private function formatDate(\DateTimeImmutable $date): string
    {
        return $date->format('Ymd');
    }

    /**
     * Format Money amount with period as decimal separator.
     */
    private function formatAmount(Money $amount): string
    {
        return $amount->toEuros();
    }

    /**
     * Format FEC line from column array.
     *
     * @param string[] $columns 18 column values
     */
    private function formatFecLine(array $columns): string
    {
        if (18 !== \count($columns)) {
            throw new \InvalidArgumentException(\sprintf('FEC line must have exactly 18 columns, got %d', \count($columns)));
        }

        return implode('|', $columns);
    }

    /**
     * Generate next sequential EcritureNum.
     */
    private function getNextEcritureNum(): string
    {
        ++$this->ecritureCounter;

        return str_pad((string) $this->ecritureCounter, 3, '0', \STR_PAD_LEFT);
    }
}
