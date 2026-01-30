<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Entity;

use CorentinBoutillier\InvoiceBundle\Pdp\PdpStatusCode;
use CorentinBoutillier\InvoiceBundle\Repository\InvoiceTransmissionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tracks the transmission of an invoice to a PDP (Plateforme de Dématérialisation Partenaire).
 *
 * Records the lifecycle of an invoice transmission including status changes,
 * retry attempts, and any errors encountered during the process.
 */
#[ORM\Entity(repositoryClass: InvoiceTransmissionRepository::class)]
#[ORM\Table(name: 'invoice_transmission')]
#[ORM\Index(columns: ['connector_id'], name: 'idx_transmission_connector')]
#[ORM\Index(columns: ['transmission_id'], name: 'idx_transmission_pdp_id')]
#[ORM\Index(columns: ['status'], name: 'idx_transmission_status')]
class InvoiceTransmission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Invoice::class)]
    #[ORM\JoinColumn(name: 'invoice_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Invoice $invoice;

    #[ORM\Column(name: 'connector_id', type: Types::STRING, length: 50)]
    private string $connectorId;

    #[ORM\Column(name: 'transmission_id', type: Types::STRING, length: 100, nullable: true)]
    private ?string $transmissionId = null;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: PdpStatusCode::class)]
    private PdpStatusCode $status;

    #[ORM\Column(name: 'status_message', type: Types::TEXT, nullable: true)]
    private ?string $statusMessage = null;

    #[ORM\Column(name: 'status_updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $statusUpdatedAt = null;

    /**
     * @var array<int, array{status: string, message: ?string, timestamp: string}>
     */
    #[ORM\Column(name: 'status_history', type: Types::JSON)]
    private array $statusHistory = [];

    /**
     * @var array<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $errors = [];

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $metadata = [];

    #[ORM\Column(name: 'retry_count', type: Types::INTEGER)]
    private int $retryCount = 0;

    #[ORM\Column(name: 'last_retry_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastRetryAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Invoice $invoice,
        string $connectorId,
        ?string $transmissionId = null,
        PdpStatusCode $status = PdpStatusCode::PENDING,
    ) {
        $this->invoice = $invoice;
        $this->connectorId = $connectorId;
        $this->transmissionId = $transmissionId;
        $this->status = $status;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    public function getConnectorId(): string
    {
        return $this->connectorId;
    }

    public function getTransmissionId(): ?string
    {
        return $this->transmissionId;
    }

    public function setTransmissionId(?string $transmissionId): self
    {
        $this->transmissionId = $transmissionId;

        return $this;
    }

    public function getStatus(): PdpStatusCode
    {
        return $this->status;
    }

    public function getStatusMessage(): ?string
    {
        return $this->statusMessage;
    }

    public function getStatusUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->statusUpdatedAt;
    }

    /**
     * Update the transmission status and record in history.
     */
    public function updateStatus(PdpStatusCode $status, ?string $message = null): self
    {
        $this->status = $status;
        $this->statusMessage = $message;
        $this->statusUpdatedAt = new \DateTimeImmutable();

        $this->statusHistory[] = [
            'status' => $status->value,
            'message' => $message,
            'timestamp' => $this->statusUpdatedAt->format(\DateTimeInterface::ATOM),
        ];

        return $this;
    }

    /**
     * @return array<int, array{status: string, message: ?string, timestamp: string}>
     */
    public function getStatusHistory(): array
    {
        return $this->statusHistory;
    }

    /**
     * Mark the transmission as failed with error details.
     *
     * @param array<string> $errors
     */
    public function markAsFailed(string $message, array $errors = []): self
    {
        $this->errors = $errors;

        return $this->updateStatus(PdpStatusCode::FAILED, $message);
    }

    /**
     * Mark the transmission as rejected.
     */
    public function markAsRejected(string $message): self
    {
        return $this->updateStatus(PdpStatusCode::REJECTED, $message);
    }

    /**
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function incrementRetryCount(): self
    {
        ++$this->retryCount;
        $this->lastRetryAt = new \DateTimeImmutable();

        return $this;
    }

    public function getLastRetryAt(): ?\DateTimeImmutable
    {
        return $this->lastRetryAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Check if the transmission is in a terminal state (no more changes expected).
     */
    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * Check if the transmission is still pending.
     */
    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    /**
     * Check if the transmission was successful (any positive status).
     */
    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }

    /**
     * Check if the transmission failed.
     */
    public function isFailed(): bool
    {
        return $this->status->isFailure();
    }
}
