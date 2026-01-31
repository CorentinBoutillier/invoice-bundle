<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Enum;

use CorentinBoutillier\InvoiceBundle\Enum\FacturXProfile;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FacturXProfile enum.
 */
class FacturXProfileTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $cases = FacturXProfile::cases();

        $this->assertCount(5, $cases);
        $this->assertContains(FacturXProfile::MINIMUM, $cases);
        $this->assertContains(FacturXProfile::BASIC_WL, $cases);
        $this->assertContains(FacturXProfile::BASIC, $cases);
        $this->assertContains(FacturXProfile::EN16931, $cases);
        $this->assertContains(FacturXProfile::EXTENDED, $cases);
    }

    public function testValuesAreCorrect(): void
    {
        $this->assertSame('MINIMUM', FacturXProfile::MINIMUM->value);
        $this->assertSame('BASIC_WL', FacturXProfile::BASIC_WL->value);
        $this->assertSame('BASIC', FacturXProfile::BASIC->value);
        $this->assertSame('EN16931', FacturXProfile::EN16931->value);
        $this->assertSame('EXTENDED', FacturXProfile::EXTENDED->value);
    }

    public function testFromMethodReturnsCorrectCase(): void
    {
        $this->assertSame(FacturXProfile::MINIMUM, FacturXProfile::from('MINIMUM'));
        $this->assertSame(FacturXProfile::BASIC_WL, FacturXProfile::from('BASIC_WL'));
        $this->assertSame(FacturXProfile::BASIC, FacturXProfile::from('BASIC'));
        $this->assertSame(FacturXProfile::EN16931, FacturXProfile::from('EN16931'));
        $this->assertSame(FacturXProfile::EXTENDED, FacturXProfile::from('EXTENDED'));
    }

    public function testFromMethodThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        FacturXProfile::from('INVALID');
    }

    public function testTryFromMethodReturnsNullForInvalidValue(): void
    {
        $this->assertNull(FacturXProfile::tryFrom('INVALID'));
    }

    /**
     * @dataProvider urnDataProvider
     */
    public function testGetUrnReturnsCorrectUrn(FacturXProfile $profile, string $expectedUrn): void
    {
        $this->assertSame($expectedUrn, $profile->getUrn());
    }

    /**
     * URNs conforming to Factur-X XSD 1.07.3 specification.
     *
     * @return array<string, array{FacturXProfile, string}>
     */
    public static function urnDataProvider(): array
    {
        return [
            'MINIMUM' => [FacturXProfile::MINIMUM, 'urn:factur-x.eu:1p0:minimum'],
            'BASIC_WL' => [FacturXProfile::BASIC_WL, 'urn:factur-x.eu:1p0:basicwl'],
            'BASIC' => [FacturXProfile::BASIC, 'urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:basic'],
            'EN16931' => [FacturXProfile::EN16931, 'urn:cen.eu:en16931:2017'],
            'EXTENDED' => [FacturXProfile::EXTENDED, 'urn:cen.eu:en16931:2017#conformant#urn:factur-x.eu:1p0:extended'],
        ];
    }

    /**
     * @dataProvider atgpProfileDataProvider
     */
    public function testGetAtgpProfileReturnsCorrectValue(FacturXProfile $profile, string $expectedAtgpProfile): void
    {
        $this->assertSame($expectedAtgpProfile, $profile->getAtgpProfile());
    }

    /**
     * @return array<string, array{FacturXProfile, string}>
     */
    public static function atgpProfileDataProvider(): array
    {
        return [
            'MINIMUM' => [FacturXProfile::MINIMUM, 'minimum'],
            'BASIC_WL' => [FacturXProfile::BASIC_WL, 'basicwl'],
            'BASIC' => [FacturXProfile::BASIC, 'basic'],
            'EN16931' => [FacturXProfile::EN16931, 'en16931'],
            'EXTENDED' => [FacturXProfile::EXTENDED, 'extended'],
        ];
    }

    public function testHasLineItemsReturnsFalseForBasicWl(): void
    {
        $this->assertFalse(FacturXProfile::BASIC_WL->hasLineItems());
    }

    /**
     * @dataProvider profilesWithLineItemsDataProvider
     */
    public function testHasLineItemsReturnsTrueForOtherProfiles(FacturXProfile $profile): void
    {
        $this->assertTrue($profile->hasLineItems());
    }

    /**
     * @return array<string, array{FacturXProfile}>
     */
    public static function profilesWithLineItemsDataProvider(): array
    {
        return [
            'MINIMUM' => [FacturXProfile::MINIMUM],
            'BASIC' => [FacturXProfile::BASIC],
            'EN16931' => [FacturXProfile::EN16931],
            'EXTENDED' => [FacturXProfile::EXTENDED],
        ];
    }
}
