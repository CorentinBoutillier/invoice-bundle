<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Enum;

use CorentinBoutillier\InvoiceBundle\Enum\PaymentMeansCode;
use CorentinBoutillier\InvoiceBundle\Enum\PaymentMethod;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PaymentMeansCode enum (UN/CEFACT codes).
 */
class PaymentMeansCodeTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $cases = PaymentMeansCode::cases();

        $this->assertCount(8, $cases);
        $this->assertContains(PaymentMeansCode::CASH, $cases);
        $this->assertContains(PaymentMeansCode::CHECK, $cases);
        $this->assertContains(PaymentMeansCode::CREDIT_TRANSFER, $cases);
        $this->assertContains(PaymentMeansCode::BANK_ACCOUNT, $cases);
        $this->assertContains(PaymentMeansCode::CREDIT_CARD, $cases);
        $this->assertContains(PaymentMeansCode::DIRECT_DEBIT, $cases);
        $this->assertContains(PaymentMeansCode::SEPA_CREDIT_TRANSFER, $cases);
        $this->assertContains(PaymentMeansCode::SEPA_DIRECT_DEBIT, $cases);
    }

    public function testValuesAreUncefactCodes(): void
    {
        $this->assertSame('10', PaymentMeansCode::CASH->value);
        $this->assertSame('20', PaymentMeansCode::CHECK->value);
        $this->assertSame('30', PaymentMeansCode::CREDIT_TRANSFER->value);
        $this->assertSame('42', PaymentMeansCode::BANK_ACCOUNT->value);
        $this->assertSame('48', PaymentMeansCode::CREDIT_CARD->value);
        $this->assertSame('49', PaymentMeansCode::DIRECT_DEBIT->value);
        $this->assertSame('58', PaymentMeansCode::SEPA_CREDIT_TRANSFER->value);
        $this->assertSame('59', PaymentMeansCode::SEPA_DIRECT_DEBIT->value);
    }

    public function testFromMethodReturnsCorrectCase(): void
    {
        $this->assertSame(PaymentMeansCode::CASH, PaymentMeansCode::from('10'));
        $this->assertSame(PaymentMeansCode::CHECK, PaymentMeansCode::from('20'));
        $this->assertSame(PaymentMeansCode::SEPA_CREDIT_TRANSFER, PaymentMeansCode::from('58'));
    }

    public function testFromMethodThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        PaymentMeansCode::from('99');
    }

    public function testTryFromMethodReturnsNullForInvalidValue(): void
    {
        $this->assertNull(PaymentMeansCode::tryFrom('99'));
    }

    /**
     * @dataProvider labelDataProvider
     */
    public function testGetLabelReturnsCorrectLabel(PaymentMeansCode $code, string $expectedLabel): void
    {
        $this->assertSame($expectedLabel, $code->getLabel());
    }

    /**
     * @return array<string, array{PaymentMeansCode, string}>
     */
    public static function labelDataProvider(): array
    {
        return [
            'CASH' => [PaymentMeansCode::CASH, 'Especes'],
            'CHECK' => [PaymentMeansCode::CHECK, 'Cheque'],
            'CREDIT_TRANSFER' => [PaymentMeansCode::CREDIT_TRANSFER, 'Virement'],
            'BANK_ACCOUNT' => [PaymentMeansCode::BANK_ACCOUNT, 'Compte bancaire'],
            'CREDIT_CARD' => [PaymentMeansCode::CREDIT_CARD, 'Carte bancaire'],
            'DIRECT_DEBIT' => [PaymentMeansCode::DIRECT_DEBIT, 'Prelevement'],
            'SEPA_CREDIT_TRANSFER' => [PaymentMeansCode::SEPA_CREDIT_TRANSFER, 'Virement SEPA'],
            'SEPA_DIRECT_DEBIT' => [PaymentMeansCode::SEPA_DIRECT_DEBIT, 'Prelevement SEPA'],
        ];
    }

    /**
     * @dataProvider paymentMethodMappingDataProvider
     */
    public function testFromPaymentMethodReturnsCorrectCode(PaymentMethod $method, PaymentMeansCode $expectedCode): void
    {
        $this->assertSame($expectedCode, PaymentMeansCode::fromPaymentMethod($method));
    }

    /**
     * @return array<string, array{PaymentMethod, PaymentMeansCode}>
     */
    public static function paymentMethodMappingDataProvider(): array
    {
        return [
            'CASH' => [PaymentMethod::CASH, PaymentMeansCode::CASH],
            'CHECK' => [PaymentMethod::CHECK, PaymentMeansCode::CHECK],
            'BANK_TRANSFER' => [PaymentMethod::BANK_TRANSFER, PaymentMeansCode::SEPA_CREDIT_TRANSFER],
            'CREDIT_CARD' => [PaymentMethod::CREDIT_CARD, PaymentMeansCode::CREDIT_CARD],
            'DIRECT_DEBIT' => [PaymentMethod::DIRECT_DEBIT, PaymentMeansCode::SEPA_DIRECT_DEBIT],
            'OTHER' => [PaymentMethod::OTHER, PaymentMeansCode::CREDIT_TRANSFER],
        ];
    }
}
