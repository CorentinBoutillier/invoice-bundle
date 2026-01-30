<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Pdp;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\HealthCheckResult;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\PdpInvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\ReceivedInvoice;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\TransmissionResult;
use CorentinBoutillier\InvoiceBundle\Pdp\Exception\PdpTransmissionException;

/**
 * Interface for PDP (Plateforme de Dématérialisation Partenaire) connectors.
 *
 * Each connector implementation handles communication with a specific PDP
 * platform (Pennylane, Chorus Pro, etc.) for the French e-invoicing reform.
 */
interface PdpConnectorInterface
{
    /**
     * Get the unique identifier for this connector.
     *
     * @return string Connector ID (e.g., 'pennylane', 'chorus_pro')
     */
    public function getId(): string;

    /**
     * Get the human-readable name for this connector.
     */
    public function getName(): string;

    /**
     * Get the capabilities supported by this connector.
     *
     * @return array<PdpCapability>
     */
    public function getCapabilities(): array;

    /**
     * Check if this connector supports a specific capability.
     */
    public function supports(PdpCapability $capability): bool;

    /**
     * Transmit an invoice to the PDP.
     *
     * @param Invoice     $invoice    The finalized invoice to transmit
     * @param string|null $pdfContent Optional PDF content (if not stored)
     * @param string|null $xmlContent Optional Factur-X XML content
     *
     * @throws PdpTransmissionException if transmission fails
     */
    public function transmit(
        Invoice $invoice,
        ?string $pdfContent = null,
        ?string $xmlContent = null,
    ): TransmissionResult;

    /**
     * Get the current status of a transmitted invoice.
     *
     * @param string $transmissionId The PDP-assigned transmission ID
     *
     * @throws PdpTransmissionException if status check fails
     */
    public function getStatus(string $transmissionId): PdpInvoiceStatus;

    /**
     * Retrieve received invoices (from suppliers).
     *
     * @param \DateTimeInterface|null $since Only invoices received after this date
     * @param int                     $limit Maximum number of invoices to retrieve
     *
     * @return array<ReceivedInvoice>
     *
     * @throws PdpTransmissionException if retrieval fails
     */
    public function getReceivedInvoices(
        ?\DateTimeInterface $since = null,
        int $limit = 100,
    ): array;

    /**
     * Perform a health check on the PDP connection.
     */
    public function healthCheck(): HealthCheckResult;

    /**
     * Check if the connector is properly configured.
     */
    public function isConfigured(): bool;
}
