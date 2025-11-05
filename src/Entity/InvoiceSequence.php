<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Entity;

use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Repository\InvoiceSequenceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceSequenceRepository::class)]
#[ORM\Table(name: 'invoice_sequence')]
#[ORM\UniqueConstraint(name: 'unique_sequence', columns: ['company_id', 'fiscal_year', 'type'])]
class InvoiceSequence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(type: 'integer', nullable: true)]
        private ?int $companyId,
        #[ORM\Column(type: 'integer')]
        private int $fiscalYear,
        #[ORM\Column(type: 'string', enumType: InvoiceType::class)]
        private InvoiceType $type,
        #[ORM\Column(type: 'datetime_immutable')]
        private \DateTimeImmutable $startDate,
        #[ORM\Column(type: 'datetime_immutable')]
        private \DateTimeImmutable $endDate,
        #[ORM\Column(type: 'integer')]
        private int $lastNumber = 0,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompanyId(): ?int
    {
        return $this->companyId;
    }

    public function getFiscalYear(): int
    {
        return $this->fiscalYear;
    }

    public function getType(): InvoiceType
    {
        return $this->type;
    }

    public function getLastNumber(): int
    {
        return $this->lastNumber;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function getEndDate(): \DateTimeImmutable
    {
        return $this->endDate;
    }

    public function getNextNumber(): int
    {
        return $this->lastNumber + 1;
    }

    public function incrementLastNumber(): void
    {
        ++$this->lastNumber;
    }

    /**
     * Set the last number (useful for testing).
     */
    public function setLastNumber(int $lastNumber): void
    {
        $this->lastNumber = $lastNumber;
    }

    public function containsDate(\DateTimeImmutable $date): bool
    {
        return $date >= $this->startDate && $date <= $this->endDate;
    }
}
