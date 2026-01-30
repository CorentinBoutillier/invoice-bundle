<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\EReporting\Dto;

use CorentinBoutillier\InvoiceBundle\EReporting\Enum\EReportingPaymentStatus;
use CorentinBoutillier\InvoiceBundle\EReporting\Enum\TransactionType;

/**
 * Represents a transaction for e-reporting submission.
 *
 * Contains all data required for e-reporting a transaction
 * to the French tax administration via the e-reporting portal.
 */
readonly class EReportingTransaction
{
    /**
     * @param string                   $invoiceNumber     Invoice/document number
     * @param \DateTimeImmutable       $invoiceDate       Invoice date
     * @param TransactionType          $transactionType   Type of transaction (B2C, B2B export, etc.)
     * @param string                   $totalExcludingVat Total HT
     * @param string                   $totalVat          Total VAT
     * @param string                   $totalIncludingVat Total TTC
     * @param string|null              $customerCountry   Customer country code (ISO 3166-1 alpha-2)
     * @param string|null              $customerVatNumber Customer VAT number (if applicable)
     * @param EReportingPaymentStatus  $paymentStatus     Payment status
     * @param \DateTimeImmutable|null  $paymentDate       Date of payment (if paid)
     * @param string|null              $paymentMethod     Payment method
     * @param array<string, string>    $vatBreakdown      VAT breakdown by rate
     * @param string|null              $invoiceId         Internal invoice ID (for reference)
     */
    public function __construct(
        public string $invoiceNumber,
        public \DateTimeImmutable $invoiceDate,
        public TransactionType $transactionType,
        public string $totalExcludingVat,
        public string $totalVat,
        public string $totalIncludingVat,
        public ?string $customerCountry = null,
        public ?string $customerVatNumber = null,
        public EReportingPaymentStatus $paymentStatus = EReportingPaymentStatus::NOT_PAID,
        public ?\DateTimeImmutable $paymentDate = null,
        public ?string $paymentMethod = null,
        public array $vatBreakdown = [],
        public ?string $invoiceId = null,
    ) {
    }

    /**
     * Check if the transaction requires e-reporting.
     */
    public function requiresEReporting(): bool
    {
        return $this->transactionType->requiresEReporting();
    }

    /**
     * Check if the transaction is for an export (outside EU).
     */
    public function isExport(): bool
    {
        return $this->transactionType->isExport();
    }

    /**
     * Check if the transaction is for an intra-EU operation.
     */
    public function isIntraEU(): bool
    {
        return $this->transactionType->isIntraEU();
    }

    /**
     * Check if the transaction is domestic (France).
     */
    public function isDomestic(): bool
    {
        return $this->transactionType->isDomestic();
    }

    /**
     * Check if the transaction is paid.
     */
    public function isPaid(): bool
    {
        return $this->paymentStatus->isPaid();
    }

    /**
     * Create a copy with updated payment information.
     */
    public function withPayment(
        EReportingPaymentStatus $status,
        ?\DateTimeImmutable $date = null,
        ?string $method = null,
    ): self {
        return new self(
            invoiceNumber: $this->invoiceNumber,
            invoiceDate: $this->invoiceDate,
            transactionType: $this->transactionType,
            totalExcludingVat: $this->totalExcludingVat,
            totalVat: $this->totalVat,
            totalIncludingVat: $this->totalIncludingVat,
            customerCountry: $this->customerCountry,
            customerVatNumber: $this->customerVatNumber,
            paymentStatus: $status,
            paymentDate: $date ?? $this->paymentDate,
            paymentMethod: $method ?? $this->paymentMethod,
            vatBreakdown: $this->vatBreakdown,
            invoiceId: $this->invoiceId,
        );
    }
}
