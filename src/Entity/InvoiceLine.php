<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Entity;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Enum\QuantityUnitCode;
use CorentinBoutillier\InvoiceBundle\Enum\TaxCategoryCode;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'invoice_line')]
class InvoiceLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $description;

    #[ORM\Column(type: 'float')]
    private float $quantity;

    #[ORM\Column(type: 'integer')]
    private int $unitPriceAmount; // Stocké en centimes

    #[ORM\Column(type: 'float')]
    private float $vatRate;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $discountRate = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $discountAmountCents = null;

    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Invoice $invoice = null;

    // ========== Factur-X EN16931 Fields ==========

    #[ORM\Column(type: 'string', length: 10, enumType: QuantityUnitCode::class)]
    private QuantityUnitCode $quantityUnit = QuantityUnitCode::HOUR;

    #[ORM\Column(type: 'string', length: 5, enumType: TaxCategoryCode::class)]
    private TaxCategoryCode $taxCategoryCode = TaxCategoryCode::STANDARD;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $itemIdentifier = null;  // BT-128: Seller item identifier (SKU/GTIN)

    #[ORM\Column(type: 'string', length: 2, nullable: true)]
    private ?string $countryOfOrigin = null;  // BT-134: ISO 3166-1 alpha-2

    public function __construct(
        string $description,
        float $quantity,
        Money $unitPrice,
        float $vatRate,
        QuantityUnitCode $quantityUnit = QuantityUnitCode::HOUR,
        TaxCategoryCode $taxCategoryCode = TaxCategoryCode::STANDARD,
    ) {
        $this->description = $description;
        $this->quantity = $quantity;
        $this->unitPriceAmount = $unitPrice->getAmount();
        $this->vatRate = $vatRate;
        $this->quantityUnit = $quantityUnit;
        $this->taxCategoryCode = $taxCategoryCode;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function setQuantity(float $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getUnitPrice(): Money
    {
        return Money::fromCents($this->unitPriceAmount);
    }

    public function setUnitPrice(Money $money): void
    {
        $this->unitPriceAmount = $money->getAmount();
    }

    public function getVatRate(): float
    {
        return $this->vatRate;
    }

    public function setVatRate(float $vatRate): void
    {
        $this->vatRate = $vatRate;
    }

    public function getDiscountRate(): ?float
    {
        return $this->discountRate;
    }

    public function setDiscountRate(?float $discountRate): void
    {
        $this->discountRate = $discountRate;
    }

    public function getDiscountAmount(): ?Money
    {
        if (null === $this->discountAmountCents) {
            return null;
        }

        return Money::fromCents($this->discountAmountCents);
    }

    public function setDiscountAmount(?Money $money): void
    {
        $this->discountAmountCents = null === $money ? null : $money->getAmount();
    }

    public function getUnitPriceAfterDiscount(): Money
    {
        $unitPrice = $this->getUnitPrice();

        // Priorité 1: Remise fixe (montant en euros)
        if (null !== $this->discountAmountCents) {
            $discount = Money::fromCents($this->discountAmountCents);

            return $unitPrice->subtract($discount);
        }

        // Priorité 2: Remise en pourcentage
        if (null !== $this->discountRate) {
            $discountMultiplier = $this->discountRate / 100;
            $discount = $unitPrice->multiply($discountMultiplier);

            return $unitPrice->subtract($discount);
        }

        // Pas de remise
        return $unitPrice;
    }

    public function getTotalBeforeVat(): Money
    {
        return $this->getUnitPriceAfterDiscount()->multiply($this->quantity);
    }

    public function getVatAmount(): Money
    {
        $totalBeforeVat = $this->getTotalBeforeVat();
        $vatMultiplier = $this->vatRate / 100;

        return $totalBeforeVat->multiply($vatMultiplier);
    }

    public function getTotalIncludingVat(): Money
    {
        $totalBeforeVat = $this->getTotalBeforeVat();
        $vatAmount = $this->getVatAmount();

        return $totalBeforeVat->add($vatAmount);
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): void
    {
        $this->invoice = $invoice;
    }

    // ========== Factur-X EN16931 Getters/Setters ==========

    public function getQuantityUnit(): QuantityUnitCode
    {
        return $this->quantityUnit;
    }

    public function setQuantityUnit(QuantityUnitCode $quantityUnit): void
    {
        $this->quantityUnit = $quantityUnit;
    }

    public function getTaxCategoryCode(): TaxCategoryCode
    {
        return $this->taxCategoryCode;
    }

    public function setTaxCategoryCode(TaxCategoryCode $taxCategoryCode): void
    {
        $this->taxCategoryCode = $taxCategoryCode;
    }

    public function getItemIdentifier(): ?string
    {
        return $this->itemIdentifier;
    }

    public function setItemIdentifier(?string $itemIdentifier): void
    {
        $this->itemIdentifier = $itemIdentifier;
    }

    public function getCountryOfOrigin(): ?string
    {
        return $this->countryOfOrigin;
    }

    public function setCountryOfOrigin(?string $countryOfOrigin): void
    {
        $this->countryOfOrigin = $countryOfOrigin;
    }
}
