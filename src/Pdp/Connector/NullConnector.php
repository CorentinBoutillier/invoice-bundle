<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Pdp\Connector;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\HealthCheckResult;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\PdpInvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\ReceivedInvoice;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\TransmissionResult;
use CorentinBoutillier\InvoiceBundle\Pdp\PdpCapability;
use CorentinBoutillier\InvoiceBundle\Pdp\PdpConnectorInterface;
use CorentinBoutillier\InvoiceBundle\Pdp\PdpStatusCode;

/**
 * Null connector for testing and fallback mode.
 *
 * This connector simulates PDP operations without making any real API calls.
 * Useful for:
 * - Unit/functional tests
 * - Development without PDP access
 * - Graceful degradation when PDP is unavailable
 */
final class NullConnector implements PdpConnectorInterface
{
    private const CONNECTOR_ID = 'null';
    private const CONNECTOR_NAME = 'Null Connector (Test/Fallback)';

    /**
     * @var array<string, PdpInvoiceStatus> Simulated status storage
     */
    private array $statuses = [];

    /**
     * @var array<ReceivedInvoice> Simulated received invoices
     */
    private array $receivedInvoices = [];

    private bool $simulateFailure = false;
    private ?string $failureMessage = null;

    public function getId(): string
    {
        return self::CONNECTOR_ID;
    }

    public function getName(): string
    {
        return self::CONNECTOR_NAME;
    }

    public function getCapabilities(): array
    {
        return [
            PdpCapability::TRANSMIT,
            PdpCapability::STATUS,
            PdpCapability::RECEIVE,
            PdpCapability::HEALTH_CHECK,
        ];
    }

    public function supports(PdpCapability $capability): bool
    {
        return \in_array($capability, $this->getCapabilities(), true);
    }

    public function transmit(
        Invoice $invoice,
        ?string $pdfContent = null,
        ?string $xmlContent = null,
    ): TransmissionResult {
        if ($this->simulateFailure) {
            return TransmissionResult::failure(
                message: $this->failureMessage ?? 'Simulated failure',
                status: PdpStatusCode::FAILED,
            );
        }

        $transmissionId = 'NULL-'.($invoice->getNumber() ?? uniqid());

        // Store status for later retrieval
        $this->statuses[$transmissionId] = new PdpInvoiceStatus(
            transmissionId: $transmissionId,
            status: PdpStatusCode::SUBMITTED,
            statusAt: new \DateTimeImmutable(),
            message: 'Simulated submission',
        );

        return TransmissionResult::success(
            transmissionId: $transmissionId,
            message: 'Invoice submitted (simulated)',
            status: PdpStatusCode::SUBMITTED,
            metadata: [
                'connector' => self::CONNECTOR_ID,
                'simulated' => true,
            ],
        );
    }

    public function getStatus(string $transmissionId): PdpInvoiceStatus
    {
        if (isset($this->statuses[$transmissionId])) {
            return $this->statuses[$transmissionId];
        }

        // Return a default status for unknown transmissions
        return new PdpInvoiceStatus(
            transmissionId: $transmissionId,
            status: PdpStatusCode::PENDING,
            statusAt: new \DateTimeImmutable(),
            message: 'Status not found (simulated)',
        );
    }

    public function getReceivedInvoices(
        ?\DateTimeInterface $since = null,
        int $limit = 100,
    ): array {
        $invoices = $this->receivedInvoices;

        if (null !== $since) {
            $invoices = array_filter(
                $invoices,
                fn (ReceivedInvoice $inv) => null !== $inv->receivedAt
                    && $inv->receivedAt > $since,
            );
        }

        return \array_slice($invoices, 0, $limit);
    }

    public function healthCheck(): HealthCheckResult
    {
        if ($this->simulateFailure) {
            return HealthCheckResult::unhealthy(
                connectorId: self::CONNECTOR_ID,
                message: $this->failureMessage ?? 'Simulated unhealthy state',
            );
        }

        return HealthCheckResult::healthy(
            connectorId: self::CONNECTOR_ID,
            responseTimeMs: 0.1,
            version: '1.0.0-null',
            details: ['simulated' => true],
        );
    }

    public function isConfigured(): bool
    {
        // NullConnector is always "configured"
        return true;
    }

    // ========================================
    // Test Helper Methods
    // ========================================

    /**
     * Set simulation to return failures.
     */
    public function simulateFailure(bool $simulate = true, ?string $message = null): self
    {
        $this->simulateFailure = $simulate;
        $this->failureMessage = $message;

        return $this;
    }

    /**
     * Add a simulated received invoice.
     */
    public function addReceivedInvoice(ReceivedInvoice $invoice): self
    {
        $this->receivedInvoices[] = $invoice;

        return $this;
    }

    /**
     * Set status for a transmission (for testing status retrieval).
     */
    public function setStatus(string $transmissionId, PdpInvoiceStatus $status): self
    {
        $this->statuses[$transmissionId] = $status;

        return $this;
    }

    /**
     * Reset all simulated state.
     */
    public function reset(): self
    {
        $this->statuses = [];
        $this->receivedInvoices = [];
        $this->simulateFailure = false;
        $this->failureMessage = null;

        return $this;
    }
}
