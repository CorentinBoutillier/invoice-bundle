<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\EReporting;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceStatus;
use CorentinBoutillier\InvoiceBundle\EReporting\Dto\EReportingTransaction;
use CorentinBoutillier\InvoiceBundle\EReporting\Dto\ReportingResult;
use CorentinBoutillier\InvoiceBundle\EReporting\Dto\ReportingSummary;
use CorentinBoutillier\InvoiceBundle\EReporting\Enum\EReportingPaymentStatus;
use CorentinBoutillier\InvoiceBundle\EReporting\Enum\ReportingFrequency;
use CorentinBoutillier\InvoiceBundle\EReporting\Enum\TransactionType;

/**
 * E-reporting service implementation.
 *
 * Handles the creation of e-reporting transactions from invoices
 * and submission to the French tax administration.
 */
final class EReportingService implements EReportingServiceInterface
{
    /**
     * EU member state country codes.
     */
    private const EU_COUNTRY_CODES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ];

    public function __construct(
        private readonly EReportingScheduler $scheduler,
    ) {
    }

    public function createTransactionFromInvoice(Invoice $invoice): EReportingTransaction
    {
        $transactionType = $this->determineTransactionType($invoice);
        $paymentStatus = $this->determinePaymentStatus($invoice);
        $vatBreakdown = $this->calculateVatBreakdown($invoice);

        return new EReportingTransaction(
            invoiceNumber: $invoice->getNumber() ?? '',
            invoiceDate: $invoice->getDate(),
            transactionType: $transactionType,
            totalExcludingVat: $invoice->getSubtotalAfterDiscount()->toEuros(),
            totalVat: $invoice->getTotalVat()->toEuros(),
            totalIncludingVat: $invoice->getTotalIncludingVat()->toEuros(),
            customerCountry: $invoice->getCustomerCountryCode(),
            customerVatNumber: $invoice->getCustomerVatNumber(),
            paymentStatus: $paymentStatus,
            paymentDate: $this->getPaymentDate($invoice),
            paymentMethod: $this->getPaymentMethod($invoice),
            vatBreakdown: $vatBreakdown,
            invoiceId: null !== $invoice->getId() ? (string) $invoice->getId() : null,
        );
    }

    public function getSummary(
        \DateTimeImmutable $periodStart,
        \DateTimeImmutable $periodEnd,
        ReportingFrequency $frequency,
        array $transactions,
    ): ReportingSummary {
        $totals = $this->calculateTotals($transactions);
        $transactionsByType = $this->countByType($transactions);
        $vatByRate = $this->aggregateVatByRate($transactions);
        $deadline = $this->scheduler->getDeadlineForPeriod($periodEnd);

        return new ReportingSummary(
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            deadline: $deadline,
            frequency: $frequency,
            transactionCount: \count($transactions),
            totalExcludingVat: $totals['excludingVat'],
            totalVat: $totals['vat'],
            totalIncludingVat: $totals['includingVat'],
            transactionsByType: $transactionsByType,
            vatByRate: $vatByRate,
        );
    }

    public function submit(array $transactions): ReportingResult
    {
        if ([] === $transactions) {
            return ReportingResult::failure(
                message: 'Aucune transaction à soumettre',
                errors: ['Le rapport ne contient aucune transaction'],
            );
        }

        // Generate a report ID (in real implementation, this would come from the PDP)
        $reportId = 'RPT-'.date('Y-m-d-His').'-'.bin2hex(random_bytes(4));

        return ReportingResult::success(
            reportId: $reportId,
            transactions: \count($transactions),
            message: 'Rapport e-reporting soumis avec succès',
        );
    }

    public function requiresEReporting(Invoice $invoice): bool
    {
        // Draft invoices don't require e-reporting
        if (InvoiceStatus::DRAFT === $invoice->getStatus()) {
            return false;
        }

        $transactionType = $this->determineTransactionType($invoice);

        return $transactionType->requiresEReporting();
    }

    /**
     * Determine the transaction type based on invoice data.
     */
    private function determineTransactionType(Invoice $invoice): TransactionType
    {
        $countryCode = $invoice->getCustomerCountryCode() ?? 'FR';
        $hasVatNumber = null !== $invoice->getCustomerVatNumber() && '' !== $invoice->getCustomerVatNumber();
        $isB2B = $hasVatNumber;

        // Domestic (France)
        if ('FR' === $countryCode) {
            return $isB2B ? TransactionType::B2B_FRANCE : TransactionType::B2C_FRANCE;
        }

        // Intra-EU
        if (\in_array($countryCode, self::EU_COUNTRY_CODES, true)) {
            return $isB2B ? TransactionType::B2B_INTRA_EU : TransactionType::B2C_INTRA_EU;
        }

        // Export (outside EU)
        return $isB2B ? TransactionType::B2B_EXPORT : TransactionType::B2C_EXPORT;
    }

    /**
     * Determine payment status from invoice status.
     */
    private function determinePaymentStatus(Invoice $invoice): EReportingPaymentStatus
    {
        return match ($invoice->getStatus()) {
            InvoiceStatus::PAID => EReportingPaymentStatus::FULLY_PAID,
            InvoiceStatus::PARTIALLY_PAID => EReportingPaymentStatus::PARTIALLY_PAID,
            default => EReportingPaymentStatus::NOT_PAID,
        };
    }

    /**
     * Calculate VAT breakdown by rate.
     *
     * @return array<string, string>
     */
    private function calculateVatBreakdown(Invoice $invoice): array
    {
        /** @var array<string, string> $breakdown */
        $breakdown = [];

        foreach ($invoice->getLines() as $line) {
            $rate = \sprintf('%.2f', $line->getVatRate());
            $vatAmount = $line->getVatAmount()->toEuros();

            if (!\array_key_exists($rate, $breakdown)) {
                $breakdown[$rate] = '0.00';
            }

            /** @var string $currentValue */
            $currentValue = $breakdown[$rate];
            $breakdown[$rate] = bcadd($currentValue, $vatAmount, 2);
        }

        return $breakdown;
    }

    /**
     * Get payment date if invoice is paid.
     *
     * In a real implementation, this would come from payment records.
     */
    private function getPaymentDate(Invoice $invoice): ?\DateTimeImmutable
    {
        $payments = $invoice->getPayments();

        if (InvoiceStatus::PAID === $invoice->getStatus() && [] !== $payments) {
            // Return the date of the last payment
            $lastPayment = end($payments);

            return $lastPayment->getPaidAt();
        }

        return null;
    }

    /**
     * Get payment method if invoice is paid.
     *
     * In a real implementation, this would come from payment records.
     */
    private function getPaymentMethod(Invoice $invoice): ?string
    {
        $payments = $invoice->getPayments();

        if (InvoiceStatus::PAID === $invoice->getStatus() && [] !== $payments) {
            $lastPayment = end($payments);

            return $lastPayment->getMethod()->value;
        }

        return null;
    }

    /**
     * Calculate totals from transactions.
     *
     * @param EReportingTransaction[] $transactions
     *
     * @return array{excludingVat: string, vat: string, includingVat: string}
     */
    private function calculateTotals(array $transactions): array
    {
        $excludingVat = '0.00';
        $vat = '0.00';
        $includingVat = '0.00';

        foreach ($transactions as $transaction) {
            $excludingVat = bcadd($excludingVat, $transaction->totalExcludingVat, 2);
            $vat = bcadd($vat, $transaction->totalVat, 2);
            $includingVat = bcadd($includingVat, $transaction->totalIncludingVat, 2);
        }

        return [
            'excludingVat' => $excludingVat,
            'vat' => $vat,
            'includingVat' => $includingVat,
        ];
    }

    /**
     * Count transactions by type.
     *
     * @param EReportingTransaction[] $transactions
     *
     * @return array<string, int>
     */
    private function countByType(array $transactions): array
    {
        $counts = [];

        foreach ($transactions as $transaction) {
            $type = $transaction->transactionType->value;
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Aggregate VAT amounts by rate.
     *
     * @param EReportingTransaction[] $transactions
     *
     * @return array<string, string>
     */
    private function aggregateVatByRate(array $transactions): array
    {
        $vatByRate = [];

        foreach ($transactions as $transaction) {
            foreach ($transaction->vatBreakdown as $rate => $amount) {
                $rateKey = (string) $rate;
                if (!isset($vatByRate[$rateKey])) {
                    $vatByRate[$rateKey] = '0.00';
                }
                $vatByRate[$rateKey] = bcadd($vatByRate[$rateKey], $amount, 2);
            }
        }

        return $vatByRate;
    }
}
