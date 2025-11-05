<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\DTO;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    // ========== Construction ==========

    public function testConstructWithFromCents(): void
    {
        $money = Money::fromCents(1500);

        $this->assertInstanceOf(Money::class, $money);
        $this->assertSame(1500, $money->getAmount());
        $this->assertSame('15.00', $money->toEuros());
    }

    public function testConstructWithFromEuros(): void
    {
        $money = Money::fromEuros('15.00');

        $this->assertInstanceOf(Money::class, $money);
        $this->assertSame(1500, $money->getAmount());
        $this->assertSame('15.00', $money->toEuros());
    }

    public function testConstructWithFromEurosRoundsCorrectly(): void
    {
        // Test arrondi standard
        $money1 = Money::fromEuros('15.994'); // Devrait arrondir à 15.99
        $this->assertSame(1599, $money1->getAmount());

        $money2 = Money::fromEuros('15.995'); // Devrait arrondir à 16.00
        $this->assertSame(1600, $money2->getAmount());
    }

    public function testConstructWithZero(): void
    {
        $money = Money::zero();

        $this->assertInstanceOf(Money::class, $money);
        $this->assertSame(0, $money->getAmount());
        $this->assertTrue($money->isZero());
    }

    public function testConstructWithNegativeAmount(): void
    {
        // Les montants négatifs sont autorisés (pour les avoirs)
        $money = Money::fromCents(-1500);

        $this->assertSame(-1500, $money->getAmount());
        $this->assertTrue($money->isNegative());
    }

    // ========== Opérations arithmétiques (immutables) ==========

    public function testAdd(): void
    {
        $money1 = Money::fromEuros('10.00');
        $money2 = Money::fromEuros('5.50');

        $result = $money1->add($money2);

        $this->assertSame('15.50', $result->toEuros());
        // Vérifier immutabilité
        $this->assertSame('10.00', $money1->toEuros());
        $this->assertSame('5.50', $money2->toEuros());
    }

    public function testSubtract(): void
    {
        $money1 = Money::fromEuros('15.00');
        $money2 = Money::fromEuros('5.50');

        $result = $money1->subtract($money2);

        $this->assertSame('9.50', $result->toEuros());
        // Vérifier immutabilité
        $this->assertSame('15.00', $money1->toEuros());
        $this->assertSame('5.50', $money2->toEuros());
    }

    public function testMultiplyByInteger(): void
    {
        $money = Money::fromEuros('10.00');

        $result = $money->multiply(3);

        $this->assertSame('30.00', $result->toEuros());
        // Vérifier immutabilité
        $this->assertSame('10.00', $money->toEuros());
    }

    public function testMultiplyByFloat(): void
    {
        $money = Money::fromEuros('100.00');

        // Multiplication par taux TVA 20%
        $result = $money->multiply(0.20);

        $this->assertSame('20.00', $result->toEuros());
        // Vérifier immutabilité
        $this->assertSame('100.00', $money->toEuros());
    }

    public function testMultiplyRoundsCorrectly(): void
    {
        $money = Money::fromEuros('10.00');

        // 10.00 × 0.055 = 0.55 (arrondi)
        $result = $money->multiply(0.055);
        $this->assertSame('0.55', $result->toEuros());

        // 10.00 × 2.5 = 25.00
        $result2 = $money->multiply(2.5);
        $this->assertSame('25.00', $result2->toEuros());
    }

    public function testDivide(): void
    {
        $money = Money::fromEuros('100.00');

        $result = $money->divide(3);

        // 100.00 / 3 = 33.33 (arrondi au centime)
        $this->assertSame('33.33', $result->toEuros());
        // Vérifier immutabilité
        $this->assertSame('100.00', $money->toEuros());
    }

    public function testDivideThrowsExceptionOnZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Division by zero');

        $money = Money::fromEuros('100.00');
        $money->divide(0);
    }

    // ========== Comparaisons ==========

    public function testEquals(): void
    {
        $money1 = Money::fromEuros('15.00');
        $money2 = Money::fromEuros('15.00');
        $money3 = Money::fromEuros('20.00');

        $this->assertTrue($money1->equals($money2));
        $this->assertFalse($money1->equals($money3));
    }

    public function testIsZero(): void
    {
        $zero = Money::zero();
        $notZero = Money::fromEuros('1.00');

        $this->assertTrue($zero->isZero());
        $this->assertFalse($notZero->isZero());
    }

    public function testIsPositive(): void
    {
        $positive = Money::fromEuros('15.00');
        $zero = Money::zero();
        $negative = Money::fromCents(-1500);

        $this->assertTrue($positive->isPositive());
        $this->assertFalse($zero->isPositive());
        $this->assertFalse($negative->isPositive());
    }

    public function testIsNegative(): void
    {
        $negative = Money::fromCents(-1500);
        $zero = Money::zero();
        $positive = Money::fromEuros('15.00');

        $this->assertTrue($negative->isNegative());
        $this->assertFalse($zero->isNegative());
        $this->assertFalse($positive->isNegative());
    }

    public function testGreaterThan(): void
    {
        $money1 = Money::fromEuros('20.00');
        $money2 = Money::fromEuros('10.00');
        $money3 = Money::fromEuros('20.00');

        $this->assertTrue($money1->greaterThan($money2));
        $this->assertFalse($money2->greaterThan($money1));
        $this->assertFalse($money1->greaterThan($money3)); // Égaux
    }

    public function testLessThan(): void
    {
        $money1 = Money::fromEuros('10.00');
        $money2 = Money::fromEuros('20.00');
        $money3 = Money::fromEuros('10.00');

        $this->assertTrue($money1->lessThan($money2));
        $this->assertFalse($money2->lessThan($money1));
        $this->assertFalse($money1->lessThan($money3)); // Égaux
    }

    public function testGreaterThanOrEqual(): void
    {
        $money1 = Money::fromEuros('20.00');
        $money2 = Money::fromEuros('10.00');
        $money3 = Money::fromEuros('20.00');

        $this->assertTrue($money1->greaterThanOrEqual($money2));
        $this->assertTrue($money1->greaterThanOrEqual($money3));
        $this->assertFalse($money2->greaterThanOrEqual($money1));
    }

    public function testLessThanOrEqual(): void
    {
        $money1 = Money::fromEuros('10.00');
        $money2 = Money::fromEuros('20.00');
        $money3 = Money::fromEuros('10.00');

        $this->assertTrue($money1->lessThanOrEqual($money2));
        $this->assertTrue($money1->lessThanOrEqual($money3));
        $this->assertFalse($money2->lessThanOrEqual($money1));
    }

    // ========== Formatage ==========

    public function testToEuros(): void
    {
        $money1 = Money::fromCents(1500);
        $this->assertSame('15.00', $money1->toEuros());

        $money2 = Money::fromCents(1234);
        $this->assertSame('12.34', $money2->toEuros());

        $money3 = Money::fromCents(5);
        $this->assertSame('0.05', $money3->toEuros());

        $money4 = Money::fromCents(0);
        $this->assertSame('0.00', $money4->toEuros());

        $money5 = Money::fromCents(-1500);
        $this->assertSame('-15.00', $money5->toEuros());
    }

    public function testToString(): void
    {
        $money = Money::fromEuros('15.50');

        $this->assertSame('15.50', (string) $money);
    }

    public function testFormat(): void
    {
        $money = Money::fromEuros('1500.50');

        // Format français par défaut
        $formatted = $money->format();
        $this->assertSame('1 500,50 €', $formatted);
    }

    public function testFormatWithEnglishLocale(): void
    {
        $money = Money::fromEuros('1500.50');

        $formatted = $money->format('en_US');
        $this->assertSame('1,500.50 €', $formatted);
    }

    public function testFormatZero(): void
    {
        $money = Money::zero();

        $formatted = $money->format();
        $this->assertSame('0,00 €', $formatted);
    }

    public function testFormatNegative(): void
    {
        $money = Money::fromCents(-150050);

        $formatted = $money->format();
        $this->assertSame('-1 500,50 €', $formatted);
    }

    // ========== Cas limites ==========

    public function testLargeAmounts(): void
    {
        // Test avec de gros montants (millions)
        $money = Money::fromEuros('1000000.00');
        $this->assertSame(100000000, $money->getAmount());
        $this->assertSame('1000000.00', $money->toEuros());
    }

    public function testVerySmallAmounts(): void
    {
        // Test avec 1 centime
        $money = Money::fromCents(1);
        $this->assertSame('0.01', $money->toEuros());
    }

    public function testComplexCalculationChain(): void
    {
        // Scénario réel: calcul TTC
        // 100€ HT + TVA 20% = 120€ TTC
        $priceHT = Money::fromEuros('100.00');
        $vatRate = 0.20;

        $vatAmount = $priceHT->multiply($vatRate);
        $this->assertSame('20.00', $vatAmount->toEuros());

        $priceTTC = $priceHT->add($vatAmount);
        $this->assertSame('120.00', $priceTTC->toEuros());
    }

    public function testComplexDiscountCalculation(): void
    {
        // Scénario: Prix 80€ × quantité 10 = 800€
        // Remise ligne 10% = 720€
        // Remise globale 50€ = 670€
        // TVA 20% = 134€
        // Total TTC = 804€

        $unitPrice = Money::fromEuros('80.00');
        $quantity = 10;

        $total = $unitPrice->multiply($quantity);
        $this->assertSame('800.00', $total->toEuros());

        $lineDiscount = $total->multiply(0.10);
        $afterLineDiscount = $total->subtract($lineDiscount);
        $this->assertSame('720.00', $afterLineDiscount->toEuros());

        $globalDiscount = Money::fromEuros('50.00');
        $afterGlobalDiscount = $afterLineDiscount->subtract($globalDiscount);
        $this->assertSame('670.00', $afterGlobalDiscount->toEuros());

        $vat = $afterGlobalDiscount->multiply(0.20);
        $this->assertSame('134.00', $vat->toEuros());

        $ttc = $afterGlobalDiscount->add($vat);
        $this->assertSame('804.00', $ttc->toEuros());
    }

    // ========== Immutabilité ==========

    public function testImmutability(): void
    {
        $original = Money::fromEuros('100.00');

        // Toutes les opérations retournent de nouvelles instances
        $added = $original->add(Money::fromEuros('50.00'));
        $subtracted = $original->subtract(Money::fromEuros('30.00'));
        $multiplied = $original->multiply(2);
        $divided = $original->divide(2);

        // L'objet original n'a pas changé
        $this->assertSame('100.00', $original->toEuros());
        $this->assertSame(10000, $original->getAmount());

        // Chaque opération a créé une nouvelle instance
        $this->assertNotSame($original, $added);
        $this->assertNotSame($original, $subtracted);
        $this->assertNotSame($original, $multiplied);
        $this->assertNotSame($original, $divided);
    }
}
