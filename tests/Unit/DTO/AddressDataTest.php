<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\DTO;

use CorentinBoutillier\InvoiceBundle\DTO\AddressData;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AddressData DTO.
 */
class AddressDataTest extends TestCase
{
    public function testConstructWithRequiredFieldsOnly(): void
    {
        $address = new AddressData(
            line1: '123 Rue de la Paix',
            city: 'Paris',
            postalCode: '75001',
        );

        $this->assertSame('123 Rue de la Paix', $address->line1);
        $this->assertSame('Paris', $address->city);
        $this->assertSame('75001', $address->postalCode);
        $this->assertSame('FR', $address->countryCode);
        $this->assertNull($address->line2);
        $this->assertNull($address->line3);
        $this->assertNull($address->countrySubdivision);
    }

    public function testConstructWithAllFields(): void
    {
        $address = new AddressData(
            line1: '123 Rue de la Paix',
            city: 'Paris',
            postalCode: '75001',
            countryCode: 'BE',
            line2: 'Batiment A',
            line3: '2eme etage',
            countrySubdivision: 'Ile-de-France',
        );

        $this->assertSame('123 Rue de la Paix', $address->line1);
        $this->assertSame('Paris', $address->city);
        $this->assertSame('75001', $address->postalCode);
        $this->assertSame('BE', $address->countryCode);
        $this->assertSame('Batiment A', $address->line2);
        $this->assertSame('2eme etage', $address->line3);
        $this->assertSame('Ile-de-France', $address->countrySubdivision);
    }

    public function testClassIsReadonly(): void
    {
        $reflection = new \ReflectionClass(AddressData::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testFromLegacyStringWithSimpleAddress(): void
    {
        $address = AddressData::fromLegacyString('123 Rue de la Paix');

        $this->assertSame('123 Rue de la Paix', $address->line1);
        $this->assertSame('FR', $address->countryCode);
    }

    public function testFromLegacyStringWithMultilineAddressAndPostalCode(): void
    {
        $legacyAddress = "123 Rue de la Paix\n75001 Paris";

        $address = AddressData::fromLegacyString($legacyAddress);

        $this->assertSame('123 Rue de la Paix', $address->line1);
        $this->assertSame('75001', $address->postalCode);
        $this->assertSame('Paris', $address->city);
    }

    public function testFromLegacyStringWithThreeLines(): void
    {
        $legacyAddress = "Societe ABC\n123 Rue de la Paix\n75001 Paris";

        $address = AddressData::fromLegacyString($legacyAddress);

        $this->assertSame('Societe ABC', $address->line1);
        $this->assertSame('123 Rue de la Paix', $address->line2);
        $this->assertSame('75001', $address->postalCode);
        $this->assertSame('Paris', $address->city);
    }

    public function testFromLegacyStringWithCustomCountryCode(): void
    {
        $address = AddressData::fromLegacyString('123 Main Street', 'US');

        $this->assertSame('US', $address->countryCode);
    }

    public function testFromLegacyStringWithEmptyString(): void
    {
        $address = AddressData::fromLegacyString('');

        $this->assertSame('', $address->line1);
        $this->assertSame('', $address->city);
        $this->assertSame('', $address->postalCode);
    }

    public function testToSingleLineWithBasicAddress(): void
    {
        $address = new AddressData(
            line1: '123 Rue de la Paix',
            city: 'Paris',
            postalCode: '75001',
        );

        $this->assertSame('123 Rue de la Paix, 75001 Paris', $address->toSingleLine());
    }

    public function testToSingleLineWithLine2(): void
    {
        $address = new AddressData(
            line1: '123 Rue de la Paix',
            city: 'Paris',
            postalCode: '75001',
            line2: 'Batiment A',
        );

        $this->assertSame('123 Rue de la Paix, Batiment A, 75001 Paris', $address->toSingleLine());
    }

    public function testToSingleLineWithForeignCountry(): void
    {
        $address = new AddressData(
            line1: '123 Main Street',
            city: 'Brussels',
            postalCode: '1000',
            countryCode: 'BE',
        );

        $this->assertSame('123 Main Street, 1000 Brussels, BE', $address->toSingleLine());
    }

    public function testToSingleLineDoesNotIncludeFranceCountryCode(): void
    {
        $address = new AddressData(
            line1: '123 Rue de la Paix',
            city: 'Paris',
            postalCode: '75001',
            countryCode: 'FR',
        );

        $this->assertStringNotContainsString('FR', $address->toSingleLine());
    }
}
