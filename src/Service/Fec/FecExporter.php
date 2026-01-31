<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\Fec;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;
use CorentinBoutillier\InvoiceBundle\Entity\Payment;
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
        '512000' => 'Banque',
        '707000' => 'Ventes de marchandises',
        '445710' => 'TVA collectée 20%',
        '445712' => 'TVA collectée 10%',
        '445711' => 'TVA collectée 5.5%',
        '445713' => 'TVA collectée 2.1%',
    ];

    /** Counter for sequential EcritureNum generation (one per invoice, not per line). */
    private int $ecritureCounter = 0;

    /** Counter for lettrage code generation. */
    private int $lettrageCounter = 0;

    /** Current EcritureNum for the invoice being processed. */
    private string $currentEcritureNum = '';

    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly string $customerAccount,
        private readonly string $salesAccount,
        private readonly string $vatCollectedAccount,
        private readonly string $journalCode,
        private readonly string $journalLabel,
        private readonly string $bankAccount = '512000',
        private readonly string $bankJournalCode = 'BQ',
        private readonly string $bankJournalLabel = 'Banque',
    ) {
    }

    public function export(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        ?int $companyId = null,
    ): string {
        // Reset counters for each export
        $this->ecritureCounter = 0;
        $this->lettrageCounter = 0;

        // Fetch finalized invoices in date range
        $invoices = $this->invoiceRepository->findForFecExport($startDate, $endDate, $companyId);

        // Build CSV lines
        $lines = [$this->getHeaderLine()];

        foreach ($invoices as $invoice) {
            $invoiceLines = $this->createInvoiceLines($invoice);
            $lines = [...$lines, ...$invoiceLines];

            // Generate payment entries if invoice has payments
            $paymentLines = $this->createPaymentLines($invoice);
            $lines = [...$lines, ...$paymentLines];
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
     * Generates 3+ lines per invoice (all with same EcritureNum):
     * - Customer line (debit for invoice, credit for credit note)
     * - Sales line (credit for invoice, debit for credit note)
     * - VAT lines (one per distinct VAT rate)
     *
     * @return string[]
     */
    private function createInvoiceLines(Invoice $invoice): array
    {
        // Increment counter once per invoice - all lines share the same EcritureNum
        ++$this->ecritureCounter;
        $this->currentEcritureNum = str_pad((string) $this->ecritureCounter, 6, '0', \STR_PAD_LEFT);

        $isCreditNote = InvoiceType::CREDIT_NOTE === $invoice->getType();

        // Generate lettrage code if invoice has payments
        $lettrageCode = '';
        $lettrageDate = '';
        $payments = $invoice->getPayments();
        if (\count($payments) > 0) {
            $lettrageCode = $this->generateLettrageCode();
            // Get the last payment date for lettrage
            $lastPayment = $this->getLastPayment($payments);
            if ($lastPayment) {
                $lettrageDate = $this->formatDate($lastPayment->getPaidAt());
            }
        }

        $lines = [
            $this->createCustomerLine($invoice, $isCreditNote, $lettrageCode, $lettrageDate),
            $this->createSalesLine($invoice, $isCreditNote),
        ];

        // Add VAT lines (one per distinct VAT rate)
        $vatLines = $this->createVatLines($invoice, $isCreditNote);
        $lines = [...$lines, ...$vatLines];

        return $lines;
    }

    /**
     * Create payment FEC lines for an invoice.
     *
     * Generates 2 lines per payment:
     * - Bank debit (account 512000)
     * - Customer credit (account 411000)
     *
     * @return string[]
     */
    private function createPaymentLines(Invoice $invoice): array
    {
        $payments = $invoice->getPayments();
        if (0 === \count($payments)) {
            return [];
        }

        $lines = [];
        $isCreditNote = InvoiceType::CREDIT_NOTE === $invoice->getType();

        // Get the lettrage code (same as the one used for the invoice customer line)
        $lettrageCode = $this->getCurrentLettrageCode();

        foreach ($payments as $payment) {
            // New EcritureNum for each payment
            ++$this->ecritureCounter;
            $this->currentEcritureNum = str_pad((string) $this->ecritureCounter, 6, '0', \STR_PAD_LEFT);

            $lettrageDate = $this->formatDate($payment->getPaidAt());

            $lines[] = $this->createBankLine($invoice, $payment, $isCreditNote);
            $lines[] = $this->createPaymentCustomerLine($invoice, $payment, $isCreditNote, $lettrageCode, $lettrageDate);
        }

        return $lines;
    }

    /**
     * Create customer accounting line (account 411000).
     *
     * Invoice: DEBIT customer (receivable)
     * Credit note: CREDIT customer (refund)
     */
    private function createCustomerLine(Invoice $invoice, bool $isCreditNote, string $lettrageCode, string $lettrageDate): string
    {
        $totalTtc = $invoice->getTotalIncludingVat();
        $zero = '0,00'; // French FEC format

        $debit = $isCreditNote ? $zero : $this->formatAmount($totalTtc);
        $credit = $isCreditNote ? $this->formatAmount($totalTtc) : $zero;

        return $this->formatFecLine([
            $this->journalCode,                                          // JournalCode
            $this->journalLabel,                                         // JournalLib
            $this->currentEcritureNum,                                   // EcritureNum (same for all lines of this invoice)
            $this->formatDate($invoice->getDate()),                      // EcritureDate
            $this->customerAccount,                                      // CompteNum
            self::ACCOUNT_LABELS[$this->customerAccount] ?? 'Clients',   // CompteLib
            $this->generateCustomerAuxCode($invoice),                    // CompAuxNum
            $invoice->getCustomerName(),                                 // CompAuxLib
            $invoice->getNumber() ?? '',                                 // PieceRef
            $this->formatDate($invoice->getDate()),                      // PieceDate
            $this->getInvoiceLabel($invoice),                            // EcritureLib
            $debit,                                                      // Debit
            $credit,                                                     // Credit
            $lettrageCode,                                               // EcritureLet
            $lettrageDate,                                               // DateLet
            $this->formatDate($invoice->getDate()),                      // ValidDate
            '',                                                          // Montantdevise (empty - EUR)
            '',                                                          // Idevise (empty - EUR)
        ]);
    }

    /**
     * Create bank accounting line for payment (account 512000).
     *
     * Invoice payment: DEBIT bank (cash in)
     * Credit note refund: CREDIT bank (cash out)
     */
    private function createBankLine(Invoice $invoice, Payment $payment, bool $isCreditNote): string
    {
        $amount = $payment->getAmount();
        $zero = '0,00';

        $debit = $isCreditNote ? $zero : $this->formatAmount($amount);
        $credit = $isCreditNote ? $this->formatAmount($amount) : $zero;

        return $this->formatFecLine([
            $this->bankJournalCode,                                      // JournalCode
            $this->bankJournalLabel,                                     // JournalLib
            $this->currentEcritureNum,                                   // EcritureNum
            $this->formatDate($payment->getPaidAt()),                    // EcritureDate
            $this->bankAccount,                                          // CompteNum
            self::ACCOUNT_LABELS[$this->bankAccount] ?? 'Banque',        // CompteLib
            '',                                                          // CompAuxNum (empty for bank)
            '',                                                          // CompAuxLib (empty for bank)
            $invoice->getNumber() ?? '',                                 // PieceRef
            $this->formatDate($payment->getPaidAt()),                    // PieceDate
            $this->getPaymentLabel($invoice, $payment),                  // EcritureLib
            $debit,                                                      // Debit
            $credit,                                                     // Credit
            '',                                                          // EcritureLet (no lettrage for bank)
            '',                                                          // DateLet (no lettrage for bank)
            $this->formatDate($payment->getPaidAt()),                    // ValidDate
            '',                                                          // Montantdevise (empty - EUR)
            '',                                                          // Idevise (empty - EUR)
        ]);
    }

    /**
     * Create customer accounting line for payment (account 411000).
     *
     * Invoice payment: CREDIT customer (reduce receivable)
     * Credit note refund: DEBIT customer (reduce liability)
     */
    private function createPaymentCustomerLine(Invoice $invoice, Payment $payment, bool $isCreditNote, string $lettrageCode, string $lettrageDate): string
    {
        $amount = $payment->getAmount();
        $zero = '0,00';

        $debit = $isCreditNote ? $this->formatAmount($amount) : $zero;
        $credit = $isCreditNote ? $zero : $this->formatAmount($amount);

        return $this->formatFecLine([
            $this->bankJournalCode,                                      // JournalCode
            $this->bankJournalLabel,                                     // JournalLib
            $this->currentEcritureNum,                                   // EcritureNum
            $this->formatDate($payment->getPaidAt()),                    // EcritureDate
            $this->customerAccount,                                      // CompteNum
            self::ACCOUNT_LABELS[$this->customerAccount] ?? 'Clients',   // CompteLib
            $this->generateCustomerAuxCode($invoice),                    // CompAuxNum
            $invoice->getCustomerName(),                                 // CompAuxLib
            $invoice->getNumber() ?? '',                                 // PieceRef
            $this->formatDate($payment->getPaidAt()),                    // PieceDate
            $this->getPaymentLabel($invoice, $payment),                  // EcritureLib
            $debit,                                                      // Debit
            $credit,                                                     // Credit
            $lettrageCode,                                               // EcritureLet
            $lettrageDate,                                               // DateLet
            $this->formatDate($payment->getPaidAt()),                    // ValidDate
            '',                                                          // Montantdevise (empty - EUR)
            '',                                                          // Idevise (empty - EUR)
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
        $zero = '0,00'; // French FEC format

        $debit = $isCreditNote ? $this->formatAmount($totalHt) : $zero;
        $credit = $isCreditNote ? $zero : $this->formatAmount($totalHt);

        return $this->formatFecLine([
            $this->journalCode,                                                   // JournalCode
            $this->journalLabel,                                                  // JournalLib
            $this->currentEcritureNum,                                            // EcritureNum (same for all lines of this invoice)
            $this->formatDate($invoice->getDate()),                               // EcritureDate
            $this->salesAccount,                                                  // CompteNum
            self::ACCOUNT_LABELS[$this->salesAccount] ?? 'Ventes de marchandises', // CompteLib
            '',                                                                   // CompAuxNum (empty for sales)
            '',                                                                   // CompAuxLib (empty for sales)
            $invoice->getNumber() ?? '',                                          // PieceRef
            $this->formatDate($invoice->getDate()),                               // PieceDate
            $this->getInvoiceLabel($invoice),                                     // EcritureLib
            $debit,                                                               // Debit
            $credit,                                                              // Credit
            '',                                                                   // EcritureLet (no lettrage for sales)
            '',                                                                   // DateLet (no lettrage for sales)
            $this->formatDate($invoice->getDate()),                               // ValidDate
            '',                                                                   // Montantdevise (empty - EUR)
            '',                                                                   // Idevise (empty - EUR)
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

        $zero = '0,00'; // French FEC format

        foreach ($linesByRate as $rateString => $lines) {
            $rate = (float) $rateString; // Convert string back to float
            $vatAmount = $this->calculateVatForLines($lines);

            $debit = $isCreditNote ? $this->formatAmount($vatAmount) : $zero;
            $credit = $isCreditNote ? $zero : $this->formatAmount($vatAmount);

            $vatAccount = $this->getVatAccountForRate($rate);
            $vatLabel = $this->getVatLabelForRate($rate);

            $vatLines[] = $this->formatFecLine([
                $this->journalCode,                          // JournalCode
                $this->journalLabel,                         // JournalLib
                $this->currentEcritureNum,                   // EcritureNum (same for all lines of this invoice)
                $this->formatDate($invoice->getDate()),      // EcritureDate
                $vatAccount,                                 // CompteNum
                $vatLabel,                                   // CompteLib
                '',                                          // CompAuxNum (empty for VAT)
                '',                                          // CompAuxLib (empty for VAT)
                $invoice->getNumber() ?? '',                 // PieceRef
                $this->formatDate($invoice->getDate()),      // PieceDate
                $this->getVatLineLabel($invoice, $rate),     // EcritureLib
                $debit,                                      // Debit
                $credit,                                     // Credit
                '',                                          // EcritureLet (no lettrage for VAT)
                '',                                          // DateLet (no lettrage for VAT)
                $this->formatDate($invoice->getDate()),      // ValidDate
                '',                                          // Montantdevise (empty - EUR)
                '',                                          // Idevise (empty - EUR)
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
     * Get payment description for FEC line.
     */
    private function getPaymentLabel(Invoice $invoice, Payment $payment): string
    {
        $type = InvoiceType::CREDIT_NOTE === $invoice->getType() ? 'Avoir' : 'Facture';

        return \sprintf('Règlement %s %s', $type, $invoice->getNumber() ?? '');
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
     * Format Money amount with comma as decimal separator (FEC French format).
     *
     * French legal requirements mandate comma separator in FEC files.
     */
    private function formatAmount(Money $amount): string
    {
        // FEC format: comma as decimal separator, no thousands separator
        $euros = $amount->getAmount() / 100;

        return number_format($euros, 2, ',', '');
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
     * Generate auxiliary account code for customer.
     *
     * Uses customer SIRET if available, otherwise generates from customer name.
     */
    private function generateCustomerAuxCode(Invoice $invoice): string
    {
        // Try to get SIRET from customer (if available via getter)
        $siret = $invoice->getCustomerSiret();
        if ($siret) {
            return $siret;
        }

        // Fallback: generate code from customer name (sanitized)
        $name = $invoice->getCustomerName();
        // Remove accents and special characters, uppercase, limit to 17 chars
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $this->removeAccents($name)) ?? '');

        return substr($code, 0, 17);
    }

    /**
     * Remove accents from string for ASCII-safe code generation.
     */
    private function removeAccents(string $string): string
    {
        $transliterator = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
        if (null === $transliterator) {
            // Fallback if Transliterator not available
            return $string;
        }

        return $transliterator->transliterate($string) ?: $string;
    }

    /**
     * Generate a unique lettrage code (A, B, C... AA, AB...).
     */
    private function generateLettrageCode(): string
    {
        ++$this->lettrageCounter;

        return $this->numberToLettrageCode($this->lettrageCounter);
    }

    /**
     * Get current lettrage code without incrementing.
     */
    private function getCurrentLettrageCode(): string
    {
        return $this->numberToLettrageCode($this->lettrageCounter);
    }

    /**
     * Convert number to lettrage code (1=A, 2=B, 26=Z, 27=AA, etc.).
     */
    private function numberToLettrageCode(int $number): string
    {
        $code = '';
        while ($number > 0) {
            --$number;
            $code = \chr(65 + ($number % 26)) . $code;
            $number = (int) ($number / 26);
        }

        return $code;
    }

    /**
     * Get the last payment by date.
     *
     * @param array<int, Payment> $payments
     */
    private function getLastPayment(array $payments): ?Payment
    {
        if (0 === \count($payments)) {
            return null;
        }

        $lastPayment = $payments[0];
        foreach ($payments as $payment) {
            if ($payment->getPaidAt() > $lastPayment->getPaidAt()) {
                $lastPayment = $payment;
            }
        }

        return $lastPayment;
    }
}
