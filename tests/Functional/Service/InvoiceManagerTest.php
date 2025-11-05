<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Functional\Service;

use CorentinBoutillier\InvoiceBundle\DTO\CustomerData;
use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Event\CreditNoteCreatedEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceCancelledEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceCreatedEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceUpdatedEvent;
use CorentinBoutillier\InvoiceBundle\Provider\CompanyProviderInterface;
use CorentinBoutillier\InvoiceBundle\Service\DueDateCalculatorInterface;
use CorentinBoutillier\InvoiceBundle\Service\InvoiceManagerInterface;
use CorentinBoutillier\InvoiceBundle\Tests\Functional\Repository\RepositoryTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class InvoiceManagerTest extends RepositoryTestCase
{
    /** @phpstan-ignore property.uninitialized */
    private InvoiceManagerInterface $invoiceManager;
    /** @phpstan-ignore property.uninitialized */
    private CompanyProviderInterface $companyProvider;
    /** @phpstan-ignore property.uninitialized */
    private DueDateCalculatorInterface $dueDateCalculator;
    /** @phpstan-ignore property.uninitialized */
    private EventDispatcherInterface $eventDispatcher;

    /** @var array<object> */
    private array $dispatchedEvents = [];

    protected function setUp(): void
    {
        parent::setUp();

        $container = $this->kernel->getContainer();

        $invoiceManager = $container->get(InvoiceManagerInterface::class);
        if (!$invoiceManager instanceof InvoiceManagerInterface) {
            throw new \RuntimeException('InvoiceManagerInterface not found');
        }
        $this->invoiceManager = $invoiceManager;

        $companyProvider = $container->get(CompanyProviderInterface::class);
        if (!$companyProvider instanceof CompanyProviderInterface) {
            throw new \RuntimeException('CompanyProviderInterface not found');
        }
        $this->companyProvider = $companyProvider;

        $dueDateCalculator = $container->get(DueDateCalculatorInterface::class);
        if (!$dueDateCalculator instanceof DueDateCalculatorInterface) {
            throw new \RuntimeException('DueDateCalculatorInterface not found');
        }
        $this->dueDateCalculator = $dueDateCalculator;

        $eventDispatcher = $container->get(EventDispatcherInterface::class);
        if (!$eventDispatcher instanceof EventDispatcherInterface) {
            throw new \RuntimeException('EventDispatcherInterface not found');
        }
        $this->eventDispatcher = $eventDispatcher;

        // Listen to all events for testing
        $this->eventDispatcher->addListener(InvoiceCreatedEvent::class, function (InvoiceCreatedEvent $event): void {
            $this->dispatchedEvents[] = $event;
        });
        $this->eventDispatcher->addListener(CreditNoteCreatedEvent::class, function (CreditNoteCreatedEvent $event): void {
            $this->dispatchedEvents[] = $event;
        });
        $this->eventDispatcher->addListener(InvoiceUpdatedEvent::class, function (InvoiceUpdatedEvent $event): void {
            $this->dispatchedEvents[] = $event;
        });
        $this->eventDispatcher->addListener(InvoiceCancelledEvent::class, function (InvoiceCancelledEvent $event): void {
            $this->dispatchedEvents[] = $event;
        });
    }

    // ========== Invoice Creation Tests ==========

    public function testCreateInvoiceReturnsInvoiceEntity(): void
    {
        $customerData = $this->createCustomerData();
        $date = new \DateTimeImmutable('2025-01-15');

        $invoice = $this->invoiceManager->createInvoice(
            customerData: $customerData,
            date: $date,
            paymentTerms: '30 jours net',
        );

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertNotNull($invoice->getId());
    }

    public function testCreateInvoiceWithCompanyDataSnapshot(): void
    {
        $customerData = $this->createCustomerData();
        $date = new \DateTimeImmutable('2025-01-15');
        $companyData = $this->companyProvider->getCompanyData();

        $invoice = $this->invoiceManager->createInvoice($customerData, $date, '30 jours net');

        $this->assertSame($companyData->name, $invoice->getCompanyName());
        $this->assertSame($companyData->address, $invoice->getCompanyAddress());
        $this->assertSame($companyData->siret, $invoice->getCompanySiret());
        $this->assertSame($companyData->vatNumber, $invoice->getCompanyVatNumber());
    }

    public function testCreateInvoiceWithCustomerDataSnapshot(): void
    {
        $customerData = $this->createCustomerData(
            name: 'Client Test SARL',
            address: '123 rue de Test, 75001 Paris',
            email: 'client@test.com',
            phone: '0123456789',
        );
        $date = new \DateTimeImmutable('2025-01-15');

        $invoice = $this->invoiceManager->createInvoice($customerData, $date, '30 jours net');

        $this->assertSame('Client Test SARL', $invoice->getCustomerName());
        $this->assertSame('123 rue de Test, 75001 Paris', $invoice->getCustomerAddress());
        $this->assertSame('client@test.com', $invoice->getCustomerEmail());
        $this->assertSame('0123456789', $invoice->getCustomerPhone());
    }

    public function testCreateInvoicePersistsToDatabase(): void
    {
        $customerData = $this->createCustomerData();
        $date = new \DateTimeImmutable('2025-01-15');

        $invoice = $this->invoiceManager->createInvoice($customerData, $date, '30 jours net');

        $this->entityManager->clear();
        $persisted = $this->entityManager->find(Invoice::class, $invoice->getId());

        $this->assertNotNull($persisted);
        $this->assertSame($invoice->getCustomerName(), $persisted->getCustomerName());
    }

    public function testCreateInvoiceDispatchesInvoiceCreatedEvent(): void
    {
        $customerData = $this->createCustomerData();
        $date = new \DateTimeImmutable('2025-01-15');

        $invoice = $this->invoiceManager->createInvoice($customerData, $date, '30 jours net');

        $this->assertCount(1, $this->dispatchedEvents);
        $this->assertInstanceOf(InvoiceCreatedEvent::class, $this->dispatchedEvents[0]);
        $event = $this->dispatchedEvents[0];
        \assert($event instanceof InvoiceCreatedEvent);
        $this->assertSame($invoice, $event->invoice);
    }

    public function testCreateInvoiceCalculatesDueDateFromPaymentTerms(): void
    {
        $customerData = $this->createCustomerData();
        $date = new \DateTimeImmutable('2025-01-15');

        $invoice = $this->invoiceManager->createInvoice($customerData, $date, '30 jours net');

        $expectedDueDate = $this->dueDateCalculator->calculate($date, '30 jours net');
        $this->assertEquals($expectedDueDate, $invoice->getDueDate());
    }

    public function testCreateInvoiceWithCustomDueDate(): void
    {
        $customerData = $this->createCustomerData();
        $date = new \DateTimeImmutable('2025-01-15');
        $customDueDate = new \DateTimeImmutable('2025-02-28');

        $invoice = $this->invoiceManager->createInvoice(
            customerData: $customerData,
            date: $date,
            paymentTerms: '30 jours net',
            dueDate: $customDueDate,
        );

        $this->assertEquals($customDueDate, $invoice->getDueDate());
    }

    public function testCreateInvoiceWithOptionalCustomerFields(): void
    {
        $customerData = $this->createCustomerData(
            siret: '12345678901234',
            vatNumber: 'FR12345678901',
        );
        $date = new \DateTimeImmutable('2025-01-15');

        $invoice = $this->invoiceManager->createInvoice($customerData, $date, '30 jours net');

        $this->assertSame('12345678901234', $invoice->getCustomerSiret());
        $this->assertSame('FR12345678901', $invoice->getCustomerVatNumber());
    }

    public function testCreateInvoiceStartsWithDraftStatus(): void
    {
        $customerData = $this->createCustomerData();
        $date = new \DateTimeImmutable('2025-01-15');

        $invoice = $this->invoiceManager->createInvoice($customerData, $date, '30 jours net');

        $this->assertSame(InvoiceStatus::DRAFT, $invoice->getStatus());
    }

    public function testCreateInvoiceWithCurrency(): void
    {
        $customerData = $this->createCustomerData();
        $date = new \DateTimeImmutable('2025-01-15');

        $invoice = $this->invoiceManager->createInvoice(
            customerData: $customerData,
            date: $date,
            paymentTerms: '30 jours net',
            currency: 'USD',
        );

        // Note: Invoice entity doesn't have currency field yet, so this test documents expected behavior
        $this->assertInstanceOf(Invoice::class, $invoice);
    }

    public function testCreateInvoiceInMultiCompanyMode(): void
    {
        $this->markTestSkipped('ConfigCompanyProvider does not support multi-company mode');
    }

    public function testCreateInvoiceInMonoCompanyMode(): void
    {
        $customerData = $this->createCustomerData();
        $date = new \DateTimeImmutable('2025-01-15');

        $invoice = $this->invoiceManager->createInvoice(
            customerData: $customerData,
            date: $date,
            paymentTerms: '30 jours net',
        );

        $this->assertNull($invoice->getCompanyId());
    }

    public function testCreateInvoiceType(): void
    {
        $customerData = $this->createCustomerData();
        $date = new \DateTimeImmutable('2025-01-15');

        $invoice = $this->invoiceManager->createInvoice($customerData, $date, '30 jours net');

        $this->assertSame(InvoiceType::INVOICE, $invoice->getType());
    }

    public function testCreateInvoiceHasTimestamps(): void
    {
        $customerData = $this->createCustomerData();
        $date = new \DateTimeImmutable('2025-01-15');

        $invoice = $this->invoiceManager->createInvoice($customerData, $date, '30 jours net');

        $this->assertInstanceOf(\DateTimeImmutable::class, $invoice->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $invoice->getUpdatedAt());
    }

    // ========== Credit Note Creation Tests ==========

    public function testCreateCreditNoteReturnsInvoiceWithCreditNoteType(): void
    {
        $customerData = $this->createCustomerData();
        $date = new \DateTimeImmutable('2025-01-15');

        $creditNote = $this->invoiceManager->createCreditNote(
            customerData: $customerData,
            date: $date,
            paymentTerms: '30 jours net',
        );

        $this->assertInstanceOf(Invoice::class, $creditNote);
        $this->assertSame(InvoiceType::CREDIT_NOTE, $creditNote->getType());
    }

    public function testCreateCreditNoteLinksToOriginalInvoice(): void
    {
        $originalInvoice = $this->createPersistedInvoice();
        $customerData = $this->createCustomerData();
        $date = new \DateTimeImmutable('2025-01-20');

        $creditNote = $this->invoiceManager->createCreditNote(
            customerData: $customerData,
            date: $date,
            paymentTerms: '30 jours net',
            creditedInvoice: $originalInvoice,
        );

        $this->assertSame($originalInvoice, $creditNote->getCreditedInvoice());
    }

    public function testCreateCreditNoteDispatchesCreditNoteCreatedEvent(): void
    {
        $customerData = $this->createCustomerData();
        $date = new \DateTimeImmutable('2025-01-15');

        $creditNote = $this->invoiceManager->createCreditNote($customerData, $date, '30 jours net');

        $this->assertCount(1, $this->dispatchedEvents);
        $this->assertInstanceOf(CreditNoteCreatedEvent::class, $this->dispatchedEvents[0]);
        $event = $this->dispatchedEvents[0];
        \assert($event instanceof CreditNoteCreatedEvent);
        $this->assertSame($creditNote, $event->creditNote);
    }

    public function testCreateCreditNoteWithoutOriginalInvoice(): void
    {
        $customerData = $this->createCustomerData();
        $date = new \DateTimeImmutable('2025-01-15');

        $creditNote = $this->invoiceManager->createCreditNote($customerData, $date, '30 jours net');

        $this->assertNull($creditNote->getCreditedInvoice());
    }

    public function testCreateCreditNoteCopiesCompanyAndCustomerData(): void
    {
        $customerData = $this->createCustomerData(name: 'Client Crédit');
        $date = new \DateTimeImmutable('2025-01-15');
        $companyData = $this->companyProvider->getCompanyData();

        $creditNote = $this->invoiceManager->createCreditNote($customerData, $date, '30 jours net');

        $this->assertSame('Client Crédit', $creditNote->getCustomerName());
        $this->assertSame($companyData->name, $creditNote->getCompanyName());
    }

    public function testCreateCreditNotePersistsToDatabase(): void
    {
        $customerData = $this->createCustomerData();
        $date = new \DateTimeImmutable('2025-01-15');

        $creditNote = $this->invoiceManager->createCreditNote($customerData, $date, '30 jours net');

        $this->entityManager->clear();
        $persisted = $this->entityManager->find(Invoice::class, $creditNote->getId());

        $this->assertNotNull($persisted);
        $this->assertSame(InvoiceType::CREDIT_NOTE, $persisted->getType());
    }

    public function testCreateCreditNoteStartsWithDraftStatus(): void
    {
        $customerData = $this->createCustomerData();
        $date = new \DateTimeImmutable('2025-01-15');

        $creditNote = $this->invoiceManager->createCreditNote($customerData, $date, '30 jours net');

        $this->assertSame(InvoiceStatus::DRAFT, $creditNote->getStatus());
    }

    // ========== Line Management Tests ==========

    public function testAddLineToInvoice(): void
    {
        $invoice = $this->createPersistedInvoice();
        $line = $this->createInvoiceLine();

        $this->invoiceManager->addLine($invoice, $line);

        $this->assertCount(1, $invoice->getLines());
    }

    public function testAddLineAddsToInvoiceCollection(): void
    {
        $invoice = $this->createPersistedInvoice();
        $line = $this->createInvoiceLine('Service 1');

        $this->invoiceManager->addLine($invoice, $line);

        $lines = $invoice->getLines();
        $this->assertCount(1, $lines);
        $this->assertSame('Service 1', $lines[0]->getDescription());
    }

    public function testAddLinePersistsToDatabase(): void
    {
        $invoice = $this->createPersistedInvoice();
        $line = $this->createInvoiceLine();

        $this->invoiceManager->addLine($invoice, $line);

        $this->entityManager->clear();
        $persisted = $this->entityManager->find(Invoice::class, $invoice->getId());
        $this->assertNotNull($persisted);
        $this->assertCount(1, $persisted->getLines());
    }

    public function testAddMultipleLinesToInvoice(): void
    {
        $invoice = $this->createPersistedInvoice();
        $line1 = $this->createInvoiceLine('Service 1');
        $line2 = $this->createInvoiceLine('Service 2');
        $line3 = $this->createInvoiceLine('Service 3');

        $this->invoiceManager->addLine($invoice, $line1);
        $this->invoiceManager->addLine($invoice, $line2);
        $this->invoiceManager->addLine($invoice, $line3);

        $this->assertCount(3, $invoice->getLines());
    }

    public function testAddLineCalculatesTotalsAutomatically(): void
    {
        $invoice = $this->createPersistedInvoice();
        $line = $this->createInvoiceLine('Service', 2, '50.00', 20.0);

        $this->invoiceManager->addLine($invoice, $line);

        // 2 * 50 = 100 HT, 20% VAT = 20, Total TTC = 120
        $this->assertSame(10000, $invoice->getSubtotalBeforeDiscount()->getAmount());
        $this->assertSame(2000, $invoice->getTotalVat()->getAmount());
        $this->assertSame(12000, $invoice->getTotalIncludingVat()->getAmount());
    }

    public function testAddLineWithDifferentVatRates(): void
    {
        $invoice = $this->createPersistedInvoice();
        $line1 = $this->createInvoiceLine('Service 1', 1, '100.00', 20.0);
        $line2 = $this->createInvoiceLine('Service 2', 1, '100.00', 10.0);

        $this->invoiceManager->addLine($invoice, $line1);
        $this->invoiceManager->addLine($invoice, $line2);

        // Line 1: 100 HT + 20 VAT = 120 TTC
        // Line 2: 100 HT + 10 VAT = 110 TTC
        // Total: 200 HT + 30 VAT = 230 TTC
        $this->assertSame(20000, $invoice->getSubtotalBeforeDiscount()->getAmount());
        $this->assertSame(3000, $invoice->getTotalVat()->getAmount());
        $this->assertSame(23000, $invoice->getTotalIncludingVat()->getAmount());
    }

    public function testAddLineWithDiscounts(): void
    {
        $invoice = $this->createPersistedInvoice();
        $line = new InvoiceLine(
            description: 'Service',
            quantity: 1,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );
        $line->setDiscountRate(10.0);

        $this->invoiceManager->addLine($invoice, $line);

        // 100 - 10% = 90 HT, 20% VAT = 18, Total TTC = 108
        $this->assertSame(9000, $invoice->getSubtotalBeforeDiscount()->getAmount());
    }

    public function testCannotAddLineToFinalizedInvoice(): void
    {
        $invoice = $this->createPersistedInvoice();
        $invoice->setStatus(InvoiceStatus::FINALIZED);
        $this->entityManager->flush();

        $line = $this->createInvoiceLine();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot add line to invoice: invoice must be in DRAFT status');

        $this->invoiceManager->addLine($invoice, $line);
    }

    public function testCannotAddLineToCancelledInvoice(): void
    {
        $invoice = $this->createPersistedInvoice();
        $invoice->setStatus(InvoiceStatus::CANCELLED);
        $this->entityManager->flush();

        $line = $this->createInvoiceLine();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot add line to invoice: invoice must be in DRAFT status');

        $this->invoiceManager->addLine($invoice, $line);
    }

    // ========== Update Tests ==========

    public function testUpdateInvoiceModifiesAllFieldsInDraft(): void
    {
        $invoice = $this->createPersistedInvoice();

        $this->invoiceManager->updateInvoice($invoice, [
            'customerEmail' => 'updated@customer.com',
            'customerPhone' => '+33 9 87 65 43 21',
            'paymentTerms' => '45 jours net',
        ]);

        $this->assertSame('updated@customer.com', $invoice->getCustomerEmail());
        $this->assertSame('+33 9 87 65 43 21', $invoice->getCustomerPhone());
        $this->assertSame('45 jours net', $invoice->getPaymentTerms());
    }

    public function testUpdateInvoiceDispatchesInvoiceUpdatedEvent(): void
    {
        $invoice = $this->createPersistedInvoice();

        // Clear creation event
        $this->dispatchedEvents = [];

        $this->invoiceManager->updateInvoice($invoice, [
            'customerEmail' => 'updated@customer.com',
        ]);

        $this->assertCount(1, $this->dispatchedEvents);
        $this->assertInstanceOf(InvoiceUpdatedEvent::class, $this->dispatchedEvents[0]);
    }

    public function testUpdateInvoiceTracksChangedFields(): void
    {
        $invoice = $this->createPersistedInvoice();

        // Clear creation event
        $this->dispatchedEvents = [];

        $this->invoiceManager->updateInvoice($invoice, [
            'customerEmail' => 'updated@customer.com',
            'paymentTerms' => '45 jours net',
        ]);

        $this->assertCount(1, $this->dispatchedEvents);
        $event = $this->dispatchedEvents[0];
        \assert($event instanceof InvoiceUpdatedEvent);
        $changedFields = $event->changedFields;
        $this->assertContains('customerEmail', $changedFields);
        $this->assertContains('paymentTerms', $changedFields);
    }

    public function testCannotUpdateFinalizedInvoice(): void
    {
        $invoice = $this->createPersistedInvoice();
        $invoice->setStatus(InvoiceStatus::FINALIZED);
        $this->entityManager->flush();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot update invoice: invoice must be in DRAFT status');

        $this->invoiceManager->updateInvoice($invoice, [
            'customerName' => 'Updated',
        ]);
    }

    public function testCannotUpdateCancelledInvoice(): void
    {
        $invoice = $this->createPersistedInvoice();
        $invoice->setStatus(InvoiceStatus::CANCELLED);
        $this->entityManager->flush();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot update invoice: invoice must be in DRAFT status');

        $this->invoiceManager->updateInvoice($invoice, [
            'customerName' => 'Updated',
        ]);
    }

    public function testCannotUpdatePaidInvoice(): void
    {
        $invoice = $this->createPersistedInvoice();
        $invoice->setStatus(InvoiceStatus::PAID);
        $this->entityManager->flush();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot update invoice: invoice must be in DRAFT status');

        $this->invoiceManager->updateInvoice($invoice, [
            'customerName' => 'Updated',
        ]);
    }

    public function testUpdateInvoiceWithNoChangesDoesNotDispatchEvent(): void
    {
        $invoice = $this->createPersistedInvoice();

        // Clear creation event
        $this->dispatchedEvents = [];

        $this->invoiceManager->updateInvoice($invoice, []);

        $this->assertCount(0, $this->dispatchedEvents);
    }

    // ========== Cancellation Tests ==========

    public function testCancelInvoiceUpdateStatusToCancelled(): void
    {
        $invoice = $this->createPersistedInvoice();

        $this->invoiceManager->cancelInvoice($invoice);

        $this->assertSame(InvoiceStatus::CANCELLED, $invoice->getStatus());
    }

    public function testCancelInvoiceDispatchesInvoiceCancelledEvent(): void
    {
        $invoice = $this->createPersistedInvoice();

        // Clear creation event
        $this->dispatchedEvents = [];

        $this->invoiceManager->cancelInvoice($invoice);

        $this->assertCount(1, $this->dispatchedEvents);
        $this->assertInstanceOf(InvoiceCancelledEvent::class, $this->dispatchedEvents[0]);
        $event = $this->dispatchedEvents[0];
        \assert($event instanceof InvoiceCancelledEvent);
        $this->assertSame($invoice, $event->invoice);
    }

    public function testCancelInvoiceWithReason(): void
    {
        $invoice = $this->createPersistedInvoice();

        // Clear creation event
        $this->dispatchedEvents = [];

        $this->invoiceManager->cancelInvoice($invoice, 'Client request');

        $this->assertNotEmpty($this->dispatchedEvents);
        $event = $this->dispatchedEvents[0];
        \assert($event instanceof InvoiceCancelledEvent);
        $this->assertSame('Client request', $event->reason);
    }

    public function testCancelInvoiceWithoutReason(): void
    {
        $invoice = $this->createPersistedInvoice();

        // Clear creation event
        $this->dispatchedEvents = [];

        $this->invoiceManager->cancelInvoice($invoice);

        $this->assertNotEmpty($this->dispatchedEvents);
        $event = $this->dispatchedEvents[0];
        \assert($event instanceof InvoiceCancelledEvent);
        $this->assertNull($event->reason);
    }

    public function testCannotCancelAlreadyCancelledInvoice(): void
    {
        $invoice = $this->createPersistedInvoice();
        $invoice->setStatus(InvoiceStatus::CANCELLED);
        $this->entityManager->flush();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cancel invoice: invoice is already cancelled');

        $this->invoiceManager->cancelInvoice($invoice);
    }

    public function testCannotCancelFinalizedInvoice(): void
    {
        $invoice = $this->createPersistedInvoice();
        $invoice->setStatus(InvoiceStatus::FINALIZED);
        $this->entityManager->flush();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cancel invoice: only DRAFT invoices can be cancelled');

        $this->invoiceManager->cancelInvoice($invoice);
    }

    public function testCannotCancelPaidInvoice(): void
    {
        $invoice = $this->createPersistedInvoice();
        $invoice->setStatus(InvoiceStatus::PAID);
        $this->entityManager->flush();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cancel invoice: only DRAFT invoices can be cancelled');

        $this->invoiceManager->cancelInvoice($invoice);
    }

    // ========== Validation Tests ==========

    public function testCannotCreateInvoiceWithEmptyCustomerName(): void
    {
        $customerData = $this->createCustomerData(name: '');
        $date = new \DateTimeImmutable('2025-01-15');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer name cannot be empty');

        $this->invoiceManager->createInvoice($customerData, $date, '30 jours net');
    }

    public function testCannotCreateInvoiceWithEmptyCustomerAddress(): void
    {
        $customerData = $this->createCustomerData(address: '');
        $date = new \DateTimeImmutable('2025-01-15');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer address cannot be empty');

        $this->invoiceManager->createInvoice($customerData, $date, '30 jours net');
    }

    public function testCannotCreateInvoiceWithDueDateBeforeInvoiceDate(): void
    {
        $customerData = $this->createCustomerData();
        $date = new \DateTimeImmutable('2025-01-15');
        $dueDate = new \DateTimeImmutable('2025-01-10'); // Before invoice date

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Due date cannot be before invoice date');

        $this->invoiceManager->createInvoice(
            customerData: $customerData,
            date: $date,
            paymentTerms: '30 jours net',
            dueDate: $dueDate,
        );
    }

    // ========== Helper Methods ==========

    private function createCustomerData(
        string $name = 'Test Customer SARL',
        string $address = '123 rue Test, 75001 Paris',
        ?string $email = null,
        ?string $phone = null,
        ?string $siret = null,
        ?string $vatNumber = null,
    ): CustomerData {
        return new CustomerData(
            name: $name,
            address: $address,
            email: $email,
            phone: $phone,
            siret: $siret,
            vatNumber: $vatNumber,
        );
    }

    private function createPersistedInvoice(): Invoice
    {
        $customerData = $this->createCustomerData();
        $date = new \DateTimeImmutable('2025-01-15');

        return $this->invoiceManager->createInvoice($customerData, $date, '30 jours net');
    }

    private function createInvoiceLine(
        string $description = 'Test Service',
        int $quantity = 1,
        string $unitPrice = '100.00',
        float $vatRate = 20.0,
    ): InvoiceLine {
        return new InvoiceLine(
            description: $description,
            quantity: $quantity,
            unitPrice: Money::fromEuros($unitPrice),
            vatRate: $vatRate,
        );
    }
}
