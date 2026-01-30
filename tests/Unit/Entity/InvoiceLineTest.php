<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Entity;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;
use CorentinBoutillier\InvoiceBundle\Enum\QuantityUnitCode;
use CorentinBoutillier\InvoiceBundle\Enum\TaxCategoryCode;
use PHPUnit\Framework\TestCase;

final class InvoiceLineTest extends TestCase
{
    // ========== Construction & Basic Properties ==========

    public function testConstructWithAllBasicProperties(): void
    {
        $unitPrice = Money::fromEuros('10.00');

        $line = new InvoiceLine(
            description: 'Prestation de développement',
            quantity: 2.0,
            unitPrice: $unitPrice,
            vatRate: 20.0,
        );

        $this->assertInstanceOf(InvoiceLine::class, $line);
        $this->assertSame('Prestation de développement', $line->getDescription());
        $this->assertSame(2.0, $line->getQuantity());
        $this->assertSame(20.0, $line->getVatRate());
    }

    public function testUnitPriceReturnsMoneyObject(): void
    {
        $unitPrice = Money::fromEuros('50.00');

        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: $unitPrice,
            vatRate: 20.0,
        );

        $returnedPrice = $line->getUnitPrice();

        $this->assertInstanceOf(Money::class, $returnedPrice);
        $this->assertTrue($returnedPrice->equals($unitPrice));
    }

    public function testSetUnitPriceWithMoneyObject(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('10.00'),
            vatRate: 20.0,
        );

        $newPrice = Money::fromEuros('25.00');
        $line->setUnitPrice($newPrice);

        $this->assertTrue($line->getUnitPrice()->equals($newPrice));
    }

    // ========== Simple Total Calculation (no discounts) ==========

    public function testGetTotalBeforeVatWithIntegerQuantity(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 2.0,
            unitPrice: Money::fromEuros('10.00'),
            vatRate: 20.0,
        );

        $total = $line->getTotalBeforeVat();

        $this->assertInstanceOf(Money::class, $total);
        $this->assertSame('20.00', $total->toEuros());
    }

    public function testGetTotalBeforeVatWithSingleQuantity(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $total = $line->getTotalBeforeVat();

        $this->assertSame('100.00', $total->toEuros());
    }

    public function testGetTotalBeforeVatWithMultipleQuantities(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 10.0,
            unitPrice: Money::fromEuros('15.50'),
            vatRate: 20.0,
        );

        $total = $line->getTotalBeforeVat();

        // 10 × 15.50 = 155.00
        $this->assertSame('155.00', $total->toEuros());
    }

    // ========== Decimal Quantities ==========

    public function testGetTotalBeforeVatWithDecimalQuantity(): void
    {
        $line = new InvoiceLine(
            description: 'Heures de consulting',
            quantity: 2.5,
            unitPrice: Money::fromEuros('80.00'),
            vatRate: 20.0,
        );

        $total = $line->getTotalBeforeVat();

        // 2.5 × 80.00 = 200.00
        $this->assertSame('200.00', $total->toEuros());
    }

    public function testGetTotalBeforeVatWithComplexDecimalQuantity(): void
    {
        $line = new InvoiceLine(
            description: 'Heures',
            quantity: 3.75,
            unitPrice: Money::fromEuros('60.00'),
            vatRate: 20.0,
        );

        $total = $line->getTotalBeforeVat();

        // 3.75 × 60.00 = 225.00
        $this->assertSame('225.00', $total->toEuros());
    }

    // ========== Rounding Behavior ==========

    public function testGetTotalBeforeVatRoundsCorrectly(): void
    {
        $line = new InvoiceLine(
            description: 'Test arrondi',
            quantity: 3.0,
            unitPrice: Money::fromEuros('10.33'),
            vatRate: 20.0,
        );

        $total = $line->getTotalBeforeVat();

        // 3 × 10.33 = 30.99
        $this->assertSame('30.99', $total->toEuros());
    }

    public function testGetTotalBeforeVatWithRoundingUpNeeded(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 3.0,
            unitPrice: Money::fromEuros('10.67'),
            vatRate: 20.0,
        );

        $total = $line->getTotalBeforeVat();

        // 3 × 10.67 = 32.01
        $this->assertSame('32.01', $total->toEuros());
    }

    // ========== Immutability ==========

    public function testGetTotalBeforeVatDoesNotModifyOriginalPrice(): void
    {
        $originalPrice = Money::fromEuros('50.00');

        $line = new InvoiceLine(
            description: 'Test',
            quantity: 3.0,
            unitPrice: $originalPrice,
            vatRate: 20.0,
        );

        $total = $line->getTotalBeforeVat();

        // Le prix original ne doit pas avoir changé
        $this->assertSame('50.00', $originalPrice->toEuros());
        $this->assertSame('150.00', $total->toEuros());

        // Vérifier que ce sont des instances différentes
        $this->assertNotSame($originalPrice, $total);
    }

    public function testMultipleCallsToGetTotalReturnSameValue(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 2.0,
            unitPrice: Money::fromEuros('25.00'),
            vatRate: 20.0,
        );

        $total1 = $line->getTotalBeforeVat();
        $total2 = $line->getTotalBeforeVat();

        $this->assertTrue($total1->equals($total2));
    }

    // ========== Zero and Negative Edge Cases ==========

    public function testGetTotalBeforeVatWithZeroQuantity(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 0.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $total = $line->getTotalBeforeVat();

        $this->assertSame('0.00', $total->toEuros());
        $this->assertTrue($total->isZero());
    }

    public function testGetTotalBeforeVatWithZeroPrice(): void
    {
        $line = new InvoiceLine(
            description: 'Prestation gratuite',
            quantity: 5.0,
            unitPrice: Money::zero(),
            vatRate: 20.0,
        );

        $total = $line->getTotalBeforeVat();

        $this->assertSame('0.00', $total->toEuros());
        $this->assertTrue($total->isZero());
    }

    public function testGetTotalBeforeVatWithNegativeQuantity(): void
    {
        // Quantité négative possible pour avoirs/retours
        $line = new InvoiceLine(
            description: 'Retour produit',
            quantity: -2.0,
            unitPrice: Money::fromEuros('50.00'),
            vatRate: 20.0,
        );

        $total = $line->getTotalBeforeVat();

        $this->assertSame('-100.00', $total->toEuros());
        $this->assertTrue($total->isNegative());
    }

    // ========== ID Management ==========

    public function testIdIsNullBeforePersistence(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('10.00'),
            vatRate: 20.0,
        );

        $this->assertNull($line->getId());
    }

    // ========== VAT Rate Storage ==========

    public function testVatRateIsStored(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('10.00'),
            vatRate: 20.0,
        );

        $this->assertSame(20.0, $line->getVatRate());
    }

    public function testVatRateCanBeUpdated(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('10.00'),
            vatRate: 20.0,
        );

        $line->setVatRate(10.0);

        $this->assertSame(10.0, $line->getVatRate());
    }

    public function testVatRateZeroPercent(): void
    {
        $line = new InvoiceLine(
            description: 'Produit exonéré',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 0.0,
        );

        $this->assertSame(0.0, $line->getVatRate());
    }

    // ========== Description & Quantity Updates ==========

    public function testDescriptionCanBeUpdated(): void
    {
        $line = new InvoiceLine(
            description: 'Description initiale',
            quantity: 1.0,
            unitPrice: Money::fromEuros('10.00'),
            vatRate: 20.0,
        );

        $line->setDescription('Nouvelle description');

        $this->assertSame('Nouvelle description', $line->getDescription());
    }

    public function testQuantityCanBeUpdated(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('10.00'),
            vatRate: 20.0,
        );

        $line->setQuantity(5.0);

        $this->assertSame(5.0, $line->getQuantity());

        // Le total doit refléter la nouvelle quantité
        $this->assertSame('50.00', $line->getTotalBeforeVat()->toEuros());
    }

    // ========== Discount Rate (%) ==========

    public function testGetUnitPriceAfterDiscountWithRate(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $line->setDiscountRate(10.0); // 10% de remise

        $discountedPrice = $line->getUnitPriceAfterDiscount();

        // 100€ - 10% = 90€
        $this->assertInstanceOf(Money::class, $discountedPrice);
        $this->assertSame('90.00', $discountedPrice->toEuros());
    }

    public function testGetTotalBeforeVatWithDiscountRate(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 2.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $line->setDiscountRate(10.0);

        $total = $line->getTotalBeforeVat();

        // 2 × (100€ - 10%) = 2 × 90€ = 180€
        $this->assertSame('180.00', $total->toEuros());
    }

    public function testGetDiscountRateReturnsNullByDefault(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $this->assertNull($line->getDiscountRate());
    }

    public function testDiscountRateCanBeSet(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $line->setDiscountRate(15.0);

        $this->assertSame(15.0, $line->getDiscountRate());
    }

    public function testDiscountRateCanBeRemoved(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $line->setDiscountRate(15.0);
        $this->assertSame(15.0, $line->getDiscountRate());

        $line->setDiscountRate(null);
        $this->assertNull($line->getDiscountRate());
    }

    // ========== Discount Fixed Amount ==========

    public function testGetUnitPriceAfterDiscountWithFixedAmount(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $line->setDiscountAmount(Money::fromEuros('5.00'));

        $discountedPrice = $line->getUnitPriceAfterDiscount();

        // 100€ - 5€ = 95€
        $this->assertSame('95.00', $discountedPrice->toEuros());
    }

    public function testGetTotalBeforeVatWithFixedDiscount(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 2.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $line->setDiscountAmount(Money::fromEuros('15.00'));

        $total = $line->getTotalBeforeVat();

        // 2 × (100€ - 15€) = 2 × 85€ = 170€
        $this->assertSame('170.00', $total->toEuros());
    }

    public function testGetDiscountAmountReturnsNullByDefault(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $this->assertNull($line->getDiscountAmount());
    }

    public function testDiscountAmountCanBeSet(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $discount = Money::fromEuros('10.00');
        $line->setDiscountAmount($discount);

        $returned = $line->getDiscountAmount();
        $this->assertInstanceOf(Money::class, $returned);
        $this->assertTrue($returned->equals($discount));
    }

    public function testDiscountAmountCanBeRemoved(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $line->setDiscountAmount(Money::fromEuros('10.00'));
        $this->assertNotNull($line->getDiscountAmount());

        $line->setDiscountAmount(null);
        $this->assertNull($line->getDiscountAmount());
    }

    // ========== Discount Priority (Fixed Amount > Rate) ==========

    public function testFixedDiscountTakesPriorityOverRate(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        // Définir les deux types de remise
        $line->setDiscountRate(10.0);                        // 10% = 10€
        $line->setDiscountAmount(Money::fromEuros('15.00')); // 15€ fixe

        $discountedPrice = $line->getUnitPriceAfterDiscount();

        // La remise fixe doit être prioritaire
        // 100€ - 15€ = 85€ (et PAS 100€ - 10% = 90€)
        $this->assertSame('85.00', $discountedPrice->toEuros());
    }

    public function testTotalUsesFixedDiscountWhenBothSet(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 2.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $line->setDiscountRate(10.0);
        $line->setDiscountAmount(Money::fromEuros('20.00'));

        $total = $line->getTotalBeforeVat();

        // 2 × (100€ - 20€) = 2 × 80€ = 160€
        $this->assertSame('160.00', $total->toEuros());
    }

    // ========== No Discount ==========

    public function testGetUnitPriceAfterDiscountWithNoDiscount(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $discountedPrice = $line->getUnitPriceAfterDiscount();

        // Sans remise, le prix reste identique
        $this->assertSame('100.00', $discountedPrice->toEuros());
        $this->assertTrue($discountedPrice->equals($line->getUnitPrice()));
    }

    public function testTotalBeforeVatWithoutDiscount(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 3.0,
            unitPrice: Money::fromEuros('50.00'),
            vatRate: 20.0,
        );

        $total = $line->getTotalBeforeVat();

        // 3 × 50€ = 150€ (pas de remise)
        $this->assertSame('150.00', $total->toEuros());
    }

    // ========== Discount Edge Cases ==========

    public function testDiscountRateWithZeroPrice(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::zero(),
            vatRate: 20.0,
        );

        $line->setDiscountRate(10.0);

        $discountedPrice = $line->getUnitPriceAfterDiscount();

        $this->assertSame('0.00', $discountedPrice->toEuros());
    }

    public function testDiscountAmountWithZeroPrice(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::zero(),
            vatRate: 20.0,
        );

        $line->setDiscountAmount(Money::fromEuros('5.00'));

        $discountedPrice = $line->getUnitPriceAfterDiscount();

        // 0€ - 5€ = -5€ (montant négatif possible)
        $this->assertSame('-5.00', $discountedPrice->toEuros());
    }

    public function testDiscountRate100Percent(): void
    {
        $line = new InvoiceLine(
            description: 'Prestation offerte',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $line->setDiscountRate(100.0);

        $discountedPrice = $line->getUnitPriceAfterDiscount();

        // 100€ - 100% = 0€
        $this->assertSame('0.00', $discountedPrice->toEuros());
        $this->assertTrue($discountedPrice->isZero());
    }

    public function testDiscountAmountGreaterThanPrice(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('50.00'),
            vatRate: 20.0,
        );

        $line->setDiscountAmount(Money::fromEuros('75.00'));

        $discountedPrice = $line->getUnitPriceAfterDiscount();

        // 50€ - 75€ = -25€ (négatif possible, sera géré au niveau métier si besoin)
        $this->assertSame('-25.00', $discountedPrice->toEuros());
        $this->assertTrue($discountedPrice->isNegative());
    }

    // ========== Discount Immutability ==========

    public function testDiscountDoesNotModifyOriginalPrice(): void
    {
        $originalPrice = Money::fromEuros('100.00');

        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: $originalPrice,
            vatRate: 20.0,
        );

        $line->setDiscountRate(20.0);

        $discountedPrice = $line->getUnitPriceAfterDiscount();

        // Prix original ne doit pas avoir changé
        $this->assertSame('100.00', $originalPrice->toEuros());
        $this->assertSame('80.00', $discountedPrice->toEuros());

        // Instances différentes
        $this->assertNotSame($originalPrice, $discountedPrice);
    }

    // ========== VAT Calculations ==========

    public function testGetVatAmountWithoutDiscount(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $vatAmount = $line->getVatAmount();

        // 100€ × 20% = 20€
        $this->assertInstanceOf(Money::class, $vatAmount);
        $this->assertSame('20.00', $vatAmount->toEuros());
    }

    public function testGetTotalIncludingVatWithoutDiscount(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $totalTTC = $line->getTotalIncludingVat();

        // 100€ HT + 20€ TVA = 120€ TTC
        $this->assertSame('120.00', $totalTTC->toEuros());
    }

    public function testGetVatAmountWithMultipleQuantity(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 3.0,
            unitPrice: Money::fromEuros('50.00'),
            vatRate: 20.0,
        );

        $vatAmount = $line->getVatAmount();

        // (3 × 50€) × 20% = 150€ × 20% = 30€
        $this->assertSame('30.00', $vatAmount->toEuros());
    }

    public function testGetTotalIncludingVatWithMultipleQuantity(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 3.0,
            unitPrice: Money::fromEuros('50.00'),
            vatRate: 20.0,
        );

        $totalTTC = $line->getTotalIncludingVat();

        // 3 × 50€ = 150€ HT + 30€ TVA = 180€ TTC
        $this->assertSame('180.00', $totalTTC->toEuros());
    }

    // ========== VAT on Discounted Price ==========

    public function testGetVatAmountWithDiscountRate(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $line->setDiscountRate(10.0); // 10% de remise

        $vatAmount = $line->getVatAmount();

        // (100€ - 10%) × 20% = 90€ × 20% = 18€
        $this->assertSame('18.00', $vatAmount->toEuros());
    }

    public function testGetTotalIncludingVatWithDiscountRate(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $line->setDiscountRate(10.0);

        $totalTTC = $line->getTotalIncludingVat();

        // 100€ - 10% = 90€ HT + 18€ TVA = 108€ TTC
        $this->assertSame('108.00', $totalTTC->toEuros());
    }

    public function testGetVatAmountWithFixedDiscount(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $line->setDiscountAmount(Money::fromEuros('15.00'));

        $vatAmount = $line->getVatAmount();

        // (100€ - 15€) × 20% = 85€ × 20% = 17€
        $this->assertSame('17.00', $vatAmount->toEuros());
    }

    public function testGetTotalIncludingVatWithFixedDiscount(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $line->setDiscountAmount(Money::fromEuros('15.00'));

        $totalTTC = $line->getTotalIncludingVat();

        // 100€ - 15€ = 85€ HT + 17€ TVA = 102€ TTC
        $this->assertSame('102.00', $totalTTC->toEuros());
    }

    public function testComplexVatCalculationWithDiscountAndQuantity(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 2.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $line->setDiscountRate(10.0);

        $vatAmount = $line->getVatAmount();
        $totalTTC = $line->getTotalIncludingVat();

        // 2 × (100€ - 10%) = 2 × 90€ = 180€ HT
        // TVA: 180€ × 20% = 36€
        // TTC: 180€ + 36€ = 216€
        $this->assertSame('36.00', $vatAmount->toEuros());
        $this->assertSame('216.00', $totalTTC->toEuros());
    }

    // ========== Zero VAT (Exoneration) ==========

    public function testGetVatAmountWithZeroVat(): void
    {
        $line = new InvoiceLine(
            description: 'Produit exonéré',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 0.0,
        );

        $vatAmount = $line->getVatAmount();

        $this->assertSame('0.00', $vatAmount->toEuros());
        $this->assertTrue($vatAmount->isZero());
    }

    public function testGetTotalIncludingVatWithZeroVat(): void
    {
        $line = new InvoiceLine(
            description: 'Produit exonéré',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 0.0,
        );

        $totalHT = $line->getTotalBeforeVat();
        $totalTTC = $line->getTotalIncludingVat();

        // Avec TVA = 0%, HT = TTC
        $this->assertTrue($totalHT->equals($totalTTC));
        $this->assertSame('100.00', $totalTTC->toEuros());
    }

    // ========== Different VAT Rates (French Rates) ==========

    public function testGetVatAmountWithFrenchStandardRate(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0, // Taux normal
        );

        $vatAmount = $line->getVatAmount();

        $this->assertSame('20.00', $vatAmount->toEuros());
    }

    public function testGetVatAmountWithFrenchReducedRate1(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 10.0, // Taux intermédiaire
        );

        $vatAmount = $line->getVatAmount();

        $this->assertSame('10.00', $vatAmount->toEuros());
    }

    public function testGetVatAmountWithFrenchReducedRate2(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 5.5, // Taux réduit
        );

        $vatAmount = $line->getVatAmount();

        $this->assertSame('5.50', $vatAmount->toEuros());
    }

    public function testGetVatAmountWithFrenchSuperReducedRate(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 2.1, // Taux particulier
        );

        $vatAmount = $line->getVatAmount();

        $this->assertSame('2.10', $vatAmount->toEuros());
    }

    // ========== VAT Rounding ==========

    public function testGetVatAmountRoundsCorrectly(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('33.33'),
            vatRate: 20.0,
        );

        $vatAmount = $line->getVatAmount();

        // 33.33€ × 20% = 6.666€ → arrondi à 6.67€
        $this->assertSame('6.67', $vatAmount->toEuros());
    }

    public function testGetTotalIncludingVatRoundsCorrectly(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('33.33'),
            vatRate: 20.0,
        );

        $totalTTC = $line->getTotalIncludingVat();

        // 33.33€ HT + 6.67€ TVA = 40.00€ TTC
        $this->assertSame('40.00', $totalTTC->toEuros());
    }

    // ========== VAT with Negative Amounts (Credit Notes) ==========

    public function testGetVatAmountWithNegativeQuantity(): void
    {
        $line = new InvoiceLine(
            description: 'Avoir / Retour',
            quantity: -1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $vatAmount = $line->getVatAmount();

        // -100€ × 20% = -20€
        $this->assertSame('-20.00', $vatAmount->toEuros());
        $this->assertTrue($vatAmount->isNegative());
    }

    public function testGetTotalIncludingVatWithNegativeQuantity(): void
    {
        $line = new InvoiceLine(
            description: 'Avoir / Retour',
            quantity: -1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $totalTTC = $line->getTotalIncludingVat();

        // -100€ HT + (-20€) TVA = -120€ TTC
        $this->assertSame('-120.00', $totalTTC->toEuros());
        $this->assertTrue($totalTTC->isNegative());
    }

    // ========== Factur-X EN16931 Fields ==========

    public function testDefaultQuantityUnitIsHour(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $this->assertSame(QuantityUnitCode::HOUR, $line->getQuantityUnit());
    }

    public function testConstructWithCustomQuantityUnit(): void
    {
        $line = new InvoiceLine(
            description: 'Produit vendu au kilo',
            quantity: 2.5,
            unitPrice: Money::fromEuros('15.00'),
            vatRate: 20.0,
            quantityUnit: QuantityUnitCode::KILOGRAM,
        );

        $this->assertSame(QuantityUnitCode::KILOGRAM, $line->getQuantityUnit());
    }

    public function testQuantityUnitCanBeChanged(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $line->setQuantityUnit(QuantityUnitCode::DAY);

        $this->assertSame(QuantityUnitCode::DAY, $line->getQuantityUnit());
    }

    public function testDefaultTaxCategoryCodeIsStandard(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $this->assertSame(TaxCategoryCode::STANDARD, $line->getTaxCategoryCode());
    }

    public function testConstructWithCustomTaxCategoryCode(): void
    {
        $line = new InvoiceLine(
            description: 'Export hors UE',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 0.0,
            taxCategoryCode: TaxCategoryCode::EXEMPT,
        );

        $this->assertSame(TaxCategoryCode::EXEMPT, $line->getTaxCategoryCode());
    }

    public function testTaxCategoryCodeCanBeChanged(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $line->setTaxCategoryCode(TaxCategoryCode::ZERO_RATE);

        $this->assertSame(TaxCategoryCode::ZERO_RATE, $line->getTaxCategoryCode());
    }

    public function testConstructWithBothQuantityUnitAndTaxCategory(): void
    {
        $line = new InvoiceLine(
            description: 'Prestation journalière exonérée',
            quantity: 5.0,
            unitPrice: Money::fromEuros('500.00'),
            vatRate: 0.0,
            quantityUnit: QuantityUnitCode::DAY,
            taxCategoryCode: TaxCategoryCode::EXEMPT,
        );

        $this->assertSame(QuantityUnitCode::DAY, $line->getQuantityUnit());
        $this->assertSame(TaxCategoryCode::EXEMPT, $line->getTaxCategoryCode());
    }

    // ========== Item Identifier (BT-128) ==========

    public function testItemIdentifierIsNullByDefault(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $this->assertNull($line->getItemIdentifier());
    }

    public function testItemIdentifierCanBeSet(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $line->setItemIdentifier('SKU-12345');

        $this->assertSame('SKU-12345', $line->getItemIdentifier());
    }

    public function testItemIdentifierCanBeCleared(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $line->setItemIdentifier('SKU-12345');
        $line->setItemIdentifier(null);

        $this->assertNull($line->getItemIdentifier());
    }

    // ========== Country of Origin (BT-134) ==========

    public function testCountryOfOriginIsNullByDefault(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $this->assertNull($line->getCountryOfOrigin());
    }

    public function testCountryOfOriginCanBeSet(): void
    {
        $line = new InvoiceLine(
            description: 'Produit fabriqué en France',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $line->setCountryOfOrigin('FR');

        $this->assertSame('FR', $line->getCountryOfOrigin());
    }

    public function testCountryOfOriginCanBeCleared(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $line->setCountryOfOrigin('DE');
        $line->setCountryOfOrigin(null);

        $this->assertNull($line->getCountryOfOrigin());
    }

    public function testCountryOfOriginWithVariousCountryCodes(): void
    {
        $line = new InvoiceLine(
            description: 'Test',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        // Test avec différents codes pays ISO 3166-1 alpha-2
        $line->setCountryOfOrigin('DE');
        $this->assertSame('DE', $line->getCountryOfOrigin());

        $line->setCountryOfOrigin('CN');
        $this->assertSame('CN', $line->getCountryOfOrigin());

        $line->setCountryOfOrigin('US');
        $this->assertSame('US', $line->getCountryOfOrigin());
    }
}
