<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\DTO;

use CorentinBoutillier\InvoiceBundle\DTO\CustomerData;
use PHPUnit\Framework\TestCase;

class CustomerDataTest extends TestCase
{
    public function testConstructWithRequiredFieldsOnly(): void
    {
        $data = new CustomerData(
            name: 'Client ABC',
            address: '456 avenue des Champs, 75008 Paris',
        );

        $this->assertSame('Client ABC', $data->name);
        $this->assertSame('456 avenue des Champs, 75008 Paris', $data->address);
        $this->assertNull($data->email);
        $this->assertNull($data->phone);
        $this->assertNull($data->siret);
        $this->assertNull($data->vatNumber);
    }

    public function testConstructWithAllFields(): void
    {
        $data = new CustomerData(
            name: 'Client ABC',
            address: '456 avenue des Champs, 75008 Paris',
            email: 'client@abc.fr',
            phone: '+33 1 98 76 54 32',
            siret: '98765432109876',
            vatNumber: 'FR98765432109',
        );

        $this->assertSame('Client ABC', $data->name);
        $this->assertSame('456 avenue des Champs, 75008 Paris', $data->address);
        $this->assertSame('client@abc.fr', $data->email);
        $this->assertSame('+33 1 98 76 54 32', $data->phone);
        $this->assertSame('98765432109876', $data->siret);
        $this->assertSame('FR98765432109', $data->vatNumber);
    }

    public function testPropertiesAreReadonly(): void
    {
        $data = new CustomerData(
            name: 'Client ABC',
            address: '456 avenue des Champs, 75008 Paris',
        );

        $reflection = new \ReflectionClass($data);

        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue(
                $property->isReadOnly(),
                \sprintf('Property %s should be readonly', $property->getName()),
            );
        }
    }

    public function testClassIsReadonly(): void
    {
        $reflection = new \ReflectionClass(CustomerData::class);

        $this->assertTrue(
            $reflection->isReadOnly(),
            'CustomerData class should be readonly',
        );
    }
}
