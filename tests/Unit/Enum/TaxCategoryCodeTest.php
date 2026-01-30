<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Enum;

use CorentinBoutillier\InvoiceBundle\Enum\TaxCategoryCode;
use PHPUnit\Framework\TestCase;

/**
 * Tests for TaxCategoryCode enum (UN/CEFACT codes).
 */
class TaxCategoryCodeTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $cases = TaxCategoryCode::cases();

        $this->assertCount(7, $cases);
        $this->assertContains(TaxCategoryCode::STANDARD, $cases);
        $this->assertContains(TaxCategoryCode::ZERO_RATE, $cases);
        $this->assertContains(TaxCategoryCode::EXEMPT, $cases);
        $this->assertContains(TaxCategoryCode::REVERSE_CHARGE, $cases);
        $this->assertContains(TaxCategoryCode::INTRA_EU, $cases);
        $this->assertContains(TaxCategoryCode::EXPORT, $cases);
        $this->assertContains(TaxCategoryCode::NOT_SUBJECT, $cases);
    }

    public function testValuesAreCorrect(): void
    {
        $this->assertSame('S', TaxCategoryCode::STANDARD->value);
        $this->assertSame('Z', TaxCategoryCode::ZERO_RATE->value);
        $this->assertSame('E', TaxCategoryCode::EXEMPT->value);
        $this->assertSame('AE', TaxCategoryCode::REVERSE_CHARGE->value);
        $this->assertSame('K', TaxCategoryCode::INTRA_EU->value);
        $this->assertSame('G', TaxCategoryCode::EXPORT->value);
        $this->assertSame('O', TaxCategoryCode::NOT_SUBJECT->value);
    }

    public function testFromMethodReturnsCorrectCase(): void
    {
        $this->assertSame(TaxCategoryCode::STANDARD, TaxCategoryCode::from('S'));
        $this->assertSame(TaxCategoryCode::REVERSE_CHARGE, TaxCategoryCode::from('AE'));
        $this->assertSame(TaxCategoryCode::EXPORT, TaxCategoryCode::from('G'));
    }

    public function testFromMethodThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        TaxCategoryCode::from('X');
    }

    public function testTryFromMethodReturnsNullForInvalidValue(): void
    {
        $this->assertNull(TaxCategoryCode::tryFrom('X'));
    }

    /**
     * @dataProvider labelDataProvider
     */
    public function testGetLabelReturnsCorrectLabel(TaxCategoryCode $code, string $expectedLabel): void
    {
        $this->assertSame($expectedLabel, $code->getLabel());
    }

    /**
     * @return array<string, array{TaxCategoryCode, string}>
     */
    public static function labelDataProvider(): array
    {
        return [
            'STANDARD' => [TaxCategoryCode::STANDARD, 'TVA taux normal'],
            'ZERO_RATE' => [TaxCategoryCode::ZERO_RATE, 'TVA taux zéro'],
            'EXEMPT' => [TaxCategoryCode::EXEMPT, 'Exonéré de TVA'],
            'REVERSE_CHARGE' => [TaxCategoryCode::REVERSE_CHARGE, 'Autoliquidation'],
            'INTRA_EU' => [TaxCategoryCode::INTRA_EU, 'Livraison intracommunautaire'],
            'EXPORT' => [TaxCategoryCode::EXPORT, 'Exportation'],
            'NOT_SUBJECT' => [TaxCategoryCode::NOT_SUBJECT, 'Non soumis à TVA'],
        ];
    }

    public function testRequiresZeroRateReturnsFalseForStandard(): void
    {
        $this->assertFalse(TaxCategoryCode::STANDARD->requiresZeroRate());
    }

    /**
     * @dataProvider zeroRateCategoriesDataProvider
     */
    public function testRequiresZeroRateReturnsTrueForZeroRateCategories(TaxCategoryCode $code): void
    {
        $this->assertTrue($code->requiresZeroRate());
    }

    /**
     * @return array<string, array{TaxCategoryCode}>
     */
    public static function zeroRateCategoriesDataProvider(): array
    {
        return [
            'ZERO_RATE' => [TaxCategoryCode::ZERO_RATE],
            'EXEMPT' => [TaxCategoryCode::EXEMPT],
            'REVERSE_CHARGE' => [TaxCategoryCode::REVERSE_CHARGE],
            'INTRA_EU' => [TaxCategoryCode::INTRA_EU],
            'EXPORT' => [TaxCategoryCode::EXPORT],
            'NOT_SUBJECT' => [TaxCategoryCode::NOT_SUBJECT],
        ];
    }

    /**
     * @dataProvider exemptionReasonDataProvider
     */
    public function testGetExemptionReasonCodeReturnsCorrectValue(TaxCategoryCode $code, ?string $expectedReason): void
    {
        $this->assertSame($expectedReason, $code->getExemptionReasonCode());
    }

    /**
     * @return array<string, array{TaxCategoryCode, ?string}>
     */
    public static function exemptionReasonDataProvider(): array
    {
        return [
            'STANDARD' => [TaxCategoryCode::STANDARD, null],
            'ZERO_RATE' => [TaxCategoryCode::ZERO_RATE, null],
            'EXEMPT' => [TaxCategoryCode::EXEMPT, null],
            'REVERSE_CHARGE' => [TaxCategoryCode::REVERSE_CHARGE, 'VATEX-EU-AE'],
            'INTRA_EU' => [TaxCategoryCode::INTRA_EU, 'VATEX-EU-IC'],
            'EXPORT' => [TaxCategoryCode::EXPORT, 'VATEX-EU-G'],
            'NOT_SUBJECT' => [TaxCategoryCode::NOT_SUBJECT, null],
        ];
    }
}
