<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Entity;

use CorentinBoutillier\InvoiceBundle\Repository\LettrageSequenceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Séquence de numérotation des codes de lettrage.
 *
 * Gère la génération séquentielle des codes de lettrage par entreprise.
 * La séquence est globale par entreprise (pas par exercice fiscal).
 * Les codes sont des entiers convertis ensuite en codes alphabétiques
 * (A, B, C..., Z, AA, AB...) par le service LettrageCodeGenerator.
 */
#[ORM\Entity(repositoryClass: LettrageSequenceRepository::class)]
#[ORM\Table(name: 'lettrage_sequence')]
#[ORM\UniqueConstraint(name: 'unique_lettrage_sequence', columns: ['company_id'])]
class LettrageSequence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $companyId;

    #[ORM\Column(type: 'integer')]
    private int $lastCode = 0;

    public function __construct(?int $companyId)
    {
        $this->companyId = $companyId;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompanyId(): ?int
    {
        return $this->companyId;
    }

    /**
     * Retourne le dernier code utilisé.
     */
    public function getLastCode(): int
    {
        return $this->lastCode;
    }

    /**
     * Retourne le prochain code à utiliser (sans modifier lastCode).
     */
    public function getNextCode(): int
    {
        return $this->lastCode + 1;
    }

    /**
     * Incrémente le compteur de code après utilisation.
     *
     * @return $this
     */
    public function incrementLastCode(): self
    {
        ++$this->lastCode;

        return $this;
    }

    /**
     * Définit le dernier code (utile pour les tests).
     */
    public function setLastCode(int $lastCode): void
    {
        $this->lastCode = $lastCode;
    }
}
