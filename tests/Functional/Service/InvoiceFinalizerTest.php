<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Functional\Service;

use CorentinBoutillier\InvoiceBundle\DTO\CustomerData;
use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceFinalizedEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoicePdfGeneratedEvent;
use CorentinBoutillier\InvoiceBundle\Exception\InvoiceFinalizationException;
use CorentinBoutillier\InvoiceBundle\Service\InvoiceFinalizerInterface;
use CorentinBoutillier\InvoiceBundle\Service\Pdf\PdfGeneratorInterface;
use CorentinBoutillier\InvoiceBundle\Service\Pdf\Storage\PdfStorageInterface;
use CorentinBoutillier\InvoiceBundle\Tests\Functional\Repository\RepositoryTestCase;
use Smalot\PdfParser\Parser as PdfParser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class InvoiceFinalizerTest extends RepositoryTestCase
{
    /** @phpstan-ignore property.uninitialized */
    private InvoiceFinalizerInterface $invoiceFinalizer;
    /** @phpstan-ignore property.uninitialized */
    private PdfStorageInterface $pdfStorage;
    /** @phpstan-ignore property.uninitialized */
    private EventDispatcherInterface $eventDispatcher;
    /** @var array<object> */
    private array $dispatchedEvents = [];

    protected function setUp(): void
    {
        parent::setUp();

        $container = $this->kernel->getContainer();

        $invoiceFinalizer = $container->get(InvoiceFinalizerInterface::class);
        if (!$invoiceFinalizer instanceof InvoiceFinalizerInterface) {
            throw new \RuntimeException('InvoiceFinalizerInterface not found');
        }
        $this->invoiceFinalizer = $invoiceFinalizer;

        $pdfStorage = $container->get(PdfStorageInterface::class);
        if (!$pdfStorage instanceof PdfStorageInterface) {
            throw new \RuntimeException('PdfStorageInterface not found');
        }
        $this->pdfStorage = $pdfStorage;

        $eventDispatcher = $container->get(EventDispatcherInterface::class);
        if (!$eventDispatcher instanceof EventDispatcherInterface) {
            throw new \RuntimeException('EventDispatcherInterface not found');
        }
        $this->eventDispatcher = $eventDispatcher;

        // Capture dispatched events
        $this->dispatchedEvents = [];
        $this->eventDispatcher->addListener(InvoiceFinalizedEvent::class, function (InvoiceFinalizedEvent $event): void {
            $this->dispatchedEvents[] = $event;
        });
        $this->eventDispatcher->addListener(InvoicePdfGeneratedEvent::class, function (InvoicePdfGeneratedEvent $event): void {
            $this->dispatchedEvents[] = $event;
        });
    }

    // ========================================
    // Tests de succès (7 tests)
    // ========================================

    public function testFinalizeInvoiceSuccessfully(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        $this->invoiceFinalizer->finalize($invoice);

        $this->assertSame(InvoiceStatus::FINALIZED, $invoice->getStatus());
        $this->assertNotNull($invoice->getNumber());
        $this->assertNotNull($invoice->getPdfPath());
        $this->assertNotNull($invoice->getPdfGeneratedAt());
    }

    public function testFinalizeAssignsInvoiceNumber(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        $this->invoiceFinalizer->finalize($invoice);

        $number = $invoice->getNumber();
        $this->assertNotNull($number);
        $this->assertMatchesRegularExpression('/^FA-\d{4}-\d{4}$/', $number);
    }

    public function testFinalizeChangesStatusToFinalized(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();
        $this->assertSame(InvoiceStatus::DRAFT, $invoice->getStatus());

        $this->invoiceFinalizer->finalize($invoice);

        $this->assertSame(InvoiceStatus::FINALIZED, $invoice->getStatus());
    }

    public function testFinalizeGeneratesPdf(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        $this->invoiceFinalizer->finalize($invoice);

        // Vérifier que le PDF a été stocké
        $pdfPath = $invoice->getPdfPath();
        $this->assertNotNull($pdfPath);

        // Vérifier que le PDF existe dans le storage
        $this->assertTrue($this->pdfStorage->exists($pdfPath));

        // Vérifier que le contenu du PDF n'est pas vide
        $pdfContent = $this->pdfStorage->retrieve($pdfPath);
        $this->assertNotEmpty($pdfContent);
    }

    public function testFinalizeStoresPdfToStorage(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        $this->invoiceFinalizer->finalize($invoice);

        $pdfPath = $invoice->getPdfPath();
        $this->assertNotNull($pdfPath);

        // Vérifier le format du path (YYYY/MM/FA-YYYY-NNNN.pdf)
        $this->assertMatchesRegularExpression('/^\d{4}\/\d{2}\/FA-\d{4}-\d{4}\.pdf$/', $pdfPath);
    }

    public function testFinalizeRecordsPdfPathOnInvoice(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();
        $this->assertNull($invoice->getPdfPath());

        $this->invoiceFinalizer->finalize($invoice);

        $this->assertNotNull($invoice->getPdfPath());
    }

    public function testFinalizeRecordsPdfGeneratedAtTimestamp(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();
        $beforeFinalization = new \DateTimeImmutable();

        $this->invoiceFinalizer->finalize($invoice);

        $pdfGeneratedAt = $invoice->getPdfGeneratedAt();
        $this->assertNotNull($pdfGeneratedAt);
        $this->assertGreaterThanOrEqual($beforeFinalization->getTimestamp(), $pdfGeneratedAt->getTimestamp());
    }

    // ========================================
    // Tests événements (3 tests)
    // ========================================

    public function testFinalizeDispatchesInvoiceFinalizedEvent(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        $this->invoiceFinalizer->finalize($invoice);

        $finalizedEvents = array_filter($this->dispatchedEvents, fn ($event) => $event instanceof InvoiceFinalizedEvent);
        $this->assertCount(1, $finalizedEvents);
    }

    public function testFinalizeDispatchesInvoicePdfGeneratedEvent(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        $this->invoiceFinalizer->finalize($invoice);

        $pdfGeneratedEvents = array_filter($this->dispatchedEvents, fn ($event) => $event instanceof InvoicePdfGeneratedEvent);
        $this->assertCount(1, $pdfGeneratedEvents);
    }

    public function testFinalizedEventContainsInvoiceAndNumber(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        $this->invoiceFinalizer->finalize($invoice);

        $finalizedEvents = array_filter($this->dispatchedEvents, fn ($event) => $event instanceof InvoiceFinalizedEvent);
        $event = reset($finalizedEvents);

        $this->assertInstanceOf(InvoiceFinalizedEvent::class, $event);
        $this->assertSame($invoice, $event->invoice);
        $this->assertSame($invoice->getNumber(), $event->number);
    }

    // ========================================
    // Tests validations (4 tests)
    // ========================================

    public function testCannotFinalizeInvoiceWithoutLines(): void
    {
        $invoice = $this->createDraftInvoice();
        // Pas de lignes ajoutées

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot finalize invoice: invoice must have at least one line');

        $this->invoiceFinalizer->finalize($invoice);
    }

    public function testCannotFinalizeAlreadyFinalizedInvoice(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();
        $this->invoiceFinalizer->finalize($invoice);
        $this->entityManager->clear(); // Clear pour réinitialiser l'état

        // Recharger l'invoice depuis la BDD
        $reloadedInvoice = $this->entityManager->find(Invoice::class, $invoice->getId());
        if (!$reloadedInvoice instanceof Invoice) {
            throw new \RuntimeException('Invoice not found');
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot finalize invoice: invoice is already finalized');

        $this->invoiceFinalizer->finalize($reloadedInvoice);
    }

    public function testCannotFinalizeCancelledInvoice(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();
        $invoice->setStatus(InvoiceStatus::CANCELLED);
        $this->entityManager->flush();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot finalize invoice: only DRAFT invoices can be finalized');

        $this->invoiceFinalizer->finalize($invoice);
    }

    public function testCannotFinalizePaidInvoice(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();
        $invoice->setStatus(InvoiceStatus::PAID);
        $this->entityManager->flush();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot finalize invoice: only DRAFT invoices can be finalized');

        $this->invoiceFinalizer->finalize($invoice);
    }

    // ========================================
    // Tests transactionnels/rollback (6 tests)
    // ========================================

    public function testRollbackOnPdfGenerationFailure(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        // Mock PdfGenerator pour simuler une erreur
        $mockPdfGenerator = $this->createMock(PdfGeneratorInterface::class);
        $mockPdfGenerator->method('generate')->willThrowException(new \RuntimeException('PDF generation failed'));

        // Get services from container
        $numberGenerator = $this->kernel->getContainer()->get(\CorentinBoutillier\InvoiceBundle\Service\NumberGenerator\InvoiceNumberGeneratorInterface::class);
        if (!$numberGenerator instanceof \CorentinBoutillier\InvoiceBundle\Service\NumberGenerator\InvoiceNumberGeneratorInterface) {
            throw new \RuntimeException('InvoiceNumberGeneratorInterface not found');
        }

        $companyProvider = $this->kernel->getContainer()->get(\CorentinBoutillier\InvoiceBundle\Provider\CompanyProviderInterface::class);
        if (!$companyProvider instanceof \CorentinBoutillier\InvoiceBundle\Provider\CompanyProviderInterface) {
            throw new \RuntimeException('CompanyProviderInterface not found');
        }

        // Créer un InvoiceFinalizer avec le mock
        $finalizer = new \CorentinBoutillier\InvoiceBundle\Service\InvoiceFinalizer(
            $this->entityManager,
            $numberGenerator,
            $mockPdfGenerator,
            $this->pdfStorage,
            $this->eventDispatcher,
            $companyProvider,
        );

        $this->expectException(InvoiceFinalizationException::class);
        $this->expectExceptionMessage('Failed to finalize invoice: PDF generation failed');

        $finalizer->finalize($invoice);
    }

    public function testRollbackOnPdfStorageFailure(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        // Mock PdfStorage pour simuler une erreur
        $mockPdfStorage = $this->createMock(PdfStorageInterface::class);
        $mockPdfStorage->method('store')->willThrowException(new \RuntimeException('Storage failed'));

        // Get services from container
        $numberGenerator = $this->kernel->getContainer()->get(\CorentinBoutillier\InvoiceBundle\Service\NumberGenerator\InvoiceNumberGeneratorInterface::class);
        if (!$numberGenerator instanceof \CorentinBoutillier\InvoiceBundle\Service\NumberGenerator\InvoiceNumberGeneratorInterface) {
            throw new \RuntimeException('InvoiceNumberGeneratorInterface not found');
        }

        $pdfGenerator = $this->kernel->getContainer()->get(PdfGeneratorInterface::class);
        if (!$pdfGenerator instanceof PdfGeneratorInterface) {
            throw new \RuntimeException('PdfGeneratorInterface not found');
        }

        $companyProvider = $this->kernel->getContainer()->get(\CorentinBoutillier\InvoiceBundle\Provider\CompanyProviderInterface::class);
        if (!$companyProvider instanceof \CorentinBoutillier\InvoiceBundle\Provider\CompanyProviderInterface) {
            throw new \RuntimeException('CompanyProviderInterface not found');
        }

        // Créer un InvoiceFinalizer avec le mock
        $finalizer = new \CorentinBoutillier\InvoiceBundle\Service\InvoiceFinalizer(
            $this->entityManager,
            $numberGenerator,
            $pdfGenerator,
            $mockPdfStorage,
            $this->eventDispatcher,
            $companyProvider,
        );

        $this->expectException(InvoiceFinalizationException::class);
        $this->expectExceptionMessage('Failed to finalize invoice: Storage failed');

        $finalizer->finalize($invoice);
    }

    public function testInvoiceSequenceNotConsumedOnRollback(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        // Mock PdfStorage pour simuler une erreur
        $mockPdfStorage = $this->createMock(PdfStorageInterface::class);
        $mockPdfStorage->method('store')->willThrowException(new \RuntimeException('Storage failed'));

        // Get services from container
        $numberGenerator = $this->kernel->getContainer()->get(\CorentinBoutillier\InvoiceBundle\Service\NumberGenerator\InvoiceNumberGeneratorInterface::class);
        if (!$numberGenerator instanceof \CorentinBoutillier\InvoiceBundle\Service\NumberGenerator\InvoiceNumberGeneratorInterface) {
            throw new \RuntimeException('InvoiceNumberGeneratorInterface not found');
        }

        $pdfGenerator = $this->kernel->getContainer()->get(PdfGeneratorInterface::class);
        if (!$pdfGenerator instanceof PdfGeneratorInterface) {
            throw new \RuntimeException('PdfGeneratorInterface not found');
        }

        $companyProvider = $this->kernel->getContainer()->get(\CorentinBoutillier\InvoiceBundle\Provider\CompanyProviderInterface::class);
        if (!$companyProvider instanceof \CorentinBoutillier\InvoiceBundle\Provider\CompanyProviderInterface) {
            throw new \RuntimeException('CompanyProviderInterface not found');
        }

        // Créer un InvoiceFinalizer avec le mock
        $finalizer = new \CorentinBoutillier\InvoiceBundle\Service\InvoiceFinalizer(
            $this->entityManager,
            $numberGenerator,
            $pdfGenerator,
            $mockPdfStorage,
            $this->eventDispatcher,
            $companyProvider,
        );

        try {
            $finalizer->finalize($invoice);
            $this->fail('Expected InvoiceFinalizationException to be thrown');
        } catch (InvoiceFinalizationException) {
            // Expected
        }

        // Clear EntityManager after rollback (required by Doctrine after exception)
        $this->entityManager->clear();

        // Vérifier qu'une nouvelle facture obtient le même numéro (séquence pas incrémentée)
        $invoice2 = $this->createDraftInvoiceWithLines();
        $this->invoiceFinalizer->finalize($invoice2);

        // Les deux factures devraient avoir le même numéro (car la première a rollback)
        $this->assertSame('FA-2025-0001', $invoice2->getNumber());
    }

    public function testInvoiceStatusRemainsAsDraftOnFailure(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        // Mock PdfStorage pour simuler une erreur
        $mockPdfStorage = $this->createMock(PdfStorageInterface::class);
        $mockPdfStorage->method('store')->willThrowException(new \RuntimeException('Storage failed'));

        // Get services from container
        $numberGenerator = $this->kernel->getContainer()->get(\CorentinBoutillier\InvoiceBundle\Service\NumberGenerator\InvoiceNumberGeneratorInterface::class);
        if (!$numberGenerator instanceof \CorentinBoutillier\InvoiceBundle\Service\NumberGenerator\InvoiceNumberGeneratorInterface) {
            throw new \RuntimeException('InvoiceNumberGeneratorInterface not found');
        }

        $pdfGenerator = $this->kernel->getContainer()->get(PdfGeneratorInterface::class);
        if (!$pdfGenerator instanceof PdfGeneratorInterface) {
            throw new \RuntimeException('PdfGeneratorInterface not found');
        }

        $companyProvider = $this->kernel->getContainer()->get(\CorentinBoutillier\InvoiceBundle\Provider\CompanyProviderInterface::class);
        if (!$companyProvider instanceof \CorentinBoutillier\InvoiceBundle\Provider\CompanyProviderInterface) {
            throw new \RuntimeException('CompanyProviderInterface not found');
        }

        // Créer un InvoiceFinalizer avec le mock
        $finalizer = new \CorentinBoutillier\InvoiceBundle\Service\InvoiceFinalizer(
            $this->entityManager,
            $numberGenerator,
            $pdfGenerator,
            $mockPdfStorage,
            $this->eventDispatcher,
            $companyProvider,
        );

        try {
            $finalizer->finalize($invoice);
            $this->fail('Expected InvoiceFinalizationException to be thrown');
        } catch (InvoiceFinalizationException) {
            // Expected
        }

        // Recharger depuis la BDD pour vérifier le statut
        $this->entityManager->clear();
        $reloadedInvoice = $this->entityManager->find(Invoice::class, $invoice->getId());
        if (!$reloadedInvoice instanceof Invoice) {
            throw new \RuntimeException('Invoice not found');
        }

        $this->assertSame(InvoiceStatus::DRAFT, $reloadedInvoice->getStatus());
    }

    public function testNoPdfPathRecordedOnFailure(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        // Mock PdfStorage pour simuler une erreur
        $mockPdfStorage = $this->createMock(PdfStorageInterface::class);
        $mockPdfStorage->method('store')->willThrowException(new \RuntimeException('Storage failed'));

        // Get services from container
        $numberGenerator = $this->kernel->getContainer()->get(\CorentinBoutillier\InvoiceBundle\Service\NumberGenerator\InvoiceNumberGeneratorInterface::class);
        if (!$numberGenerator instanceof \CorentinBoutillier\InvoiceBundle\Service\NumberGenerator\InvoiceNumberGeneratorInterface) {
            throw new \RuntimeException('InvoiceNumberGeneratorInterface not found');
        }

        $pdfGenerator = $this->kernel->getContainer()->get(PdfGeneratorInterface::class);
        if (!$pdfGenerator instanceof PdfGeneratorInterface) {
            throw new \RuntimeException('PdfGeneratorInterface not found');
        }

        $companyProvider = $this->kernel->getContainer()->get(\CorentinBoutillier\InvoiceBundle\Provider\CompanyProviderInterface::class);
        if (!$companyProvider instanceof \CorentinBoutillier\InvoiceBundle\Provider\CompanyProviderInterface) {
            throw new \RuntimeException('CompanyProviderInterface not found');
        }

        // Créer un InvoiceFinalizer avec le mock
        $finalizer = new \CorentinBoutillier\InvoiceBundle\Service\InvoiceFinalizer(
            $this->entityManager,
            $numberGenerator,
            $pdfGenerator,
            $mockPdfStorage,
            $this->eventDispatcher,
            $companyProvider,
        );

        try {
            $finalizer->finalize($invoice);
            $this->fail('Expected InvoiceFinalizationException to be thrown');
        } catch (InvoiceFinalizationException) {
            // Expected
        }

        // Recharger depuis la BDD
        $this->entityManager->clear();
        $reloadedInvoice = $this->entityManager->find(Invoice::class, $invoice->getId());
        if (!$reloadedInvoice instanceof Invoice) {
            throw new \RuntimeException('Invoice not found');
        }

        $this->assertNull($reloadedInvoice->getPdfPath());
    }

    public function testNoEventsDispatchedOnFailure(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        // Mock PdfStorage pour simuler une erreur
        $mockPdfStorage = $this->createMock(PdfStorageInterface::class);
        $mockPdfStorage->method('store')->willThrowException(new \RuntimeException('Storage failed'));

        // Get services from container
        $numberGenerator = $this->kernel->getContainer()->get(\CorentinBoutillier\InvoiceBundle\Service\NumberGenerator\InvoiceNumberGeneratorInterface::class);
        if (!$numberGenerator instanceof \CorentinBoutillier\InvoiceBundle\Service\NumberGenerator\InvoiceNumberGeneratorInterface) {
            throw new \RuntimeException('InvoiceNumberGeneratorInterface not found');
        }

        $pdfGenerator = $this->kernel->getContainer()->get(PdfGeneratorInterface::class);
        if (!$pdfGenerator instanceof PdfGeneratorInterface) {
            throw new \RuntimeException('PdfGeneratorInterface not found');
        }

        $companyProvider = $this->kernel->getContainer()->get(\CorentinBoutillier\InvoiceBundle\Provider\CompanyProviderInterface::class);
        if (!$companyProvider instanceof \CorentinBoutillier\InvoiceBundle\Provider\CompanyProviderInterface) {
            throw new \RuntimeException('CompanyProviderInterface not found');
        }

        // Créer un InvoiceFinalizer avec le mock
        $finalizer = new \CorentinBoutillier\InvoiceBundle\Service\InvoiceFinalizer(
            $this->entityManager,
            $numberGenerator,
            $pdfGenerator,
            $mockPdfStorage,
            $this->eventDispatcher,
            $companyProvider,
        );

        $this->dispatchedEvents = []; // Reset

        try {
            $finalizer->finalize($invoice);
            $this->fail('Expected InvoiceFinalizationException to be thrown');
        } catch (InvoiceFinalizationException) {
            // Expected
        }

        $this->assertEmpty($this->dispatchedEvents);
    }

    // ========================================
    // Tests types de facture (2 tests)
    // ========================================

    public function testFinalizeCreditNoteUsesCorrectSequence(): void
    {
        $creditNote = $this->createDraftCreditNoteWithLines();

        $this->invoiceFinalizer->finalize($creditNote);

        $number = $creditNote->getNumber();
        $this->assertNotNull($number);
        $this->assertMatchesRegularExpression('/^AV-\d{4}-\d{4}$/', $number);
    }

    public function testFinalizeInvoiceUsesCorrectSequence(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        $this->invoiceFinalizer->finalize($invoice);

        $number = $invoice->getNumber();
        $this->assertNotNull($number);
        $this->assertMatchesRegularExpression('/^FA-\d{4}-\d{4}$/', $number);
    }

    // ========================================
    // Tests configuration (1 test)
    // ========================================

    public function testFinalizePassesCompanyDataToPdfGenerator(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        $this->invoiceFinalizer->finalize($invoice);

        // Vérifier que le PDF contient les données de la société
        $pdfPath = $invoice->getPdfPath();
        $this->assertNotNull($pdfPath, 'PDF path should be set after finalization');

        $pdfContent = $this->pdfStorage->retrieve($pdfPath);
        $text = $this->extractTextFromPdf($pdfContent);
        $this->assertStringContainsString('Test Company SARL', $text);
        $this->assertStringContainsString('123 Test Street', $text);
    }

    // ========================================
    // Helper methods
    // ========================================

    /**
     * Extract text content from a PDF binary string.
     */
    private function extractTextFromPdf(string $pdfBinary): string
    {
        $parser = new PdfParser();
        $pdf = $parser->parseContent($pdfBinary);

        return $pdf->getText();
    }

    private function createDraftInvoice(): Invoice
    {
        $customerData = new CustomerData(
            name: 'Test Customer',
            address: '456 Customer Street, 75002 Paris, France',
            email: 'customer@example.com',
            phone: '+33 1 98 76 54 32',
            siret: null,
            vatNumber: null,
        );

        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2025-01-15'),
            dueDate: new \DateTimeImmutable('2025-02-14'),
            customerName: $customerData->name,
            customerAddress: $customerData->address,
            companyName: 'Test Company SARL',
            companyAddress: '123 Test Street, 75001 Paris, France',
        );

        $invoice->setCustomerEmail($customerData->email);
        $invoice->setCustomerPhone($customerData->phone);
        $invoice->setPaymentTerms('30 jours net');

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $invoice;
    }

    private function createDraftInvoiceWithLines(): Invoice
    {
        $invoice = $this->createDraftInvoice();

        $line1 = new InvoiceLine(
            description: 'Service de développement',
            quantity: 10,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $line2 = new InvoiceLine(
            description: 'Service de conseil',
            quantity: 5,
            unitPrice: Money::fromEuros('150.00'),
            vatRate: 20.0,
        );

        $invoice->addLine($line1);
        $invoice->addLine($line2);

        $this->entityManager->flush();

        return $invoice;
    }

    private function createDraftCreditNoteWithLines(): Invoice
    {
        $customerData = new CustomerData(
            name: 'Test Customer',
            address: '456 Customer Street, 75002 Paris, France',
            email: 'customer@example.com',
            phone: '+33 1 98 76 54 32',
            siret: null,
            vatNumber: null,
        );

        $creditNote = new Invoice(
            type: InvoiceType::CREDIT_NOTE,
            date: new \DateTimeImmutable('2025-01-20'),
            dueDate: new \DateTimeImmutable('2025-02-19'),
            customerName: $customerData->name,
            customerAddress: $customerData->address,
            companyName: 'Test Company SARL',
            companyAddress: '123 Test Street, 75001 Paris, France',
        );

        $creditNote->setCustomerEmail($customerData->email);
        $creditNote->setCustomerPhone($customerData->phone);
        $creditNote->setPaymentTerms('30 jours net');

        $line = new InvoiceLine(
            description: 'Remboursement service',
            quantity: 1,
            unitPrice: Money::fromEuros('500.00'),
            vatRate: 20.0,
        );

        $creditNote->addLine($line);

        $this->entityManager->persist($creditNote);
        $this->entityManager->flush();

        return $creditNote;
    }
}
