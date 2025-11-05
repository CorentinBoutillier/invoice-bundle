<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Entity;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Enum\PaymentMethod;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'payment')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
#[ORM\DiscriminatorMap(['payment' => self::class])]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private int $amountCents;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $paidAt;

    #[ORM\Column(type: 'string', enumType: PaymentMethod::class)]
    private PaymentMethod $method;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function __construct(
        Money $amount,
        \DateTimeImmutable $paidAt,
        PaymentMethod $method,
    ) {
        $this->amountCents = $amount->getAmount();
        $this->paidAt = $paidAt;
        $this->method = $method;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAmount(): Money
    {
        return Money::fromCents($this->amountCents);
    }

    public function setAmount(Money $money): void
    {
        $this->amountCents = $money->getAmount();
    }

    public function getPaidAt(): \DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(\DateTimeImmutable $paidAt): void
    {
        $this->paidAt = $paidAt;
    }

    public function getMethod(): PaymentMethod
    {
        return $this->method;
    }

    public function setMethod(PaymentMethod $method): void
    {
        $this->method = $method;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): void
    {
        $this->reference = $reference;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }
}
