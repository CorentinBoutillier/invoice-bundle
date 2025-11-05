<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Provider;

use CorentinBoutillier\InvoiceBundle\DTO\CompanyData;
use CorentinBoutillier\InvoiceBundle\Provider\CompanyProviderInterface;
use CorentinBoutillier\InvoiceBundle\Provider\ConfigCompanyProvider;
use PHPUnit\Framework\TestCase;

final class ConfigCompanyProviderTest extends TestCase
{
    public function testImplementsCompanyProviderInterface(): void
    {
        $config = ['name' => 'ACME SARL', 'address' => '123 Rue de Test'];
        $provider = new ConfigCompanyProvider($config);

        $this->assertInstanceOf(CompanyProviderInterface::class, $provider);
    }

    public function testGetCompanyDataReturnsCompanyDataDTO(): void
    {
        $config = [
            'name' => 'ACME SARL',
            'address' => '123 Rue de Test, 75001 Paris',
        ];
        $provider = new ConfigCompanyProvider($config);

        $result = $provider->getCompanyData();

        $this->assertInstanceOf(CompanyData::class, $result);
    }

    public function testGetCompanyDataWithMinimalConfiguration(): void
    {
        $config = [
            'name' => 'ACME SARL',
            'address' => '123 Rue de Test, 75001 Paris',
        ];
        $provider = new ConfigCompanyProvider($config);

        $result = $provider->getCompanyData();

        $this->assertSame('ACME SARL', $result->name);
        $this->assertSame('123 Rue de Test, 75001 Paris', $result->address);
        $this->assertNull($result->siret);
        $this->assertNull($result->vatNumber);
    }

    public function testGetCompanyDataWithCompleteConfiguration(): void
    {
        $config = [
            'name' => 'ACME SARL',
            'address' => '123 Rue de Test, 75001 Paris',
            'siret' => '12345678901234',
            'vatNumber' => 'FR12345678901',
            'email' => 'contact@acme.fr',
            'phone' => '+33123456789',
            'logo' => '/path/to/logo.png',
            'legalForm' => 'SARL',
            'capital' => '10000 EUR',
            'rcs' => 'Paris B 123 456 789',
            'bankName' => 'Banque Populaire',
            'iban' => 'FR7612345678901234567890123',
            'bic' => 'BPOPFRPPXXX',
        ];
        $provider = new ConfigCompanyProvider($config);

        $result = $provider->getCompanyData();

        $this->assertSame('ACME SARL', $result->name);
        $this->assertSame('123 Rue de Test, 75001 Paris', $result->address);
        $this->assertSame('12345678901234', $result->siret);
        $this->assertSame('FR12345678901', $result->vatNumber);
        $this->assertSame('contact@acme.fr', $result->email);
        $this->assertSame('+33123456789', $result->phone);
        $this->assertSame('/path/to/logo.png', $result->logo);
        $this->assertSame('SARL', $result->legalForm);
        $this->assertSame('10000 EUR', $result->capital);
        $this->assertSame('Paris B 123 456 789', $result->rcs);
        $this->assertSame('Banque Populaire', $result->bankName);
        $this->assertSame('FR7612345678901234567890123', $result->iban);
        $this->assertSame('BPOPFRPPXXX', $result->bic);
    }

    public function testGetCompanyDataWithNullCompanyIdForMonoCompany(): void
    {
        $config = [
            'name' => 'ACME SARL',
            'address' => '123 Rue de Test, 75001 Paris',
        ];
        $provider = new ConfigCompanyProvider($config);

        $result = $provider->getCompanyData(null);

        $this->assertInstanceOf(CompanyData::class, $result);
        $this->assertSame('ACME SARL', $result->name);
    }

    public function testGetCompanyDataWithDefaultFiscalYearValues(): void
    {
        $config = [
            'name' => 'ACME SARL',
            'address' => '123 Rue de Test, 75001 Paris',
        ];
        $provider = new ConfigCompanyProvider($config);

        $result = $provider->getCompanyData();

        $this->assertSame(1, $result->fiscalYearStartMonth);
        $this->assertSame(1, $result->fiscalYearStartDay);
        $this->assertSame(0, $result->fiscalYearStartYear);
    }

    public function testGetCompanyDataWithCustomFiscalYear(): void
    {
        $config = [
            'name' => 'ACME SARL',
            'address' => '123 Rue de Test, 75001 Paris',
            'fiscalYearStartMonth' => 11,
            'fiscalYearStartDay' => 1,
        ];
        $provider = new ConfigCompanyProvider($config);

        $result = $provider->getCompanyData();

        $this->assertSame(11, $result->fiscalYearStartMonth);
        $this->assertSame(1, $result->fiscalYearStartDay);
    }

    public function testGetCompanyDataThrowsExceptionForMultiCompanyWithCompanyId(): void
    {
        $config = [
            'name' => 'ACME SARL',
            'address' => '123 Rue de Test, 75001 Paris',
        ];
        $provider = new ConfigCompanyProvider($config);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('ConfigCompanyProvider does not support multi-company mode');

        $provider->getCompanyData(1);
    }

    public function testGetCompanyDataThrowsExceptionWhenNameIsMissing(): void
    {
        $config = [
            'address' => '123 Rue de Test, 75001 Paris',
        ];
        $provider = new ConfigCompanyProvider($config);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Company name is required');

        $provider->getCompanyData();
    }

    public function testGetCompanyDataThrowsExceptionWhenAddressIsMissing(): void
    {
        $config = [
            'name' => 'ACME SARL',
        ];
        $provider = new ConfigCompanyProvider($config);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Company address is required');

        $provider->getCompanyData();
    }
}
