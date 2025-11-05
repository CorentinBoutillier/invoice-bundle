<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Functional\Service;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;
use CorentinBoutillier\InvoiceBundle\Entity\Payment;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Enum\PaymentMethod;
use CorentinBoutillier\InvoiceBundle\Event\InvoicePaidEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoicePartiallyPaidEvent;
use CorentinBoutillier\InvoiceBundle\Repository\InvoiceRepository;
use CorentinBoutillier\InvoiceBundle\Service\PaymentManagerInterface;
use CorentinBoutillier\InvoiceBundle\Tests\Functional\Repository\RepositoryTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class PaymentManagerTest extends RepositoryTestCase
{
    /** @phpstan-ignore property.uninitialized */
    private PaymentManagerInterface $paymentManager;

    /** @phpstan-ignore property.uninitialized */
    private InvoiceRepository $invoiceRepository;

    /** @phpstan-ignore property.uninitialized */
    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = $this->entityManager->getRepository(Invoice::class);
        if (!$repository instanceof InvoiceRepository) {
            throw new \RuntimeException('InvoiceRepository not found');
        }
        $this->invoiceRepository = $repository;

        $container = $this->kernel->getContainer();

        $paymentManager = $container->get(PaymentManagerInterface::class);
        if (!$paymentManager instanceof PaymentManagerInterface) {
            throw new \RuntimeException('PaymentManagerInterface not found');
        }
        $this->paymentManager = $paymentManager;

        $eventDispatcher = $container->get('event_dispatcher');
        if (!$eventDispatcher instanceof EventDispatcherInterface) {
            throw new \RuntimeException('EventDispatcherInterface not found');
        }
        $this->eventDispatcher = $eventDispatcher;
    }

    // ========== Payment Recording Tests ==========

    public function testRecordPaymentCreatesPaymentEntity(): void
    {
        $invoice = $this->createInvoiceWithAmount(120.00);

        $result = $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('120.00'),
            paidAt: new \DateTimeImmutable('2025-01-20'),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $this->assertInstanceOf(Payment::class, $result);
        $this->assertTrue($result->getAmount()->equals(Money::fromEuros('120.00')));
        $this->assertEquals(new \DateTimeImmutable('2025-01-20'), $result->getPaidAt());
        $this->assertSame(PaymentMethod::BANK_TRANSFER, $result->getMethod());
    }

    public function testRecordPaymentAddsPaymentToInvoice(): void
    {
        $invoice = $this->createInvoiceWithAmount(120.00);

        $payment = $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('60.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::CREDIT_CARD,
        );

        $payments = $invoice->getPayments();
        $this->assertCount(1, $payments);
        $this->assertSame($payment, $payments[0]);
    }

    public function testRecordPaymentPersistsToDatabase(): void
    {
        $invoice = $this->createInvoiceWithAmount(120.00);
        $invoiceId = $invoice->getId();

        $payment = $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('120.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $this->assertNotNull($payment->getId());

        // Reload invoice from database
        $this->entityManager->clear();
        $reloadedInvoice = $this->invoiceRepository->find($invoiceId);

        $this->assertNotNull($reloadedInvoice);
        $this->assertCount(1, $reloadedInvoice->getPayments());
    }

    // ========== Status Update Tests - Full Payment ==========

    public function testRecordFullPaymentUpdatesStatusToPaid(): void
    {
        $invoice = $this->createInvoiceWithAmount(120.00);
        $this->assertSame(InvoiceStatus::FINALIZED, $invoice->getStatus());

        $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('120.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $this->assertSame(InvoiceStatus::PAID, $invoice->getStatus());
    }

    public function testRecordFullPaymentDispatchesInvoicePaidEvent(): void
    {
        $invoice = $this->createInvoiceWithAmount(120.00);
        $paidAt = new \DateTimeImmutable('2025-01-20');

        $eventDispatched = false;
        $capturedEvent = null;

        $this->eventDispatcher->addListener(InvoicePaidEvent::class, function ($event) use (&$eventDispatched, &$capturedEvent) {
            $eventDispatched = true;
            $capturedEvent = $event;
        });

        $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('120.00'),
            paidAt: $paidAt,
            method: PaymentMethod::BANK_TRANSFER,
        );

        $this->assertTrue($eventDispatched, 'InvoicePaidEvent should be dispatched');
        $this->assertInstanceOf(InvoicePaidEvent::class, $capturedEvent);
        $this->assertSame($invoice, $capturedEvent->invoice);
        $this->assertEquals($paidAt, $capturedEvent->paidAt);
    }

    public function testFullPaymentFromPartiallyPaidStatus(): void
    {
        $invoice = $this->createInvoiceWithAmount(120.00);

        // First partial payment
        $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('50.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $this->assertSame(InvoiceStatus::PARTIALLY_PAID, $invoice->getStatus());

        // Second payment completes the invoice
        $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('70.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::CREDIT_CARD,
        );

        $this->assertSame(InvoiceStatus::PAID, $invoice->getStatus());
    }

    // ========== Status Update Tests - Partial Payment ==========

    public function testRecordPartialPaymentUpdatesStatusToPartiallyPaid(): void
    {
        $invoice = $this->createInvoiceWithAmount(120.00);
        $this->assertSame(InvoiceStatus::FINALIZED, $invoice->getStatus());

        $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('60.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $this->assertSame(InvoiceStatus::PARTIALLY_PAID, $invoice->getStatus());
    }

    public function testRecordPartialPaymentDispatchesPartiallyPaidEvent(): void
    {
        $invoice = $this->createInvoiceWithAmount(120.00);
        $paymentAmount = Money::fromEuros('60.00');

        $eventDispatched = false;
        $capturedEvent = null;

        $this->eventDispatcher->addListener(InvoicePartiallyPaidEvent::class, function ($event) use (&$eventDispatched, &$capturedEvent) {
            $eventDispatched = true;
            $capturedEvent = $event;
        });

        $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: $paymentAmount,
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $this->assertTrue($eventDispatched, 'InvoicePartiallyPaidEvent should be dispatched');
        $this->assertInstanceOf(InvoicePartiallyPaidEvent::class, $capturedEvent);
        $this->assertSame($invoice, $capturedEvent->invoice);
        $this->assertTrue($capturedEvent->amountPaid->equals($paymentAmount));
        $this->assertTrue($capturedEvent->remainingAmount->equals(Money::fromEuros('60.00')));
    }

    public function testMultiplePartialPaymentsAccumulate(): void
    {
        $invoice = $this->createInvoiceWithAmount(120.00);

        $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('30.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $this->assertSame(InvoiceStatus::PARTIALLY_PAID, $invoice->getStatus());
        $this->assertTrue($invoice->getTotalPaid()->equals(Money::fromEuros('30.00')));
        $this->assertTrue($invoice->getRemainingAmount()->equals(Money::fromEuros('90.00')));

        $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('40.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::CREDIT_CARD,
        );

        $this->assertSame(InvoiceStatus::PARTIALLY_PAID, $invoice->getStatus());
        $this->assertTrue($invoice->getTotalPaid()->equals(Money::fromEuros('70.00')));
        $this->assertTrue($invoice->getRemainingAmount()->equals(Money::fromEuros('50.00')));
    }

    // ========== Amount Calculations ==========

    public function testRecordPaymentUpdatesTotalPaid(): void
    {
        $invoice = $this->createInvoiceWithAmount(120.00);
        $this->assertTrue($invoice->getTotalPaid()->isZero());

        $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('60.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $this->assertTrue($invoice->getTotalPaid()->equals(Money::fromEuros('60.00')));
    }

    public function testRecordPaymentUpdatesRemainingAmount(): void
    {
        $invoice = $this->createInvoiceWithAmount(120.00);
        $this->assertTrue($invoice->getRemainingAmount()->equals(Money::fromEuros('120.00')));

        $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('60.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $this->assertTrue($invoice->getRemainingAmount()->equals(Money::fromEuros('60.00')));
    }

    public function testZeroRemainingAfterFullPayment(): void
    {
        $invoice = $this->createInvoiceWithAmount(120.00);

        $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('120.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $this->assertTrue($invoice->getRemainingAmount()->isZero());
        $this->assertTrue($invoice->isFullyPaid());
    }

    // ========== Optional Fields ==========

    public function testRecordPaymentWithReferenceAndNotes(): void
    {
        $invoice = $this->createInvoiceWithAmount(120.00);

        $payment = $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('120.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
            reference: 'TRANSFER-123456',
            notes: 'Payment via bank transfer on January 20th',
        );

        $this->assertSame('TRANSFER-123456', $payment->getReference());
        $this->assertSame('Payment via bank transfer on January 20th', $payment->getNotes());
    }

    public function testRecordPaymentWithoutOptionalFields(): void
    {
        $invoice = $this->createInvoiceWithAmount(120.00);

        $payment = $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('120.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $this->assertNull($payment->getReference());
        $this->assertNull($payment->getNotes());
    }

    // ========== Validation Tests ==========

    public function testCannotRecordPaymentOnDraftInvoice(): void
    {
        $invoice = $this->createInvoiceWithAmount(120.00);
        $invoice->setStatus(InvoiceStatus::DRAFT);
        $this->entityManager->flush();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot record payment on invoice with status DRAFT');

        $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('120.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );
    }

    public function testCannotRecordPaymentOnCancelledInvoice(): void
    {
        $invoice = $this->createInvoiceWithAmount(120.00);
        $invoice->setStatus(InvoiceStatus::CANCELLED);
        $this->entityManager->flush();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot record payment on invoice with status CANCELLED');

        $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('120.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );
    }

    // ========== Edge Cases ==========

    public function testRecordOverpayment(): void
    {
        $invoice = $this->createInvoiceWithAmount(120.00);

        // Pay more than the invoice amount
        $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('150.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $this->assertSame(InvoiceStatus::PAID, $invoice->getStatus());
        $this->assertTrue($invoice->isFullyPaid());
        $this->assertTrue($invoice->getRemainingAmount()->isNegative());
    }

    public function testRecordZeroPayment(): void
    {
        $invoice = $this->createInvoiceWithAmount(120.00);
        $initialStatus = $invoice->getStatus();

        $payment = $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::zero(),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::OTHER,
        );

        // Payment is recorded but status doesn't change
        $this->assertNotNull($payment->getId());
        $this->assertSame($initialStatus, $invoice->getStatus());
        $this->assertTrue($invoice->getTotalPaid()->isZero());
    }

    // ========== Helper Methods ==========

    /**
     * Creates an invoice with a specified TOTAL amount including VAT.
     *
     * @param float $totalIncludingVat Total amount including VAT (e.g., 120.00)
     * @param float $vatRate VAT rate in percentage (e.g., 20.0 for 20%)
     */
    private function createInvoiceWithAmount(float $totalIncludingVat, float $vatRate = 20.0): Invoice
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2025-01-15'),
            dueDate: new \DateTimeImmutable('2025-02-14'),
            customerName: 'Test Customer',
            customerAddress: '123 Test Street',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $invoice->setStatus(InvoiceStatus::FINALIZED);
        $invoice->setNumber('FA-2025-0001');
        $invoice->setCompanyId(1);

        // Calculate amount excluding VAT to achieve the desired total
        // Formula: amountExcludingVat = totalIncludingVat / (1 + vatRate/100)
        $amountExcludingVat = $totalIncludingVat / (1 + $vatRate / 100);

        // Add a line to create the specified total amount
        $line = new InvoiceLine(
            description: 'Test Service',
            quantity: 1,
            unitPrice: Money::fromEuros((string) $amountExcludingVat),
            vatRate: $vatRate,
        );

        $invoice->addLine($line);

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $invoice;
    }
}
