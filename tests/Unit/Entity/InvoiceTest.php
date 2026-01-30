<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Entity;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;
use CorentinBoutillier\InvoiceBundle\Entity\Payment;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Enum\OperationCategory;
use CorentinBoutillier\InvoiceBundle\Enum\PaymentMethod;
use PHPUnit\Framework\TestCase;

final class InvoiceTest extends TestCase
{
    // ========== Construction & Basic Properties ==========

    public function testConstructWithMinimalRequiredData(): void
    {
        $date = new \DateTimeImmutable('2024-06-15');
        $dueDate = new \DateTimeImmutable('2024-07-15');

        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: $date,
            dueDate: $dueDate,
            customerName: 'ACME Corporation',
            customerAddress: '123 Main St, 75001 Paris',
            companyName: 'My Company SAS',
            companyAddress: '456 Business Ave, 75002 Paris',
        );

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame(InvoiceType::INVOICE, $invoice->getType());
        $this->assertSame($date, $invoice->getDate());
        $this->assertSame($dueDate, $invoice->getDueDate());
    }

    public function testStatusDefaultsToDraft(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $this->assertSame(InvoiceStatus::DRAFT, $invoice->getStatus());
    }

    public function testNumberIsNullForDraft(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $this->assertNull($invoice->getNumber());
    }

    // ========== Invoice Type ==========

    public function testInvoiceType(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $this->assertSame(InvoiceType::INVOICE, $invoice->getType());
    }

    public function testCreditNoteType(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::CREDIT_NOTE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $this->assertSame(InvoiceType::CREDIT_NOTE, $invoice->getType());
    }

    // ========== Customer Snapshot (NO Relations) ==========

    public function testCustomerSnapshotData(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'ACME Corporation',
            customerAddress: '123 Main Street, 75001 Paris, France',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $this->assertSame('ACME Corporation', $invoice->getCustomerName());
        $this->assertSame('123 Main Street, 75001 Paris, France', $invoice->getCustomerAddress());
    }

    public function testCustomerOptionalFields(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'ACME Corporation',
            customerAddress: '123 Main St',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $invoice->setCustomerEmail('contact@acme.com');
        $invoice->setCustomerPhone('+33 1 23 45 67 89');
        $invoice->setCustomerSiret('12345678901234');
        $invoice->setCustomerVatNumber('FR12345678901');

        $this->assertSame('contact@acme.com', $invoice->getCustomerEmail());
        $this->assertSame('+33 1 23 45 67 89', $invoice->getCustomerPhone());
        $this->assertSame('12345678901234', $invoice->getCustomerSiret());
        $this->assertSame('FR12345678901', $invoice->getCustomerVatNumber());
    }

    public function testCustomerOptionalFieldsAreNullByDefault(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $this->assertNull($invoice->getCustomerEmail());
        $this->assertNull($invoice->getCustomerPhone());
        $this->assertNull($invoice->getCustomerSiret());
        $this->assertNull($invoice->getCustomerVatNumber());
    }

    // ========== Company Snapshot (NO Relations) ==========

    public function testCompanySnapshotData(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'My Company SAS',
            companyAddress: '456 Business Avenue, 75002 Paris',
        );

        $this->assertSame('My Company SAS', $invoice->getCompanyName());
        $this->assertSame('456 Business Avenue, 75002 Paris', $invoice->getCompanyAddress());
    }

    public function testCompanyOptionalFields(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'My Company',
            companyAddress: 'Address',
        );

        $invoice->setCompanySiret('98765432109876');
        $invoice->setCompanyVatNumber('FR98765432109');
        $invoice->setCompanyEmail('billing@mycompany.com');
        $invoice->setCompanyPhone('+33 1 98 76 54 32');

        $this->assertSame('98765432109876', $invoice->getCompanySiret());
        $this->assertSame('FR98765432109', $invoice->getCompanyVatNumber());
        $this->assertSame('billing@mycompany.com', $invoice->getCompanyEmail());
        $this->assertSame('+33 1 98 76 54 32', $invoice->getCompanyPhone());
    }

    // ========== Lines Collection ==========

    public function testAddLine(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $line = new InvoiceLine(
            description: 'Prestation de développement',
            quantity: 10.0,
            unitPrice: Money::fromEuros('80.00'),
            vatRate: 20.0,
        );

        $invoice->addLine($line);

        $lines = $invoice->getLines();
        $this->assertCount(1, $lines);
        $this->assertSame($line, $lines[0]);
    }

    public function testAddMultipleLines(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $line1 = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $line2 = new InvoiceLine('Item 2', 2.0, Money::fromEuros('50.00'), 20.0);
        $line3 = new InvoiceLine('Item 3', 3.0, Money::fromEuros('25.00'), 10.0);

        $invoice->addLine($line1);
        $invoice->addLine($line2);
        $invoice->addLine($line3);

        $lines = $invoice->getLines();
        $this->assertCount(3, $lines);
    }

    public function testLinesCollectionStartsEmpty(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $this->assertCount(0, $invoice->getLines());
    }

    // ========== Payments Collection ==========

    public function testAddPayment(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $payment = new Payment(
            amount: Money::fromEuros('100.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $invoice->addPayment($payment);

        $payments = $invoice->getPayments();
        $this->assertCount(1, $payments);
        $this->assertSame($payment, $payments[0]);
    }

    public function testAddMultiplePayments(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $payment1 = new Payment(Money::fromEuros('50.00'), new \DateTimeImmutable(), PaymentMethod::BANK_TRANSFER);
        $payment2 = new Payment(Money::fromEuros('30.00'), new \DateTimeImmutable(), PaymentMethod::CREDIT_CARD);
        $payment3 = new Payment(Money::fromEuros('20.00'), new \DateTimeImmutable(), PaymentMethod::CHECK);

        $invoice->addPayment($payment1);
        $invoice->addPayment($payment2);
        $invoice->addPayment($payment3);

        $payments = $invoice->getPayments();
        $this->assertCount(3, $payments);
    }

    public function testPaymentsCollectionStartsEmpty(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $this->assertCount(0, $invoice->getPayments());
    }

    // ========== Payment Terms ==========

    public function testPaymentTermsCanBeSet(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $invoice->setPaymentTerms('30 jours net');

        $this->assertSame('30 jours net', $invoice->getPaymentTerms());
    }

    public function testPaymentTermsIsNullByDefault(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $this->assertNull($invoice->getPaymentTerms());
    }

    // ========== Credit Note Specific ==========

    public function testCreditNoteCanReferenceOriginalInvoice(): void
    {
        $originalInvoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-01'),
            dueDate: new \DateTimeImmutable('2024-07-01'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $creditNote = new Invoice(
            type: InvoiceType::CREDIT_NOTE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $creditNote->setCreditedInvoice($originalInvoice);

        $this->assertSame($originalInvoice, $creditNote->getCreditedInvoice());
    }

    public function testCreditedInvoiceIsNullByDefault(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $this->assertNull($invoice->getCreditedInvoice());
    }

    // ========== ID Management ==========

    public function testIdIsNullBeforePersistence(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $this->assertNull($invoice->getId());
    }

    // ========== Timestamps ==========

    public function testCreatedAtIsSetAutomatically(): void
    {
        $before = new \DateTimeImmutable();

        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $after = new \DateTimeImmutable();

        $createdAt = $invoice->getCreatedAt();
        $this->assertInstanceOf(\DateTimeImmutable::class, $createdAt);
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $createdAt->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $createdAt->getTimestamp());
    }

    public function testUpdatedAtIsSetAutomatically(): void
    {
        $before = new \DateTimeImmutable();

        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $after = new \DateTimeImmutable();

        $updatedAt = $invoice->getUpdatedAt();
        $this->assertInstanceOf(\DateTimeImmutable::class, $updatedAt);
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $updatedAt->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $updatedAt->getTimestamp());
    }

    // ========== Simple Calculations (no global discount) ==========

    public function testGetSubtotalBeforeDiscountWithNoLines(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $subtotal = $invoice->getSubtotalBeforeDiscount();

        $this->assertInstanceOf(Money::class, $subtotal);
        $this->assertTrue($subtotal->isZero());
        $this->assertSame('0.00', $subtotal->toEuros());
    }

    public function testGetSubtotalBeforeDiscountWithOneLine(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $line = new InvoiceLine('Item', 2.0, Money::fromEuros('50.00'), 20.0);
        $invoice->addLine($line);

        $subtotal = $invoice->getSubtotalBeforeDiscount();

        // 2 × 50€ = 100€
        $this->assertSame('100.00', $subtotal->toEuros());
    }

    public function testGetSubtotalBeforeDiscountWithMultipleLines(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $line1 = new InvoiceLine('Item 1', 2.0, Money::fromEuros('50.00'), 20.0);
        $line2 = new InvoiceLine('Item 2', 1.0, Money::fromEuros('30.00'), 20.0);
        $line3 = new InvoiceLine('Item 3', 3.0, Money::fromEuros('15.00'), 10.0);

        $invoice->addLine($line1);
        $invoice->addLine($line2);
        $invoice->addLine($line3);

        $subtotal = $invoice->getSubtotalBeforeDiscount();

        // (2×50) + (1×30) + (3×15) = 100 + 30 + 45 = 175€
        $this->assertSame('175.00', $subtotal->toEuros());
    }

    public function testGetSubtotalIncludesLineDiscounts(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $line = new InvoiceLine('Item', 1.0, Money::fromEuros('100.00'), 20.0);
        $line->setDiscountRate(10.0); // 10% de remise ligne
        $invoice->addLine($line);

        $subtotal = $invoice->getSubtotalBeforeDiscount();

        // 100€ - 10% = 90€
        $this->assertSame('90.00', $subtotal->toEuros());
    }

    // ========== Total VAT ==========

    public function testGetTotalVatWithNoLines(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $totalVat = $invoice->getTotalVat();

        $this->assertTrue($totalVat->isZero());
        $this->assertSame('0.00', $totalVat->toEuros());
    }

    public function testGetTotalVatWithOneLine(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $line = new InvoiceLine('Item', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $totalVat = $invoice->getTotalVat();

        // 100€ × 20% = 20€
        $this->assertSame('20.00', $totalVat->toEuros());
    }

    public function testGetTotalVatWithMultipleLinesAndDifferentRates(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $line1 = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $line2 = new InvoiceLine('Item 2', 1.0, Money::fromEuros('50.00'), 10.0);
        $line3 = new InvoiceLine('Item 3', 1.0, Money::fromEuros('20.00'), 5.5);

        $invoice->addLine($line1);
        $invoice->addLine($line2);
        $invoice->addLine($line3);

        $totalVat = $invoice->getTotalVat();

        // (100×20%) + (50×10%) + (20×5.5%) = 20 + 5 + 1.10 = 26.10€
        $this->assertSame('26.10', $totalVat->toEuros());
    }

    public function testGetTotalVatIncludesLineDiscounts(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $line = new InvoiceLine('Item', 1.0, Money::fromEuros('100.00'), 20.0);
        $line->setDiscountRate(10.0); // Remise ligne
        $invoice->addLine($line);

        $totalVat = $invoice->getTotalVat();

        // (100€ - 10%) × 20% = 90€ × 20% = 18€
        $this->assertSame('18.00', $totalVat->toEuros());
    }

    // ========== Total Including VAT (no global discount) ==========

    public function testGetTotalIncludingVatWithNoLines(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $totalTTC = $invoice->getTotalIncludingVat();

        $this->assertTrue($totalTTC->isZero());
        $this->assertSame('0.00', $totalTTC->toEuros());
    }

    public function testGetTotalIncludingVatWithOneLine(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $line = new InvoiceLine('Item', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $totalTTC = $invoice->getTotalIncludingVat();

        // 100€ HT + 20€ TVA = 120€ TTC
        $this->assertSame('120.00', $totalTTC->toEuros());
    }

    public function testGetTotalIncludingVatWithMultipleLines(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $line1 = new InvoiceLine('Item 1', 2.0, Money::fromEuros('50.00'), 20.0);
        $line2 = new InvoiceLine('Item 2', 1.0, Money::fromEuros('30.00'), 10.0);

        $invoice->addLine($line1);
        $invoice->addLine($line2);

        $totalTTC = $invoice->getTotalIncludingVat();

        // Line 1: 2×50 = 100€ HT + 20€ TVA = 120€ TTC
        // Line 2: 1×30 = 30€ HT + 3€ TVA = 33€ TTC
        // Total: 120 + 33 = 153€ TTC
        $this->assertSame('153.00', $totalTTC->toEuros());
    }

    public function testGetTotalIncludingVatWithLineDiscounts(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $line = new InvoiceLine('Item', 1.0, Money::fromEuros('100.00'), 20.0);
        $line->setDiscountRate(10.0);
        $invoice->addLine($line);

        $totalTTC = $invoice->getTotalIncludingVat();

        // (100€ - 10%) = 90€ HT + 18€ TVA = 108€ TTC
        $this->assertSame('108.00', $totalTTC->toEuros());
    }

    // ========== Calculation Consistency ==========

    public function testCalculationConsistency(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $line = new InvoiceLine('Item', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $subtotal = $invoice->getSubtotalBeforeDiscount();
        $vat = $invoice->getTotalVat();
        $total = $invoice->getTotalIncludingVat();

        // Vérifier que Total TTC = Sous-total HT + TVA
        $calculated = $subtotal->add($vat);
        $this->assertTrue($calculated->equals($total));
    }

    // ========== Global Discount ==========

    public function testGlobalDiscountIsNullByDefault(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $this->assertNull($invoice->getGlobalDiscountRate());
        $this->assertTrue($invoice->getGlobalDiscountAmount()->isZero());
        $this->assertSame('0.00', $invoice->getGlobalDiscountAmount()->toEuros());
    }

    public function testSetGlobalDiscountRate(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $invoice->setGlobalDiscountRate(10.0);

        $this->assertSame(10.0, $invoice->getGlobalDiscountRate());
    }

    public function testSetGlobalDiscountAmount(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $invoice->setGlobalDiscountAmount(Money::fromEuros('50.00'));

        $this->assertInstanceOf(Money::class, $invoice->getGlobalDiscountAmount());
        $this->assertSame('50.00', $invoice->getGlobalDiscountAmount()->toEuros());
    }

    public function testGetGlobalDiscountAmountWithNoDiscount(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $this->assertSame('0.00', $invoice->getGlobalDiscountAmount()->toEuros());
    }

    public function testGetGlobalDiscountAmountWithRate(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $invoice->setGlobalDiscountRate(10.0);

        // 100€ × 10% = 10€
        $this->assertSame('10.00', $invoice->getGlobalDiscountAmount()->toEuros());
    }

    public function testGetGlobalDiscountAmountWithFixedAmount(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $invoice->setGlobalDiscountAmount(Money::fromEuros('15.00'));

        $this->assertSame('15.00', $invoice->getGlobalDiscountAmount()->toEuros());
    }

    public function testGlobalDiscountFixedAmountTakesPriorityOverRate(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        // Définir les deux
        $invoice->setGlobalDiscountRate(10.0); // 10€
        $invoice->setGlobalDiscountAmount(Money::fromEuros('15.00')); // 15€

        // Le montant fixe doit être prioritaire
        $this->assertSame('15.00', $invoice->getGlobalDiscountAmount()->toEuros());
    }

    public function testGetSubtotalAfterDiscountWithNoGlobalDiscount(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        // Sans remise globale, subtotal après = subtotal avant
        $this->assertSame('100.00', $invoice->getSubtotalAfterDiscount()->toEuros());
    }

    public function testGetSubtotalAfterDiscountWithGlobalDiscountRate(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $invoice->setGlobalDiscountRate(10.0);

        // 100€ - 10% = 90€
        $this->assertSame('90.00', $invoice->getSubtotalAfterDiscount()->toEuros());
    }

    public function testGetSubtotalAfterDiscountWithGlobalDiscountAmount(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $invoice->setGlobalDiscountAmount(Money::fromEuros('15.00'));

        // 100€ - 15€ = 85€
        $this->assertSame('85.00', $invoice->getSubtotalAfterDiscount()->toEuros());
    }

    public function testGetTotalIncludingVatWithGlobalDiscount(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $invoice->setGlobalDiscountRate(10.0);

        // Subtotal avant remise : 100€
        // Remise globale : 10€ (10%)
        // Subtotal après remise : 90€
        // TVA 20% sur 90€ : 18€
        // Total TTC : 90€ + 18€ = 108€

        $this->assertSame('108.00', $invoice->getTotalIncludingVat()->toEuros());
    }

    public function testComplexCalculationWithLineDiscountsAndGlobalDiscount(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        // Ligne 1 : 100€ - 10% ligne = 90€ HT
        $line1 = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $line1->setDiscountRate(10.0);
        $invoice->addLine($line1);

        // Ligne 2 : 50€ (pas de remise ligne) = 50€ HT
        $line2 = new InvoiceLine('Item 2', 1.0, Money::fromEuros('50.00'), 10.0);
        $invoice->addLine($line2);

        // Subtotal avant remise globale : 90 + 50 = 140€
        $this->assertSame('140.00', $invoice->getSubtotalBeforeDiscount()->toEuros());

        // Remise globale 5% sur 140€ = 7€
        $invoice->setGlobalDiscountRate(5.0);
        $this->assertSame('7.00', $invoice->getGlobalDiscountAmount()->toEuros());

        // Subtotal après remise globale : 140 - 7 = 133€
        $this->assertSame('133.00', $invoice->getSubtotalAfterDiscount()->toEuros());

        // TVA calculée APRÈS remise globale (distribution proportionnelle):
        // Ligne 1: 90/140 × 7 = 4.50€ remise → 85.50€ HT → TVA 20% = 17.10€
        // Ligne 2: 50/140 × 7 = 2.50€ remise → 47.50€ HT → TVA 10% = 4.75€
        // Total TVA : 17.10 + 4.75 = 21.85€
        $this->assertSame('21.85', $invoice->getTotalVat()->toEuros());

        // Total TTC : 133€ + 21.85€ = 154.85€
        $this->assertSame('154.85', $invoice->getTotalIncludingVat()->toEuros());
    }

    public function testGlobalDiscountWithZeroAmount(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $invoice->setGlobalDiscountAmount(Money::zero());

        $this->assertSame('0.00', $invoice->getGlobalDiscountAmount()->toEuros());
        $this->assertSame('100.00', $invoice->getSubtotalAfterDiscount()->toEuros());
    }

    public function testCanRemoveGlobalDiscount(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $invoice->setGlobalDiscountRate(10.0);
        $this->assertSame(10.0, $invoice->getGlobalDiscountRate());

        // Supprimer la remise
        $invoice->setGlobalDiscountRate(null);
        $this->assertNull($invoice->getGlobalDiscountRate());
        $this->assertSame('0.00', $invoice->getGlobalDiscountAmount()->toEuros());
    }

    // ========== Payment Tracking ==========

    public function testGetTotalPaidWithNoPayments(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $this->assertSame('0.00', $invoice->getTotalPaid()->toEuros());
    }

    public function testGetTotalPaidWithOnePayment(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $payment = new Payment(
            amount: Money::fromEuros('50.00'),
            paidAt: new \DateTimeImmutable('2024-06-20'),
            method: PaymentMethod::BANK_TRANSFER,
        );
        $invoice->addPayment($payment);

        $this->assertSame('50.00', $invoice->getTotalPaid()->toEuros());
    }

    public function testGetTotalPaidWithMultiplePayments(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $payment1 = new Payment(
            amount: Money::fromEuros('30.00'),
            paidAt: new \DateTimeImmutable('2024-06-20'),
            method: PaymentMethod::BANK_TRANSFER,
        );
        $payment2 = new Payment(
            amount: Money::fromEuros('50.00'),
            paidAt: new \DateTimeImmutable('2024-06-25'),
            method: PaymentMethod::CREDIT_CARD,
        );
        $payment3 = new Payment(
            amount: Money::fromEuros('40.00'),
            paidAt: new \DateTimeImmutable('2024-06-30'),
            method: PaymentMethod::CHECK,
        );

        $invoice->addPayment($payment1);
        $invoice->addPayment($payment2);
        $invoice->addPayment($payment3);

        // 30 + 50 + 40 = 120€
        $this->assertSame('120.00', $invoice->getTotalPaid()->toEuros());
    }

    public function testGetRemainingAmountWithNoPayments(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        // Total TTC: 120€, Payé: 0€, Reste: 120€
        $this->assertSame('120.00', $invoice->getRemainingAmount()->toEuros());
    }

    public function testGetRemainingAmountWithPartialPayment(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $payment = new Payment(
            amount: Money::fromEuros('50.00'),
            paidAt: new \DateTimeImmutable('2024-06-20'),
            method: PaymentMethod::BANK_TRANSFER,
        );
        $invoice->addPayment($payment);

        // Total TTC: 120€, Payé: 50€, Reste: 70€
        $this->assertSame('70.00', $invoice->getRemainingAmount()->toEuros());
    }

    public function testGetRemainingAmountWhenFullyPaid(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $payment = new Payment(
            amount: Money::fromEuros('120.00'),
            paidAt: new \DateTimeImmutable('2024-06-20'),
            method: PaymentMethod::BANK_TRANSFER,
        );
        $invoice->addPayment($payment);

        // Total TTC: 120€, Payé: 120€, Reste: 0€
        $this->assertSame('0.00', $invoice->getRemainingAmount()->toEuros());
    }

    public function testGetRemainingAmountWhenOverpaid(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $payment = new Payment(
            amount: Money::fromEuros('150.00'),
            paidAt: new \DateTimeImmutable('2024-06-20'),
            method: PaymentMethod::BANK_TRANSFER,
        );
        $invoice->addPayment($payment);

        // Total TTC: 120€, Payé: 150€, Reste: -30€ (trop-perçu)
        $this->assertSame('-30.00', $invoice->getRemainingAmount()->toEuros());
    }

    public function testIsFullyPaidReturnsFalseWithNoPayments(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $this->assertFalse($invoice->isFullyPaid());
    }

    public function testIsFullyPaidReturnsFalseWithPartialPayment(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $payment = new Payment(
            amount: Money::fromEuros('50.00'),
            paidAt: new \DateTimeImmutable('2024-06-20'),
            method: PaymentMethod::BANK_TRANSFER,
        );
        $invoice->addPayment($payment);

        $this->assertFalse($invoice->isFullyPaid());
    }

    public function testIsFullyPaidReturnsTrueWhenExactlyPaid(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $payment = new Payment(
            amount: Money::fromEuros('120.00'),
            paidAt: new \DateTimeImmutable('2024-06-20'),
            method: PaymentMethod::BANK_TRANSFER,
        );
        $invoice->addPayment($payment);

        $this->assertTrue($invoice->isFullyPaid());
    }

    public function testIsFullyPaidReturnsTrueWhenOverpaid(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $payment = new Payment(
            amount: Money::fromEuros('150.00'),
            paidAt: new \DateTimeImmutable('2024-06-20'),
            method: PaymentMethod::BANK_TRANSFER,
        );
        $invoice->addPayment($payment);

        // Même avec trop-perçu, considéré comme "payée"
        $this->assertTrue($invoice->isFullyPaid());
    }

    public function testIsPartiallyPaidReturnsFalseWithNoPayments(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $this->assertFalse($invoice->isPartiallyPaid());
    }

    public function testIsPartiallyPaidReturnsTrueWithPartialPayment(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $payment = new Payment(
            amount: Money::fromEuros('50.00'),
            paidAt: new \DateTimeImmutable('2024-06-20'),
            method: PaymentMethod::BANK_TRANSFER,
        );
        $invoice->addPayment($payment);

        $this->assertTrue($invoice->isPartiallyPaid());
    }

    public function testIsPartiallyPaidReturnsFalseWhenFullyPaid(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $payment = new Payment(
            amount: Money::fromEuros('120.00'),
            paidAt: new \DateTimeImmutable('2024-06-20'),
            method: PaymentMethod::BANK_TRANSFER,
        );
        $invoice->addPayment($payment);

        // Totalement payée = pas partiellement payée
        $this->assertFalse($invoice->isPartiallyPaid());
    }

    public function testIsPartiallyPaidReturnsFalseWhenOverpaid(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $payment = new Payment(
            amount: Money::fromEuros('150.00'),
            paidAt: new \DateTimeImmutable('2024-06-20'),
            method: PaymentMethod::BANK_TRANSFER,
        );
        $invoice->addPayment($payment);

        // Trop-payée = pas partiellement payée
        $this->assertFalse($invoice->isPartiallyPaid());
    }

    // ========== Due Date & Overdue ==========

    public function testIsOverdueReturnsFalseWhenNotYetDue(): void
    {
        $today = new \DateTimeImmutable('2024-06-15');
        $dueDate = new \DateTimeImmutable('2024-07-15'); // Dans 1 mois

        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: $today,
            dueDate: $dueDate,
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $this->assertFalse($invoice->isOverdue($today));
    }

    public function testIsOverdueReturnsFalseOnDueDate(): void
    {
        $today = new \DateTimeImmutable('2024-07-15');
        $dueDate = new \DateTimeImmutable('2024-07-15'); // Aujourd'hui

        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: $dueDate,
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        // Le jour même de l'échéance, pas encore en retard
        $this->assertFalse($invoice->isOverdue($today));
    }

    public function testIsOverdueReturnsTrueWhenPastDueAndUnpaid(): void
    {
        $today = new \DateTimeImmutable('2024-07-20'); // 5 jours après échéance
        $dueDate = new \DateTimeImmutable('2024-07-15');

        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: $dueDate,
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $this->assertTrue($invoice->isOverdue($today));
    }

    public function testIsOverdueReturnsTrueWhenPastDueAndPartiallyPaid(): void
    {
        $today = new \DateTimeImmutable('2024-07-20');
        $dueDate = new \DateTimeImmutable('2024-07-15');

        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: $dueDate,
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $payment = new Payment(
            amount: Money::fromEuros('50.00'),
            paidAt: new \DateTimeImmutable('2024-07-16'),
            method: PaymentMethod::BANK_TRANSFER,
        );
        $invoice->addPayment($payment);

        // Partiellement payée ET en retard = en retard
        $this->assertTrue($invoice->isOverdue($today));
    }

    public function testIsOverdueReturnsFalseWhenPastDueButFullyPaid(): void
    {
        $today = new \DateTimeImmutable('2024-07-20');
        $dueDate = new \DateTimeImmutable('2024-07-15');

        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: $dueDate,
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $payment = new Payment(
            amount: Money::fromEuros('120.00'),
            paidAt: new \DateTimeImmutable('2024-07-16'),
            method: PaymentMethod::BANK_TRANSFER,
        );
        $invoice->addPayment($payment);

        // Totalement payée = pas en retard, même après échéance
        $this->assertFalse($invoice->isOverdue($today));
    }

    public function testIsOverdueUsesCurrentDateWhenNotProvided(): void
    {
        // Facture échue hier
        $yesterday = new \DateTimeImmutable('yesterday');

        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('-30 days'),
            dueDate: $yesterday,
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        // Sans paramètre, utilise la date actuelle
        $this->assertTrue($invoice->isOverdue());
    }

    public function testGetDaysOverdueReturnsZeroWhenNotYetDue(): void
    {
        $today = new \DateTimeImmutable('2024-06-15');
        $dueDate = new \DateTimeImmutable('2024-07-15');

        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: $today,
            dueDate: $dueDate,
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $this->assertSame(0, $invoice->getDaysOverdue($today));
    }

    public function testGetDaysOverdueReturnsZeroOnDueDate(): void
    {
        $today = new \DateTimeImmutable('2024-07-15');
        $dueDate = new \DateTimeImmutable('2024-07-15');

        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: $dueDate,
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $this->assertSame(0, $invoice->getDaysOverdue($today));
    }

    public function testGetDaysOverdueReturnsCorrectNumberWhenOverdue(): void
    {
        $today = new \DateTimeImmutable('2024-07-20'); // 5 jours après
        $dueDate = new \DateTimeImmutable('2024-07-15');

        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: $dueDate,
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $this->assertSame(5, $invoice->getDaysOverdue($today));
    }

    public function testGetDaysOverdueReturnsZeroWhenFullyPaid(): void
    {
        $today = new \DateTimeImmutable('2024-07-20');
        $dueDate = new \DateTimeImmutable('2024-07-15');

        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: $dueDate,
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $payment = new Payment(
            amount: Money::fromEuros('120.00'),
            paidAt: new \DateTimeImmutable('2024-07-16'),
            method: PaymentMethod::BANK_TRANSFER,
        );
        $invoice->addPayment($payment);

        // Totalement payée = 0 jours de retard
        $this->assertSame(0, $invoice->getDaysOverdue($today));
    }

    public function testGetDaysOverdueWithLargeDelay(): void
    {
        $today = new \DateTimeImmutable('2024-09-15'); // 62 jours après
        $dueDate = new \DateTimeImmutable('2024-07-15');

        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: $dueDate,
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        $this->assertSame(62, $invoice->getDaysOverdue($today));
    }

    public function testGetDaysOverdueUsesCurrentDateWhenNotProvided(): void
    {
        // Facture échue il y a 3 jours
        $threeDaysAgo = new \DateTimeImmutable('-3 days');

        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('-33 days'),
            dueDate: $threeDaysAgo,
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $line = new InvoiceLine('Item 1', 1.0, Money::fromEuros('100.00'), 20.0);
        $invoice->addLine($line);

        // Sans paramètre, utilise la date actuelle
        $daysOverdue = $invoice->getDaysOverdue();
        $this->assertGreaterThanOrEqual(3, $daysOverdue);
        $this->assertLessThanOrEqual(4, $daysOverdue); // Tolérance pour exécution de nuit
    }

    // ========== Factur-X EN16931 Fields ==========

    public function testEN16931FieldsAreNullByDefault(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $this->assertNull($invoice->getBuyerReference());
        $this->assertNull($invoice->getPurchaseOrderReference());
        $this->assertNull($invoice->getAccountingReference());
        $this->assertNull($invoice->getOperationCategory());
        $this->assertNull($invoice->getVatOnDebits());
    }

    public function testBuyerReferenceCanBeSet(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $invoice->setBuyerReference('REF-CLIENT-001');

        $this->assertSame('REF-CLIENT-001', $invoice->getBuyerReference());
    }

    public function testPurchaseOrderReferenceCanBeSet(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $invoice->setPurchaseOrderReference('PO-2024-001');

        $this->assertSame('PO-2024-001', $invoice->getPurchaseOrderReference());
    }

    public function testAccountingReferenceCanBeSet(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $invoice->setAccountingReference('ACC-2024-001');

        $this->assertSame('ACC-2024-001', $invoice->getAccountingReference());
    }

    public function testOperationCategoryCanBeSet(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $invoice->setOperationCategory(OperationCategory::SERVICES);

        $this->assertSame(OperationCategory::SERVICES, $invoice->getOperationCategory());
    }

    public function testVatOnDebitsCanBeSet(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $invoice->setVatOnDebits(true);

        $this->assertTrue($invoice->getVatOnDebits());
    }

    public function testVatOnDebitsCanBeFalse(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $invoice->setVatOnDebits(false);

        $this->assertFalse($invoice->getVatOnDebits());
    }

    // ========== Structured Customer Address (BG-8) ==========

    public function testStructuredCustomerAddressFieldsAreNullByDefault(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $this->assertNull($invoice->getCustomerCity());
        $this->assertNull($invoice->getCustomerPostalCode());
        $this->assertNull($invoice->getCustomerCountryCode());
    }

    public function testStructuredCustomerAddressCanBeSet(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'ACME Corp',
            customerAddress: '123 Rue du Client',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $invoice->setCustomerCity('Paris');
        $invoice->setCustomerPostalCode('75001');
        $invoice->setCustomerCountryCode('FR');

        $this->assertSame('Paris', $invoice->getCustomerCity());
        $this->assertSame('75001', $invoice->getCustomerPostalCode());
        $this->assertSame('FR', $invoice->getCustomerCountryCode());
    }

    // ========== Delivery Address (BG-15) ==========

    public function testDeliveryAddressFieldsAreNullByDefault(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $this->assertNull($invoice->getDeliveryAddressLine1());
        $this->assertNull($invoice->getDeliveryCity());
        $this->assertNull($invoice->getDeliveryPostalCode());
        $this->assertNull($invoice->getDeliveryCountryCode());
    }

    public function testDeliveryAddressCanBeSet(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $invoice->setDeliveryAddressLine1('456 Rue de Livraison');
        $invoice->setDeliveryCity('Lyon');
        $invoice->setDeliveryPostalCode('69001');
        $invoice->setDeliveryCountryCode('FR');

        $this->assertSame('456 Rue de Livraison', $invoice->getDeliveryAddressLine1());
        $this->assertSame('Lyon', $invoice->getDeliveryCity());
        $this->assertSame('69001', $invoice->getDeliveryPostalCode());
        $this->assertSame('FR', $invoice->getDeliveryCountryCode());
    }

    public function testHasDeliveryAddressReturnsFalseWhenNotSet(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $this->assertFalse($invoice->hasDeliveryAddress());
    }

    public function testHasDeliveryAddressReturnsTrueWhenAddressLine1Set(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $invoice->setDeliveryAddressLine1('456 Rue de Livraison');

        $this->assertTrue($invoice->hasDeliveryAddress());
    }

    public function testHasDeliveryAddressReturnsTrueWhenCitySet(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $invoice->setDeliveryCity('Lyon');

        $this->assertTrue($invoice->hasDeliveryAddress());
    }

    public function testHasDeliveryAddressReturnsTrueWhenPostalCodeSet(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $invoice->setDeliveryPostalCode('69001');

        $this->assertTrue($invoice->hasDeliveryAddress());
    }

    public function testHasDeliveryAddressReturnsFalseWhenOnlyCountryCodeSet(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Customer',
            customerAddress: 'Address',
            companyName: 'Company',
            companyAddress: 'Address',
        );

        $invoice->setDeliveryCountryCode('FR');

        // Country code seul ne suffit pas pour une adresse de livraison
        $this->assertFalse($invoice->hasDeliveryAddress());
    }
}
