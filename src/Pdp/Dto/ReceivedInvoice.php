<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Pdp\Dto;

/**
 * Invoice received from a supplier through PDP.
 *
 * Contains the raw invoice data and metadata from the PDP.
 */
readonly class ReceivedInvoice
{
    /**
     * @param string              $transmissionId    PDP tracking ID
     * @param string              $invoiceNumber     Supplier's invoice number
     * @param \DateTimeImmutable  $invoiceDate       Invoice issue date
     * @param string              $supplierName      Supplier company name
     * @param string|null         $supplierSiret     Supplier SIRET
     * @param string|null         $supplierVatNumber Supplier VAT number
     * @param int                 $totalAmountCents  Total TTC in cents
     * @param int                 $vatAmountCents    Total VAT in cents
     * @param string|null         $pdfContent        PDF binary content (base64 decoded)
     * @param string|null         $xmlContent        Factur-X XML content
     * @param \DateTimeImmutable  $receivedAt        When invoice was received
     * @param array<string, mixed> $metadata         Additional PDP-specific data
     */
    public function __construct(
        public string $transmissionId,
        public string $invoiceNumber,
        public \DateTimeImmutable $invoiceDate,
        public string $supplierName,
        public ?string $supplierSiret = null,
        public ?string $supplierVatNumber = null,
        public int $totalAmountCents = 0,
        public int $vatAmountCents = 0,
        public ?string $pdfContent = null,
        public ?string $xmlContent = null,
        public ?\DateTimeImmutable $receivedAt = null,
        public array $metadata = [],
    ) {
    }

    /**
     * Get total amount in euros as string (2 decimals).
     */
    public function getTotalAmountEuros(): string
    {
        return number_format($this->totalAmountCents / 100, 2, '.', '');
    }

    /**
     * Get VAT amount in euros as string (2 decimals).
     */
    public function getVatAmountEuros(): string
    {
        return number_format($this->vatAmountCents / 100, 2, '.', '');
    }

    /**
     * Get HT amount in cents.
     */
    public function getNetAmountCents(): int
    {
        return $this->totalAmountCents - $this->vatAmountCents;
    }

    /**
     * Check if PDF content is available.
     */
    public function hasPdf(): bool
    {
        return null !== $this->pdfContent && '' !== $this->pdfContent;
    }

    /**
     * Check if Factur-X XML content is available.
     */
    public function hasXml(): bool
    {
        return null !== $this->xmlContent && '' !== $this->xmlContent;
    }
}
