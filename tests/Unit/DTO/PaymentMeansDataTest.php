<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\DTO;

use CorentinBoutillier\InvoiceBundle\DTO\CompanyData;
use CorentinBoutillier\InvoiceBundle\DTO\PaymentMeansData;
use CorentinBoutillier\InvoiceBundle\Enum\PaymentMeansCode;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PaymentMeansData DTO.
 */
class PaymentMeansDataTest extends TestCase
{
    public function testConstructWithRequiredFieldsOnly(): void
    {
        $payment = new PaymentMeansData(
            typeCode: PaymentMeansCode::SEPA_CREDIT_TRANSFER,
        );

        $this->assertSame(PaymentMeansCode::SEPA_CREDIT_TRANSFER, $payment->typeCode);
        $this->assertNull($payment->iban);
        $this->assertNull($payment->bic);
        $this->assertNull($payment->bankName);
        $this->assertNull($payment->remittanceInformation);
        $this->assertNull($payment->paymentId);
    }

    public function testConstructWithAllFields(): void
    {
        $payment = new PaymentMeansData(
            typeCode: PaymentMeansCode::SEPA_CREDIT_TRANSFER,
            iban: 'FR7630001007941234567890185',
            bic: 'BDFEFRPP',
            bankName: 'Banque de France',
            remittanceInformation: 'Facture FA-2024-001',
            paymentId: 'REF-12345',
        );

        $this->assertSame(PaymentMeansCode::SEPA_CREDIT_TRANSFER, $payment->typeCode);
        $this->assertSame('FR7630001007941234567890185', $payment->iban);
        $this->assertSame('BDFEFRPP', $payment->bic);
        $this->assertSame('Banque de France', $payment->bankName);
        $this->assertSame('Facture FA-2024-001', $payment->remittanceInformation);
        $this->assertSame('REF-12345', $payment->paymentId);
    }

    public function testClassIsReadonly(): void
    {
        $reflection = new \ReflectionClass(PaymentMeansData::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testFromCompanyDataWithDefaultTypeCode(): void
    {
        $company = new CompanyData(
            name: 'ACME Corp',
            address: '123 Rue de Paris',
            iban: 'FR7630001007941234567890185',
            bic: 'BDFEFRPP',
            bankName: 'Banque de France',
        );

        $payment = PaymentMeansData::fromCompanyData($company);

        $this->assertSame(PaymentMeansCode::SEPA_CREDIT_TRANSFER, $payment->typeCode);
        $this->assertSame('FR7630001007941234567890185', $payment->iban);
        $this->assertSame('BDFEFRPP', $payment->bic);
        $this->assertSame('Banque de France', $payment->bankName);
    }

    public function testFromCompanyDataWithCustomTypeCode(): void
    {
        $company = new CompanyData(
            name: 'ACME Corp',
            address: '123 Rue de Paris',
            iban: 'FR7630001007941234567890185',
        );

        $payment = PaymentMeansData::fromCompanyData($company, PaymentMeansCode::CREDIT_TRANSFER);

        $this->assertSame(PaymentMeansCode::CREDIT_TRANSFER, $payment->typeCode);
    }

    public function testFromCompanyDataWithoutBankDetails(): void
    {
        $company = new CompanyData(
            name: 'ACME Corp',
            address: '123 Rue de Paris',
        );

        $payment = PaymentMeansData::fromCompanyData($company);

        $this->assertNull($payment->iban);
        $this->assertNull($payment->bic);
        $this->assertNull($payment->bankName);
    }
}
