<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\DTO;

use CorentinBoutillier\InvoiceBundle\DTO\CompanyData;
use PHPUnit\Framework\TestCase;

class CompanyDataTest extends TestCase
{
    public function testConstructWithRequiredFieldsOnly(): void
    {
        $data = new CompanyData(
            name: 'ACME SARL',
            address: '123 rue de la Paix, 75001 Paris',
        );

        $this->assertSame('ACME SARL', $data->name);
        $this->assertSame('123 rue de la Paix, 75001 Paris', $data->address);
        $this->assertNull($data->siret);
        $this->assertNull($data->vatNumber);
        $this->assertNull($data->email);
        $this->assertNull($data->phone);
        $this->assertNull($data->logo);
        $this->assertNull($data->legalForm);
        $this->assertNull($data->capital);
        $this->assertNull($data->rcs);
        $this->assertSame(1, $data->fiscalYearStartMonth);
        $this->assertSame(1, $data->fiscalYearStartDay);
        $this->assertSame(0, $data->fiscalYearStartYear);
        $this->assertNull($data->bankName);
        $this->assertNull($data->iban);
        $this->assertNull($data->bic);
    }

    public function testConstructWithAllFields(): void
    {
        $data = new CompanyData(
            name: 'ACME SARL',
            address: '123 rue de la Paix, 75001 Paris',
            siret: '12345678901234',
            vatNumber: 'FR12345678901',
            email: 'contact@acme.fr',
            phone: '+33 1 23 45 67 89',
            logo: '/path/to/logo.png',
            legalForm: 'SARL',
            capital: '10000',
            rcs: 'Paris B 123 456 789',
            fiscalYearStartMonth: 11,
            fiscalYearStartDay: 1,
            fiscalYearStartYear: 2024,
            bankName: 'Crédit Agricole',
            iban: 'FR76 1234 5678 9012 3456 7890 123',
            bic: 'AGRIFRPP123',
        );

        $this->assertSame('ACME SARL', $data->name);
        $this->assertSame('123 rue de la Paix, 75001 Paris', $data->address);
        $this->assertSame('12345678901234', $data->siret);
        $this->assertSame('FR12345678901', $data->vatNumber);
        $this->assertSame('contact@acme.fr', $data->email);
        $this->assertSame('+33 1 23 45 67 89', $data->phone);
        $this->assertSame('/path/to/logo.png', $data->logo);
        $this->assertSame('SARL', $data->legalForm);
        $this->assertSame('10000', $data->capital);
        $this->assertSame('Paris B 123 456 789', $data->rcs);
        $this->assertSame(11, $data->fiscalYearStartMonth);
        $this->assertSame(1, $data->fiscalYearStartDay);
        $this->assertSame(2024, $data->fiscalYearStartYear);
        $this->assertSame('Crédit Agricole', $data->bankName);
        $this->assertSame('FR76 1234 5678 9012 3456 7890 123', $data->iban);
        $this->assertSame('AGRIFRPP123', $data->bic);
    }

    public function testPropertiesAreReadonly(): void
    {
        $data = new CompanyData(
            name: 'ACME SARL',
            address: '123 rue de la Paix, 75001 Paris',
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
        $reflection = new \ReflectionClass(CompanyData::class);

        $this->assertTrue(
            $reflection->isReadOnly(),
            'CompanyData class should be readonly',
        );
    }
}
