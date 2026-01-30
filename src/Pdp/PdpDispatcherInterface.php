<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Pdp;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\HealthCheckResult;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\PdpInvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\ReceivedInvoice;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\TransmissionResult;
use CorentinBoutillier\InvoiceBundle\Pdp\Exception\PdpConnectorNotFoundException;
use CorentinBoutillier\InvoiceBundle\Pdp\Exception\PdpTransmissionException;

/**
 * Interface for PDP dispatcher operations.
 */
interface PdpDispatcherInterface
{
    /**
     * Register a connector.
     */
    public function registerConnector(PdpConnectorInterface $connector): self;

    /**
     * Get a connector by ID.
     *
     * @throws PdpConnectorNotFoundException
     */
    public function getConnector(string $connectorId): PdpConnectorInterface;

    /**
     * Get the default connector.
     *
     * @throws PdpConnectorNotFoundException
     */
    public function getDefaultConnector(): PdpConnectorInterface;

    /**
     * Check if a connector exists.
     */
    public function hasConnector(string $connectorId): bool;

    /**
     * Get all registered connector IDs.
     *
     * @return array<string>
     */
    public function getConnectorIds(): array;

    /**
     * Get all registered connectors.
     *
     * @return array<string, PdpConnectorInterface>
     */
    public function getConnectors(): array;

    /**
     * Transmit an invoice using the specified or default connector.
     *
     * @throws PdpConnectorNotFoundException
     * @throws PdpTransmissionException
     */
    public function transmit(
        Invoice $invoice,
        ?string $connectorId = null,
        ?string $pdfContent = null,
        ?string $xmlContent = null,
    ): TransmissionResult;

    /**
     * Get invoice status from the specified or default connector.
     *
     * @throws PdpConnectorNotFoundException
     * @throws PdpTransmissionException
     */
    public function getStatus(
        string $transmissionId,
        ?string $connectorId = null,
    ): PdpInvoiceStatus;

    /**
     * Get received invoices from the specified or default connector.
     *
     * @return array<ReceivedInvoice>
     *
     * @throws PdpConnectorNotFoundException
     * @throws PdpTransmissionException
     */
    public function getReceivedInvoices(
        ?string $connectorId = null,
        ?\DateTimeInterface $since = null,
        int $limit = 100,
    ): array;

    /**
     * Perform health check on the specified or default connector.
     *
     * @throws PdpConnectorNotFoundException
     */
    public function healthCheck(?string $connectorId = null): HealthCheckResult;
}
