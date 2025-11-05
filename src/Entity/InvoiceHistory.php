<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Entity;

use CorentinBoutillier\InvoiceBundle\Enum\InvoiceHistoryAction;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'invoice_history')]
class InvoiceHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Invoice::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Invoice $invoice;

    #[ORM\Column(type: 'string', enumType: InvoiceHistoryAction::class)]
    private InvoiceHistoryAction $action;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $executedAt;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $userId = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    public function __construct(
        Invoice $invoice,
        InvoiceHistoryAction $action,
        \DateTimeImmutable $executedAt,
    ) {
        $this->invoice = $invoice;
        $this->action = $action;
        $this->executedAt = $executedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    public function getAction(): InvoiceHistoryAction
    {
        return $this->action;
    }

    public function getExecutedAt(): \DateTimeImmutable
    {
        return $this->executedAt;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): void
    {
        $this->userId = $userId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function setMetadata(?array $metadata): void
    {
        $this->metadata = $metadata;
    }
}
