<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Entity;

use CorentinBoutillier\InvoiceBundle\Enum\LettrageEntryType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Lettrage comptable.
 *
 * Représente le rapprochement entre des factures (débit) et des paiements (crédit).
 * Un lettrage est équilibré quand la somme des débits égale la somme des crédits.
 *
 * Le code de lettrage est persisté en base pour garantir la cohérence
 * lors de l'export FEC sur plusieurs exercices fiscaux.
 *
 * Supporte le soft delete pour préserver l'audit trail.
 */
#[ORM\Entity]
#[ORM\Table(name: 'lettrage')]
#[ORM\Index(name: 'idx_lettrage_company', columns: ['company_id'])]
#[ORM\Index(name: 'idx_lettrage_code', columns: ['code'])]
class Lettrage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 10)]
    private string $code;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $date;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $companyId;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $deletionReason = null;

    /**
     * @var Collection<int, LettrageEntry>
     */
    #[ORM\OneToMany(
        targetEntity: LettrageEntry::class,
        mappedBy: 'lettrage',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $entries;

    public function __construct(
        string $code,
        \DateTimeImmutable $date,
        ?int $companyId,
    ) {
        $this->code = $code;
        $this->date = $date;
        $this->companyId = $companyId;
        $this->entries = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getCompanyId(): ?int
    {
        return $this->companyId;
    }

    /**
     * @return Collection<int, LettrageEntry>
     */
    public function getEntries(): Collection
    {
        return $this->entries;
    }

    public function addEntry(LettrageEntry $entry): self
    {
        if (!$this->entries->contains($entry)) {
            $this->entries->add($entry);
        }

        return $this;
    }

    public function removeEntry(LettrageEntry $entry): self
    {
        $this->entries->removeElement($entry);

        return $this;
    }

    /**
     * Vérifie si le lettrage est équilibré.
     *
     * Un lettrage est équilibré quand la somme des débits égale la somme des crédits.
     */
    public function isBalanced(): bool
    {
        return $this->getTotalDebitCents() === $this->getTotalCreditCents();
    }

    /**
     * Retourne le total des montants au débit (en centimes).
     */
    public function getTotalDebitCents(): int
    {
        $total = 0;

        foreach ($this->entries as $entry) {
            if (LettrageEntryType::DEBIT === $entry->getType()) {
                $total += $entry->getAmountCents();
            }
        }

        return $total;
    }

    /**
     * Retourne le total des montants au crédit (en centimes).
     */
    public function getTotalCreditCents(): int
    {
        $total = 0;

        foreach ($this->entries as $entry) {
            if (LettrageEntryType::CREDIT === $entry->getType()) {
                $total += $entry->getAmountCents();
            }
        }

        return $total;
    }

    /**
     * Indique si le lettrage a été supprimé (soft delete).
     */
    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function getDeletionReason(): ?string
    {
        return $this->deletionReason;
    }

    /**
     * Supprime le lettrage de manière logique (soft delete).
     *
     * Préserve l'audit trail en conservant l'enregistrement en base.
     */
    public function softDelete(\DateTimeImmutable $deletedAt, ?string $reason = null): self
    {
        $this->deletedAt = $deletedAt;
        $this->deletionReason = $reason;

        return $this;
    }
}
