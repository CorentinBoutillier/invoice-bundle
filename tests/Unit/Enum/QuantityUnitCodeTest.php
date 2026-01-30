<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Enum;

use CorentinBoutillier\InvoiceBundle\Enum\QuantityUnitCode;
use PHPUnit\Framework\TestCase;

/**
 * Tests for QuantityUnitCode enum (UN/ECE Recommendation 20).
 */
class QuantityUnitCodeTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $cases = QuantityUnitCode::cases();

        // 17 cases
        $this->assertCount(17, $cases);
        $this->assertContains(QuantityUnitCode::HOUR, $cases);
        $this->assertContains(QuantityUnitCode::DAY, $cases);
        $this->assertContains(QuantityUnitCode::MONTH, $cases);
        $this->assertContains(QuantityUnitCode::YEAR, $cases);
        $this->assertContains(QuantityUnitCode::UNIT, $cases);
        $this->assertContains(QuantityUnitCode::PIECE, $cases);
        $this->assertContains(QuantityUnitCode::SET, $cases);
        $this->assertContains(QuantityUnitCode::KILOGRAM, $cases);
        $this->assertContains(QuantityUnitCode::GRAM, $cases);
        $this->assertContains(QuantityUnitCode::LITER, $cases);
        $this->assertContains(QuantityUnitCode::MILLILITER, $cases);
        $this->assertContains(QuantityUnitCode::CUBIC_METER, $cases);
        $this->assertContains(QuantityUnitCode::METER, $cases);
        $this->assertContains(QuantityUnitCode::CENTIMETER, $cases);
        $this->assertContains(QuantityUnitCode::MILLIMETER, $cases);
        $this->assertContains(QuantityUnitCode::KILOMETER, $cases);
        $this->assertContains(QuantityUnitCode::SQUARE_METER, $cases);
    }

    public function testTimeUnitValues(): void
    {
        $this->assertSame('HUR', QuantityUnitCode::HOUR->value);
        $this->assertSame('DAY', QuantityUnitCode::DAY->value);
        $this->assertSame('MON', QuantityUnitCode::MONTH->value);
        $this->assertSame('ANN', QuantityUnitCode::YEAR->value);
    }

    public function testQuantityUnitValues(): void
    {
        $this->assertSame('C62', QuantityUnitCode::UNIT->value);
        $this->assertSame('H87', QuantityUnitCode::PIECE->value);
        $this->assertSame('SET', QuantityUnitCode::SET->value);
    }

    public function testWeightUnitValues(): void
    {
        $this->assertSame('KGM', QuantityUnitCode::KILOGRAM->value);
        $this->assertSame('GRM', QuantityUnitCode::GRAM->value);
    }

    public function testVolumeUnitValues(): void
    {
        $this->assertSame('LTR', QuantityUnitCode::LITER->value);
        $this->assertSame('MLT', QuantityUnitCode::MILLILITER->value);
        $this->assertSame('MTQ', QuantityUnitCode::CUBIC_METER->value);
    }

    public function testLengthUnitValues(): void
    {
        $this->assertSame('MTR', QuantityUnitCode::METER->value);
        $this->assertSame('CMT', QuantityUnitCode::CENTIMETER->value);
        $this->assertSame('MMT', QuantityUnitCode::MILLIMETER->value);
        $this->assertSame('KMT', QuantityUnitCode::KILOMETER->value);
    }

    public function testAreaUnitValues(): void
    {
        $this->assertSame('MTK', QuantityUnitCode::SQUARE_METER->value);
    }

    public function testFromMethodReturnsCorrectCase(): void
    {
        $this->assertSame(QuantityUnitCode::HOUR, QuantityUnitCode::from('HUR'));
        $this->assertSame(QuantityUnitCode::UNIT, QuantityUnitCode::from('C62'));
        $this->assertSame(QuantityUnitCode::KILOGRAM, QuantityUnitCode::from('KGM'));
    }

    public function testFromMethodThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        QuantityUnitCode::from('INVALID');
    }

    public function testTryFromMethodReturnsNullForInvalidValue(): void
    {
        $this->assertNull(QuantityUnitCode::tryFrom('INVALID'));
    }

    /**
     * @dataProvider labelDataProvider
     */
    public function testGetLabelReturnsCorrectLabel(QuantityUnitCode $code, string $expectedLabel): void
    {
        $this->assertSame($expectedLabel, $code->getLabel());
    }

    /**
     * @return array<string, array{QuantityUnitCode, string}>
     */
    public static function labelDataProvider(): array
    {
        return [
            'HOUR' => [QuantityUnitCode::HOUR, 'Heure'],
            'DAY' => [QuantityUnitCode::DAY, 'Jour'],
            'MONTH' => [QuantityUnitCode::MONTH, 'Mois'],
            'YEAR' => [QuantityUnitCode::YEAR, 'Année'],
            'UNIT' => [QuantityUnitCode::UNIT, 'Unité'],
            'PIECE' => [QuantityUnitCode::PIECE, 'Pièce'],
            'SET' => [QuantityUnitCode::SET, 'Lot'],
            'KILOGRAM' => [QuantityUnitCode::KILOGRAM, 'Kilogramme'],
            'GRAM' => [QuantityUnitCode::GRAM, 'Gramme'],
            'LITER' => [QuantityUnitCode::LITER, 'Litre'],
            'METER' => [QuantityUnitCode::METER, 'Mètre'],
        ];
    }

    /**
     * @dataProvider symbolDataProvider
     */
    public function testGetSymbolReturnsCorrectSymbol(QuantityUnitCode $code, string $expectedSymbol): void
    {
        $this->assertSame($expectedSymbol, $code->getSymbol());
    }

    /**
     * @return array<string, array{QuantityUnitCode, string}>
     */
    public static function symbolDataProvider(): array
    {
        return [
            'HOUR' => [QuantityUnitCode::HOUR, 'h'],
            'DAY' => [QuantityUnitCode::DAY, 'j'],
            'MONTH' => [QuantityUnitCode::MONTH, 'mois'],
            'YEAR' => [QuantityUnitCode::YEAR, 'an'],
            'UNIT' => [QuantityUnitCode::UNIT, 'u'],
            'PIECE' => [QuantityUnitCode::PIECE, 'u'],
            'SET' => [QuantityUnitCode::SET, 'lot'],
            'KILOGRAM' => [QuantityUnitCode::KILOGRAM, 'kg'],
            'GRAM' => [QuantityUnitCode::GRAM, 'g'],
            'LITER' => [QuantityUnitCode::LITER, 'L'],
            'MILLILITER' => [QuantityUnitCode::MILLILITER, 'mL'],
            'CUBIC_METER' => [QuantityUnitCode::CUBIC_METER, 'm3'],
            'METER' => [QuantityUnitCode::METER, 'm'],
            'CENTIMETER' => [QuantityUnitCode::CENTIMETER, 'cm'],
            'MILLIMETER' => [QuantityUnitCode::MILLIMETER, 'mm'],
            'KILOMETER' => [QuantityUnitCode::KILOMETER, 'km'],
            'SQUARE_METER' => [QuantityUnitCode::SQUARE_METER, 'm2'],
        ];
    }
}
