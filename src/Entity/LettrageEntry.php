<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Entity;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Enum\LettrageEntryType;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entrée dans un lettrage comptable.
 *
 * Représente le lien entre un lettrage et soit une facture (débit)
 * soit un paiement (crédit), avec le montant lettré.
 *
 * Pour le lettrage partiel, amountCents peut être inférieur au montant
 * total de la facture ou du paiement.
 */
#[ORM\Entity]
#[ORM\Table(name: 'lettrage_entry')]
#[ORM\Index(name: 'idx_lettrage_entry_invoice', columns: ['invoice_id'])]
#[ORM\Index(name: 'idx_lettrage_entry_payment', columns: ['payment_id'])]
class LettrageEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Lettrage::class, inversedBy: 'entries')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Lettrage $lettrage;

    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'lettrageEntries')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Invoice $invoice = null;

    #[ORM\ManyToOne(targetEntity: Payment::class, inversedBy: 'lettrageEntries')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Payment $payment = null;

    #[ORM\Column(type: 'integer')]
    private int $amountCents;

    #[ORM\Column(type: 'string', enumType: LettrageEntryType::class)]
    private LettrageEntryType $type;

    /**
     * @throws \InvalidArgumentException Si invoice et payment sont tous deux null ou tous deux définis
     * @throws \InvalidArgumentException Si amountCents est <= 0
     */
    public function __construct(
        Lettrage $lettrage,
        ?Invoice $invoice,
        ?Payment $payment,
        int $amountCents,
        LettrageEntryType $type,
    ) {
        // Validation : exactement un des deux doit être défini
        if (null !== $invoice && null !== $payment) {
            throw new \InvalidArgumentException('LettrageEntry must have either an invoice OR a payment, not both');
        }

        if (null === $invoice && null === $payment) {
            throw new \InvalidArgumentException('LettrageEntry must have either an invoice OR a payment');
        }

        // Validation : montant positif
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        $this->lettrage = $lettrage;
        $this->invoice = $invoice;
        $this->payment = $payment;
        $this->amountCents = $amountCents;
        $this->type = $type;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLettrage(): Lettrage
    {
        return $this->lettrage;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    /**
     * Retourne le montant lettré en centimes.
     */
    public function getAmountCents(): int
    {
        return $this->amountCents;
    }

    /**
     * Retourne le montant lettré sous forme d'objet Money.
     */
    public function getAmount(): Money
    {
        return Money::fromCents($this->amountCents);
    }

    public function getType(): LettrageEntryType
    {
        return $this->type;
    }
}
