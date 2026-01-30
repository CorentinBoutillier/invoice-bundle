<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\DTO;

use CorentinBoutillier\InvoiceBundle\DTO\AddressData;
use CorentinBoutillier\InvoiceBundle\DTO\BuyerData;
use CorentinBoutillier\InvoiceBundle\DTO\CustomerData;
use PHPUnit\Framework\TestCase;

/**
 * Tests for BuyerData DTO.
 */
class BuyerDataTest extends TestCase
{
    public function testConstructWithRequiredFieldsOnly(): void
    {
        $address = new AddressData(
            line1: '123 Rue du Client',
            city: 'Lyon',
            postalCode: '69001',
        );

        $buyer = new BuyerData(
            name: 'Client SARL',
            address: $address,
        );

        $this->assertSame('Client SARL', $buyer->name);
        $this->assertSame($address, $buyer->address);
        $this->assertNull($buyer->siret);
        $this->assertNull($buyer->vatNumber);
        $this->assertNull($buyer->email);
        $this->assertNull($buyer->phone);
        $this->assertNull($buyer->buyerReference);
        $this->assertNull($buyer->accountingReference);
        $this->assertNull($buyer->legalForm);
        $this->assertNull($buyer->tradingName);
        $this->assertNull($buyer->deliveryAddress);
        $this->assertNull($buyer->purchaseOrderReference);
    }

    public function testConstructWithAllFields(): void
    {
        $address = new AddressData(
            line1: '123 Rue du Client',
            city: 'Lyon',
            postalCode: '69001',
        );

        $deliveryAddress = new AddressData(
            line1: '456 Rue de Livraison',
            city: 'Marseille',
            postalCode: '13001',
        );

        $buyer = new BuyerData(
            name: 'Client SARL',
            address: $address,
            siret: '12345678901234',
            vatNumber: 'FR12345678901',
            email: 'contact@client.fr',
            phone: '+33123456789',
            buyerReference: 'REF-CLIENT-001',
            accountingReference: 'ACC-2024-001',
            legalForm: 'SARL',
            tradingName: 'Client & Co',
            deliveryAddress: $deliveryAddress,
            purchaseOrderReference: 'PO-2024-001',
        );

        $this->assertSame('Client SARL', $buyer->name);
        $this->assertSame($address, $buyer->address);
        $this->assertSame('12345678901234', $buyer->siret);
        $this->assertSame('FR12345678901', $buyer->vatNumber);
        $this->assertSame('contact@client.fr', $buyer->email);
        $this->assertSame('+33123456789', $buyer->phone);
        $this->assertSame('REF-CLIENT-001', $buyer->buyerReference);
        $this->assertSame('ACC-2024-001', $buyer->accountingReference);
        $this->assertSame('SARL', $buyer->legalForm);
        $this->assertSame('Client & Co', $buyer->tradingName);
        $this->assertSame($deliveryAddress, $buyer->deliveryAddress);
        $this->assertSame('PO-2024-001', $buyer->purchaseOrderReference);
    }

    public function testClassIsReadonly(): void
    {
        $reflection = new \ReflectionClass(BuyerData::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testFromCustomerData(): void
    {
        $customer = new CustomerData(
            name: 'Client SARL',
            address: "123 Rue du Client\n69001 Lyon",
            email: 'contact@client.fr',
            phone: '+33123456789',
            siret: '12345678901234',
            vatNumber: 'FR12345678901',
        );

        $buyer = BuyerData::fromCustomerData($customer);

        $this->assertSame('Client SARL', $buyer->name);
        $this->assertSame('123 Rue du Client', $buyer->address->line1);
        $this->assertSame('69001', $buyer->address->postalCode);
        $this->assertSame('Lyon', $buyer->address->city);
        $this->assertSame('FR', $buyer->address->countryCode);
        $this->assertSame('12345678901234', $buyer->siret);
        $this->assertSame('FR12345678901', $buyer->vatNumber);
        $this->assertSame('contact@client.fr', $buyer->email);
        $this->assertSame('+33123456789', $buyer->phone);
    }

    public function testHasDeliveryAddressReturnsFalseWhenNoDeliveryAddress(): void
    {
        $address = new AddressData(
            line1: '123 Rue du Client',
            city: 'Lyon',
            postalCode: '69001',
        );

        $buyer = new BuyerData(
            name: 'Client SARL',
            address: $address,
        );

        $this->assertFalse($buyer->hasDeliveryAddress());
    }

    public function testHasDeliveryAddressReturnsTrueWhenDeliveryAddressExists(): void
    {
        $address = new AddressData(
            line1: '123 Rue du Client',
            city: 'Lyon',
            postalCode: '69001',
        );

        $deliveryAddress = new AddressData(
            line1: '456 Rue de Livraison',
            city: 'Marseille',
            postalCode: '13001',
        );

        $buyer = new BuyerData(
            name: 'Client SARL',
            address: $address,
            deliveryAddress: $deliveryAddress,
        );

        $this->assertTrue($buyer->hasDeliveryAddress());
    }
}
