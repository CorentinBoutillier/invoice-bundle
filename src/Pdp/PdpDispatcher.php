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
 * Dispatches PDP operations to the appropriate connector.
 *
 * Central service for managing PDP connectors and routing operations.
 */
final class PdpDispatcher implements PdpDispatcherInterface
{
    /**
     * @var array<string, PdpConnectorInterface>
     */
    private array $connectors = [];

    private ?string $defaultConnectorId = null;

    /**
     * @param iterable<PdpConnectorInterface> $connectors
     */
    public function __construct(
        iterable $connectors = [],
        ?string $defaultConnectorId = null,
    ) {
        foreach ($connectors as $connector) {
            $this->registerConnector($connector);
        }

        $this->defaultConnectorId = $defaultConnectorId;
    }

    /**
     * Register a connector.
     */
    public function registerConnector(PdpConnectorInterface $connector): self
    {
        $this->connectors[$connector->getId()] = $connector;

        return $this;
    }

    /**
     * Get a connector by ID.
     *
     * @throws PdpConnectorNotFoundException
     */
    public function getConnector(string $connectorId): PdpConnectorInterface
    {
        if (!isset($this->connectors[$connectorId])) {
            throw new PdpConnectorNotFoundException($connectorId);
        }

        return $this->connectors[$connectorId];
    }

    /**
     * Get the default connector.
     *
     * @throws PdpConnectorNotFoundException
     */
    public function getDefaultConnector(): PdpConnectorInterface
    {
        if (null === $this->defaultConnectorId) {
            throw new PdpConnectorNotFoundException('default (not configured)');
        }

        return $this->getConnector($this->defaultConnectorId);
    }

    /**
     * Check if a connector exists.
     */
    public function hasConnector(string $connectorId): bool
    {
        return isset($this->connectors[$connectorId]);
    }

    /**
     * Get all registered connector IDs.
     *
     * @return array<string>
     */
    public function getConnectorIds(): array
    {
        return array_keys($this->connectors);
    }

    /**
     * Get all registered connectors.
     *
     * @return array<string, PdpConnectorInterface>
     */
    public function getConnectors(): array
    {
        return $this->connectors;
    }

    /**
     * Get all configured connectors (ready to use).
     *
     * @return array<string, PdpConnectorInterface>
     */
    public function getConfiguredConnectors(): array
    {
        return array_filter(
            $this->connectors,
            fn (PdpConnectorInterface $c) => $c->isConfigured(),
        );
    }

    /**
     * Set the default connector ID.
     */
    public function setDefaultConnectorId(?string $connectorId): self
    {
        $this->defaultConnectorId = $connectorId;

        return $this;
    }

    /**
     * Get the default connector ID.
     */
    public function getDefaultConnectorId(): ?string
    {
        return $this->defaultConnectorId;
    }

    // ========================================
    // Dispatch Methods
    // ========================================

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
    ): TransmissionResult {
        $connector = null !== $connectorId
            ? $this->getConnector($connectorId)
            : $this->getDefaultConnector();

        return $connector->transmit($invoice, $pdfContent, $xmlContent);
    }

    /**
     * Get invoice status from the specified or default connector.
     *
     * @throws PdpConnectorNotFoundException
     * @throws PdpTransmissionException
     */
    public function getStatus(
        string $transmissionId,
        ?string $connectorId = null,
    ): PdpInvoiceStatus {
        $connector = null !== $connectorId
            ? $this->getConnector($connectorId)
            : $this->getDefaultConnector();

        return $connector->getStatus($transmissionId);
    }

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
    ): array {
        $connector = null !== $connectorId
            ? $this->getConnector($connectorId)
            : $this->getDefaultConnector();

        return $connector->getReceivedInvoices($since, $limit);
    }

    /**
     * Perform health check on the specified or default connector.
     *
     * @throws PdpConnectorNotFoundException
     */
    public function healthCheck(?string $connectorId = null): HealthCheckResult
    {
        $connector = null !== $connectorId
            ? $this->getConnector($connectorId)
            : $this->getDefaultConnector();

        return $connector->healthCheck();
    }

    /**
     * Perform health check on all registered connectors.
     *
     * @return array<string, HealthCheckResult>
     */
    public function healthCheckAll(): array
    {
        $results = [];

        foreach ($this->connectors as $id => $connector) {
            $results[$id] = $connector->healthCheck();
        }

        return $results;
    }
}
