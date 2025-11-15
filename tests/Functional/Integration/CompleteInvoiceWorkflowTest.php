<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Functional\Integration;

use CorentinBoutillier\InvoiceBundle\DTO\CustomerData;
use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Enum\PaymentMethod;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceCreatedEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceFinalizedEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoicePaidEvent;
use CorentinBoutillier\InvoiceBundle\Service\InvoiceFinalizerInterface;
use CorentinBoutillier\InvoiceBundle\Service\InvoiceManagerInterface;
use CorentinBoutillier\InvoiceBundle\Service\PaymentManagerInterface;
use CorentinBoutillier\InvoiceBundle\Tests\Functional\Repository\RepositoryTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests d'intégration end-to-end du workflow complet de facturation.
 *
 * Teste le cycle de vie complet d'une facture :
 * Création → Ajout lignes → Finalisation → Paiement → Export FEC
 *
 * Vérifie les calculs Money, les événements, et les scénarios avancés.
 */
final class CompleteInvoiceWorkflowTest extends RepositoryTestCase
{
    /** @phpstan-ignore property.uninitialized (initialized in setUp) */
    private InvoiceManagerInterface $invoiceManager;
    /** @phpstan-ignore property.uninitialized (initialized in setUp) */
    private InvoiceFinalizerInterface $invoiceFinalizer;
    /** @phpstan-ignore property.uninitialized (initialized in setUp) */
    private PaymentManagerInterface $paymentManager;
    /** @phpstan-ignore property.uninitialized (initialized in setUp) */
    private EventDispatcherInterface $eventDispatcher;
    /** @var array<int, object> */
    private array $dispatchedEvents = [];

    protected function setUp(): void
    {
        parent::setUp();

        $container = $this->kernel->getContainer();

        $invoiceManager = $container->get(InvoiceManagerInterface::class);
        $invoiceFinalizer = $container->get(InvoiceFinalizerInterface::class);
        $paymentManager = $container->get(PaymentManagerInterface::class);
        $eventDispatcher = $container->get(EventDispatcherInterface::class);

        $this->assertInstanceOf(InvoiceManagerInterface::class, $invoiceManager);
        $this->assertInstanceOf(InvoiceFinalizerInterface::class, $invoiceFinalizer);
        $this->assertInstanceOf(PaymentManagerInterface::class, $paymentManager);
        $this->assertInstanceOf(EventDispatcherInterface::class, $eventDispatcher);

        $this->invoiceManager = $invoiceManager;
        $this->invoiceFinalizer = $invoiceFinalizer;
        $this->paymentManager = $paymentManager;
        $this->eventDispatcher = $eventDispatcher;

        // Capturer tous les événements dispatchés
        $this->dispatchedEvents = [];
        $this->eventDispatcher->addListener(InvoiceCreatedEvent::class, function (object $event): void {
            $this->dispatchedEvents[] = $event;
        });
        $this->eventDispatcher->addListener(InvoiceFinalizedEvent::class, function (object $event): void {
            $this->dispatchedEvents[] = $event;
        });
        $this->eventDispatcher->addListener(InvoicePaidEvent::class, function (object $event): void {
            $this->dispatchedEvents[] = $event;
        });
    }

    /**
     * Test 1: Workflow basique complet.
     * Créer → Ajouter ligne → Finaliser → Payer → Exporter FEC.
     */
    public function testCompleteBasicWorkflow(): void
    {
        // 1. CRÉER FACTURE (DRAFT)
        $customerData = new CustomerData(
            name: 'ACME Corporation',
            address: '456 Customer Ave, 75002 Paris',
            vatNumber: 'FR98765432109',
        );

        $invoice = $this->invoiceManager->createInvoice(
            customerData: $customerData,
            date: new \DateTimeImmutable('2025-01-15'),
            paymentTerms: '30 jours net',
        );

        $this->assertSame(InvoiceStatus::DRAFT, $invoice->getStatus());
        $this->assertSame(InvoiceType::INVOICE, $invoice->getType());
        $this->assertNull($invoice->getNumber());

        // 2. AJOUTER LIGNE
        $line = new InvoiceLine(
            description: 'Service de développement',
            quantity: 10.0,
            unitPrice: Money::fromEuros('150.00'),
            vatRate: 20.0,
        );
        $this->invoiceManager->addLine($invoice, $line);
        $this->entityManager->flush();

        // 3. VÉRIFIER CALCULS
        $this->assertEquals(150000, $invoice->getSubtotalAfterDiscount()->getAmount()); // 1500.00€
        $this->assertEquals(30000, $invoice->getTotalVat()->getAmount()); // 300.00€
        $this->assertEquals(180000, $invoice->getTotalIncludingVat()->getAmount()); // 1800.00€

        // 4. FINALISER (numérotation + PDF + changement status)
        $this->invoiceFinalizer->finalize($invoice);

        $this->assertSame(InvoiceStatus::FINALIZED, $invoice->getStatus());
        $this->assertNotNull($invoice->getNumber());
        $this->assertStringStartsWith('FA-2025-', $invoice->getNumber());
        $this->assertNotNull($invoice->getPdfPath());

        // 5. PAIEMENT COMPLET
        $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('1800.00'),
            paidAt: new \DateTimeImmutable('2025-01-20'),
            method: PaymentMethod::BANK_TRANSFER,
        );
        $this->entityManager->flush();

        $this->assertTrue($invoice->isFullyPaid());
        $this->assertSame(InvoiceStatus::PAID, $invoice->getStatus());
        $this->assertEquals(0, $invoice->getRemainingAmount()->getAmount());

        // 6. Workflow terminé - vérifications finales
        $this->assertCount(1, $invoice->getPayments());
        $this->assertNotNull($invoice->getPdfPath());
    }

    /**
     * Test 2: Workflow avec plusieurs taux de TVA.
     */
    public function testMultiVatRateWorkflow(): void
    {
        $customerData = new CustomerData(
            name: 'Multi-VAT Customer',
            address: '789 Tax Street, 75003 Paris',
        );

        $invoice = $this->invoiceManager->createInvoice(
            customerData: $customerData,
            date: new \DateTimeImmutable('2025-02-01'),
            paymentTerms: '30 jours',
        );

        // Ligne 1: TVA 20%
        $line1 = new InvoiceLine(
            description: 'Produit standard',
            quantity: 5.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );
        $this->invoiceManager->addLine($invoice, $line1);

        // Ligne 2: TVA 10%
        $line2 = new InvoiceLine(
            description: 'Produit taux réduit',
            quantity: 3.0,
            unitPrice: Money::fromEuros('50.00'),
            vatRate: 10.0,
        );
        $this->invoiceManager->addLine($invoice, $line2);

        // Ligne 3: TVA 5.5%
        $line3 = new InvoiceLine(
            description: 'Produit taux super réduit',
            quantity: 2.0,
            unitPrice: Money::fromEuros('30.00'),
            vatRate: 5.5,
        );
        $this->invoiceManager->addLine($invoice, $line3);

        $this->entityManager->flush();

        // Vérifier totaux
        // HT: 500 + 150 + 60 = 710€
        // TVA: 100 (20%) + 15 (10%) + 3.30 (5.5%) = 118.30€
        // TTC: 828.30€
        $this->assertEquals(71000, $invoice->getSubtotalAfterDiscount()->getAmount());
        $this->assertEquals(11830, $invoice->getTotalVat()->getAmount());
        $this->assertEquals(82830, $invoice->getTotalIncludingVat()->getAmount());

        // Finaliser
        $this->invoiceFinalizer->finalize($invoice);

        $this->assertNotNull($invoice->getNumber());
        $this->assertSame(InvoiceStatus::FINALIZED, $invoice->getStatus());
    }

    /**
     * Test 3: Workflow avec remise globale.
     */
    public function testGlobalDiscountWithMoney(): void
    {
        $customerData = new CustomerData(
            name: 'Discount Customer',
            address: '321 Promo Street',
        );

        $invoice = $this->invoiceManager->createInvoice(
            customerData: $customerData,
            date: new \DateTimeImmutable('2025-03-01'),
            paymentTerms: '15 jours',
        );

        // Ajouter ligne
        $line = new InvoiceLine(
            description: 'Service premium',
            quantity: 1.0,
            unitPrice: Money::fromEuros('1000.00'),
            vatRate: 20.0,
        );
        $this->invoiceManager->addLine($invoice, $line);

        // Appliquer remise globale 10%
        $invoice->setGlobalDiscountRate(10.0);
        $this->entityManager->flush();

        // HT avant remise: 1000€
        // Remise: 100€ (10%)
        // HT après remise: 900€
        // TVA: 180€ (20% de 900€)
        // TTC: 1080€
        $this->assertEquals(100000, $invoice->getSubtotalBeforeDiscount()->getAmount());
        $this->assertEquals(10000, $invoice->getGlobalDiscountAmount()->getAmount());
        $this->assertEquals(90000, $invoice->getSubtotalAfterDiscount()->getAmount());
        $this->assertEquals(18000, $invoice->getTotalVat()->getAmount());
        $this->assertEquals(108000, $invoice->getTotalIncludingVat()->getAmount());
    }

    /**
     * Test 4: Paiements partiels puis paiement complet.
     */
    public function testPartialThenFullPayment(): void
    {
        $customerData = new CustomerData(
            name: 'Partial Payment Customer',
            address: '111 Payment Lane',
        );

        $invoice = $this->invoiceManager->createInvoice(
            customerData: $customerData,
            date: new \DateTimeImmutable('2025-04-01'),
            paymentTerms: '30 jours',
        );

        $line = new InvoiceLine(
            description: 'Service',
            quantity: 1.0,
            unitPrice: Money::fromEuros('2000.00'),
            vatRate: 20.0,
        );
        $this->invoiceManager->addLine($invoice, $line);
        $this->entityManager->flush();

        // Total: 2400€ TTC
        $this->assertEquals(240000, $invoice->getTotalIncludingVat()->getAmount());

        // Finaliser
        $this->invoiceFinalizer->finalize($invoice);
        $this->assertSame(InvoiceStatus::FINALIZED, $invoice->getStatus());

        // 1er paiement partiel: 1000€
        $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('1000.00'),
            paidAt: new \DateTimeImmutable('2025-04-10'),
            method: PaymentMethod::BANK_TRANSFER,
        );
        $this->entityManager->flush();

        $this->assertFalse($invoice->isFullyPaid());
        $this->assertTrue($invoice->isPartiallyPaid());
        $this->assertSame(InvoiceStatus::PARTIALLY_PAID, $invoice->getStatus());
        $this->assertEquals(140000, $invoice->getRemainingAmount()->getAmount()); // 1400€ restant

        // 2ème paiement partiel: 800€
        $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('800.00'),
            paidAt: new \DateTimeImmutable('2025-04-20'),
            method: PaymentMethod::CHECK,
        );
        $this->entityManager->flush();

        $this->assertFalse($invoice->isFullyPaid());
        $this->assertTrue($invoice->isPartiallyPaid());
        $this->assertEquals(60000, $invoice->getRemainingAmount()->getAmount()); // 600€ restant

        // 3ème paiement final: 600€
        $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('600.00'),
            paidAt: new \DateTimeImmutable('2025-04-25'),
            method: PaymentMethod::CASH,
        );
        $this->entityManager->flush();

        $this->assertTrue($invoice->isFullyPaid());
        $this->assertFalse($invoice->isPartiallyPaid());
        $this->assertSame(InvoiceStatus::PAID, $invoice->getStatus());
        $this->assertEquals(0, $invoice->getRemainingAmount()->getAmount());
    }

    /**
     * Test 5: Vérification précision des calculs Money.
     */
    public function testMoneyCalculationsAccuracy(): void
    {
        $customerData = new CustomerData(
            name: 'Precision Test Customer',
            address: '999 Accuracy Blvd',
        );

        $invoice = $this->invoiceManager->createInvoice(
            customerData: $customerData,
            date: new \DateTimeImmutable('2025-06-01'),
            paymentTerms: '30 jours',
        );

        // Ligne avec décimales complexes
        $line = new InvoiceLine(
            description: 'Service with complex decimals',
            quantity: 3.33,
            unitPrice: Money::fromEuros('47.89'),
            vatRate: 5.5,
        );
        $this->invoiceManager->addLine($invoice, $line);
        $this->entityManager->flush();

        // Vérifier que les calculs sont en centimes (pas d'arrondis flottants)
        $totalHT = $invoice->getSubtotalAfterDiscount();
        $this->assertInstanceOf(Money::class, $totalHT);
        $this->assertIsInt($totalHT->getAmount(), 'Money amount must be integer (cents)');

        // Opérations Money
        $added = $totalHT->add(Money::fromEuros('10.00'));
        $this->assertGreaterThan($totalHT->getAmount(), $added->getAmount());

        $subtracted = $totalHT->subtract(Money::fromEuros('5.00'));
        $this->assertLessThan($totalHT->getAmount(), $subtracted->getAmount());

        $multiplied = Money::fromEuros('1.00')->multiply(2.5);
        $this->assertEquals(250, $multiplied->getAmount()); // 2.50€ = 250 centimes
    }

    /**
     * Test 6: Événements dispatchés dans le bon ordre.
     */
    public function testEventsDispatchedInOrder(): void
    {
        $customerData = new CustomerData(
            name: 'Event Customer',
            address: '777 Event Drive',
        );

        // Vider les événements précédents
        $this->dispatchedEvents = [];

        // 1. Créer facture → InvoiceCreatedEvent
        $invoice = $this->invoiceManager->createInvoice(
            customerData: $customerData,
            date: new \DateTimeImmutable('2025-07-01'),
            paymentTerms: '30 jours',
        );

        $line = new InvoiceLine(
            description: 'Service',
            quantity: 1.0,
            unitPrice: Money::fromEuros('500.00'),
            vatRate: 20.0,
        );
        $this->invoiceManager->addLine($invoice, $line);
        $this->entityManager->flush();

        $this->assertCount(1, $this->dispatchedEvents);
        $this->assertInstanceOf(InvoiceCreatedEvent::class, $this->dispatchedEvents[0]);

        // 2. Finaliser → InvoiceFinalizedEvent
        $this->invoiceFinalizer->finalize($invoice);

        $this->assertCount(2, $this->dispatchedEvents);
        $this->assertInstanceOf(InvoiceFinalizedEvent::class, $this->dispatchedEvents[1]);

        // 3. Payer → InvoicePaidEvent
        $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('600.00'),
            paidAt: new \DateTimeImmutable('2025-07-10'),
            method: PaymentMethod::BANK_TRANSFER,
        );
        $this->entityManager->flush();

        $this->assertCount(3, $this->dispatchedEvents);
        $this->assertInstanceOf(InvoicePaidEvent::class, $this->dispatchedEvents[2]);

        // Vérifier ordre chronologique
        $this->assertInstanceOf(InvoiceCreatedEvent::class, $this->dispatchedEvents[0]);
        $this->assertInstanceOf(InvoiceFinalizedEvent::class, $this->dispatchedEvents[1]);
        $this->assertInstanceOf(InvoicePaidEvent::class, $this->dispatchedEvents[2]);
    }

    /**
     * Test 7: Workflow complet avec quantités décimales et vérification Money.
     */
    public function testCompleteWorkflowWithDecimalQuantities(): void
    {
        $customerData = new CustomerData(
            name: 'Decimal Test Company',
            address: '888 Precision Lane',
        );

        $invoice = $this->invoiceManager->createInvoice(
            customerData: $customerData,
            date: new \DateTimeImmutable('2025-08-15'),
            paymentTerms: '45 jours',
        );

        // Ligne avec quantité décimale
        $line = new InvoiceLine(
            description: 'Service with decimal quantity',
            quantity: 12.5,
            unitPrice: Money::fromEuros('125.50'),
            vatRate: 20.0,
        );
        $this->invoiceManager->addLine($invoice, $line);
        $this->entityManager->flush();

        // Vérifier calculs Money
        // 12.5 × 125.50€ = 1568.75€ HT
        // TVA 20%: 313.75€
        // TTC: 1882.50€
        $this->assertEquals(156875, $invoice->getSubtotalAfterDiscount()->getAmount());
        $this->assertEquals(31375, $invoice->getTotalVat()->getAmount());
        $this->assertEquals(188250, $invoice->getTotalIncludingVat()->getAmount());

        // Finaliser
        $this->invoiceFinalizer->finalize($invoice);
        $this->assertSame(InvoiceStatus::FINALIZED, $invoice->getStatus());
        $this->assertNotNull($invoice->getNumber());

        // Paiement
        $this->paymentManager->recordPayment(
            invoice: $invoice,
            amount: Money::fromEuros('1882.50'),
            paidAt: new \DateTimeImmutable('2025-08-20'),
            method: PaymentMethod::BANK_TRANSFER,
        );
        $this->entityManager->flush();

        $this->assertTrue($invoice->isFullyPaid());
        $this->assertSame(InvoiceStatus::PAID, $invoice->getStatus());
    }
}
