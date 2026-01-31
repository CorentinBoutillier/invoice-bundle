<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Functional\Repository;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceTransmission;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Pdp\PdpStatusCode;
use CorentinBoutillier\InvoiceBundle\Repository\InvoiceTransmissionRepository;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InvoiceTransmissionRepository::class)]
final class InvoiceTransmissionRepositoryTest extends RepositoryTestCase
{
    /** @phpstan-ignore property.uninitialized */
    private InvoiceTransmissionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $repository = $this->entityManager->getRepository(InvoiceTransmission::class);
        if (!$repository instanceof InvoiceTransmissionRepository) {
            throw new \RuntimeException('InvoiceTransmissionRepository not found');
        }
        $this->repository = $repository;
    }

    // ========================================
    // findLatestForInvoice() Tests
    // ========================================

    public function testFindLatestForInvoiceReturnsNullWhenNoTransmissions(): void
    {
        $invoice = $this->createInvoice();
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $result = $this->repository->findLatestForInvoice($invoice);

        self::assertNull($result);
    }

    public function testFindLatestForInvoiceReturnsMostRecent(): void
    {
        $invoice = $this->createInvoice();
        $this->entityManager->persist($invoice);

        // Create older transmission with forced older date
        $transmission1 = new InvoiceTransmission($invoice, 'connector_1', 'TX-001');
        $this->setCreatedAt($transmission1, new \DateTimeImmutable('2025-01-01 10:00:00'));
        $this->entityManager->persist($transmission1);

        // Create newer transmission with forced newer date
        $transmission2 = new InvoiceTransmission($invoice, 'connector_1', 'TX-002');
        $this->setCreatedAt($transmission2, new \DateTimeImmutable('2025-01-02 10:00:00'));
        $this->entityManager->persist($transmission2);

        $this->entityManager->flush();

        $result = $this->repository->findLatestForInvoice($invoice);

        self::assertNotNull($result);
        self::assertSame('TX-002', $result->getTransmissionId());
    }

    // ========================================
    // findByInvoice() Tests
    // ========================================

    public function testFindByInvoiceReturnsEmptyArrayWhenNoTransmissions(): void
    {
        $invoice = $this->createInvoice();
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $result = $this->repository->findByInvoice($invoice);

        self::assertSame([], $result);
    }

    public function testFindByInvoiceReturnsAllTransmissionsOrderedByCreatedAtDesc(): void
    {
        $invoice = $this->createInvoice();
        $this->entityManager->persist($invoice);

        $transmission1 = new InvoiceTransmission($invoice, 'connector_1', 'TX-001');
        $this->setCreatedAt($transmission1, new \DateTimeImmutable('2025-01-01 10:00:00'));
        $this->entityManager->persist($transmission1);

        $transmission2 = new InvoiceTransmission($invoice, 'connector_2', 'TX-002');
        $this->setCreatedAt($transmission2, new \DateTimeImmutable('2025-01-02 10:00:00'));
        $this->entityManager->persist($transmission2);

        $this->entityManager->flush();

        $result = $this->repository->findByInvoice($invoice);

        self::assertCount(2, $result);
        // Most recent first
        self::assertSame('TX-002', $result[0]->getTransmissionId());
        self::assertSame('TX-001', $result[1]->getTransmissionId());
    }

    public function testFindByInvoiceDoesNotReturnOtherInvoicesTransmissions(): void
    {
        $invoice1 = $this->createInvoice('FA-2025-0001');
        $invoice2 = $this->createInvoice('FA-2025-0002');
        $this->entityManager->persist($invoice1);
        $this->entityManager->persist($invoice2);

        $transmission1 = new InvoiceTransmission($invoice1, 'connector_1', 'TX-001');
        $transmission2 = new InvoiceTransmission($invoice2, 'connector_1', 'TX-002');
        $this->entityManager->persist($transmission1);
        $this->entityManager->persist($transmission2);
        $this->entityManager->flush();

        $result = $this->repository->findByInvoice($invoice1);

        self::assertCount(1, $result);
        self::assertSame('TX-001', $result[0]->getTransmissionId());
    }

    // ========================================
    // findByTransmissionId() Tests
    // ========================================

    public function testFindByTransmissionIdReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findByTransmissionId('NONEXISTENT');

        self::assertNull($result);
    }

    public function testFindByTransmissionIdReturnsMatchingTransmission(): void
    {
        $invoice = $this->createInvoice();
        $this->entityManager->persist($invoice);

        $transmission = new InvoiceTransmission($invoice, 'connector_1', 'TX-UNIQUE-123');
        $this->entityManager->persist($transmission);
        $this->entityManager->flush();

        $result = $this->repository->findByTransmissionId('TX-UNIQUE-123');

        self::assertNotNull($result);
        self::assertSame('TX-UNIQUE-123', $result->getTransmissionId());
        self::assertSame('connector_1', $result->getConnectorId());
    }

    // ========================================
    // findPending() Tests
    // ========================================

    public function testFindPendingReturnsOnlyNonTerminalTransmissions(): void
    {
        $invoice = $this->createInvoice();
        $this->entityManager->persist($invoice);

        // Pending statuses (non-terminal)
        $pending = new InvoiceTransmission($invoice, 'conn', 'TX-PENDING', PdpStatusCode::PENDING);
        $submitted = new InvoiceTransmission($invoice, 'conn', 'TX-SUBMITTED', PdpStatusCode::SUBMITTED);
        $transmitted = new InvoiceTransmission($invoice, 'conn', 'TX-TRANSMITTED', PdpStatusCode::TRANSMITTED);

        // Terminal statuses
        $paid = new InvoiceTransmission($invoice, 'conn', 'TX-PAID', PdpStatusCode::PAID);
        $rejected = new InvoiceTransmission($invoice, 'conn', 'TX-REJECTED', PdpStatusCode::REJECTED);
        $failed = new InvoiceTransmission($invoice, 'conn', 'TX-FAILED', PdpStatusCode::FAILED);

        $this->entityManager->persist($pending);
        $this->entityManager->persist($submitted);
        $this->entityManager->persist($transmitted);
        $this->entityManager->persist($paid);
        $this->entityManager->persist($rejected);
        $this->entityManager->persist($failed);
        $this->entityManager->flush();

        $result = $this->repository->findPending();

        self::assertCount(3, $result);

        $transmissionIds = array_map(fn (InvoiceTransmission $t) => $t->getTransmissionId(), $result);
        self::assertContains('TX-PENDING', $transmissionIds);
        self::assertContains('TX-SUBMITTED', $transmissionIds);
        self::assertContains('TX-TRANSMITTED', $transmissionIds);

        self::assertNotContains('TX-PAID', $transmissionIds);
        self::assertNotContains('TX-REJECTED', $transmissionIds);
        self::assertNotContains('TX-FAILED', $transmissionIds);
    }

    public function testFindPendingOrdersByCreatedAtAsc(): void
    {
        $invoice = $this->createInvoice();
        $this->entityManager->persist($invoice);

        $t1 = new InvoiceTransmission($invoice, 'conn', 'TX-FIRST', PdpStatusCode::PENDING);
        $this->setCreatedAt($t1, new \DateTimeImmutable('2025-01-01 10:00:00'));
        $this->entityManager->persist($t1);

        $t2 = new InvoiceTransmission($invoice, 'conn', 'TX-SECOND', PdpStatusCode::PENDING);
        $this->setCreatedAt($t2, new \DateTimeImmutable('2025-01-02 10:00:00'));
        $this->entityManager->persist($t2);

        $this->entityManager->flush();

        $result = $this->repository->findPending();

        self::assertCount(2, $result);
        // Oldest first (ASC)
        self::assertSame('TX-FIRST', $result[0]->getTransmissionId());
        self::assertSame('TX-SECOND', $result[1]->getTransmissionId());
    }

    // ========================================
    // findNeedingRetry() Tests
    // ========================================

    public function testFindNeedingRetryReturnsFailedWithRetryCountBelowLimit(): void
    {
        $invoice = $this->createInvoice();
        $this->entityManager->persist($invoice);

        // Failed with 0 retries - should be returned
        $t1 = new InvoiceTransmission($invoice, 'conn', 'TX-RETRY-0', PdpStatusCode::FAILED);
        $this->entityManager->persist($t1);

        // Failed with 2 retries - should be returned (default max is 3)
        $t2 = new InvoiceTransmission($invoice, 'conn', 'TX-RETRY-2', PdpStatusCode::FAILED);
        $t2->incrementRetryCount();
        $t2->incrementRetryCount();
        $this->entityManager->persist($t2);

        // Failed with 3 retries - should NOT be returned
        $t3 = new InvoiceTransmission($invoice, 'conn', 'TX-RETRY-3', PdpStatusCode::FAILED);
        $t3->incrementRetryCount();
        $t3->incrementRetryCount();
        $t3->incrementRetryCount();
        $this->entityManager->persist($t3);

        // Pending - should NOT be returned
        $t4 = new InvoiceTransmission($invoice, 'conn', 'TX-PENDING', PdpStatusCode::PENDING);
        $this->entityManager->persist($t4);

        $this->entityManager->flush();

        $result = $this->repository->findNeedingRetry(maxRetries: 3);

        self::assertCount(2, $result);

        $transmissionIds = array_map(fn (InvoiceTransmission $t) => $t->getTransmissionId(), $result);
        self::assertContains('TX-RETRY-0', $transmissionIds);
        self::assertContains('TX-RETRY-2', $transmissionIds);
    }

    public function testFindNeedingRetryWithCustomMaxRetries(): void
    {
        $invoice = $this->createInvoice();
        $this->entityManager->persist($invoice);

        $t1 = new InvoiceTransmission($invoice, 'conn', 'TX-RETRY-1', PdpStatusCode::FAILED);
        $t1->incrementRetryCount();
        $this->entityManager->persist($t1);

        $this->entityManager->flush();

        // With max retries of 1, should not return (retryCount >= maxRetries)
        $result = $this->repository->findNeedingRetry(maxRetries: 1);
        self::assertCount(0, $result);

        // With max retries of 2, should return
        $result = $this->repository->findNeedingRetry(maxRetries: 2);
        self::assertCount(1, $result);
    }

    // ========================================
    // findByConnector() Tests
    // ========================================

    public function testFindByConnectorReturnsOnlyMatchingConnector(): void
    {
        $invoice = $this->createInvoice();
        $this->entityManager->persist($invoice);

        $t1 = new InvoiceTransmission($invoice, 'chorus_pro', 'TX-1');
        $t2 = new InvoiceTransmission($invoice, 'chorus_pro', 'TX-2');
        $t3 = new InvoiceTransmission($invoice, 'pennylane', 'TX-3');

        $this->entityManager->persist($t1);
        $this->entityManager->persist($t2);
        $this->entityManager->persist($t3);
        $this->entityManager->flush();

        $result = $this->repository->findByConnector('chorus_pro');

        self::assertCount(2, $result);

        foreach ($result as $transmission) {
            self::assertSame('chorus_pro', $transmission->getConnectorId());
        }
    }

    public function testFindByConnectorReturnsEmptyArrayWhenNoMatches(): void
    {
        $invoice = $this->createInvoice();
        $this->entityManager->persist($invoice);

        $t1 = new InvoiceTransmission($invoice, 'chorus_pro', 'TX-1');
        $this->entityManager->persist($t1);
        $this->entityManager->flush();

        $result = $this->repository->findByConnector('nonexistent_connector');

        self::assertSame([], $result);
    }

    // ========================================
    // findByStatus() Tests
    // ========================================

    public function testFindByStatusReturnsOnlyMatchingStatus(): void
    {
        $invoice = $this->createInvoice();
        $this->entityManager->persist($invoice);

        $t1 = new InvoiceTransmission($invoice, 'conn', 'TX-1', PdpStatusCode::PENDING);
        $t2 = new InvoiceTransmission($invoice, 'conn', 'TX-2', PdpStatusCode::SUBMITTED);
        $t3 = new InvoiceTransmission($invoice, 'conn', 'TX-3', PdpStatusCode::SUBMITTED);

        $this->entityManager->persist($t1);
        $this->entityManager->persist($t2);
        $this->entityManager->persist($t3);
        $this->entityManager->flush();

        $result = $this->repository->findByStatus(PdpStatusCode::SUBMITTED);

        self::assertCount(2, $result);

        foreach ($result as $transmission) {
            self::assertSame(PdpStatusCode::SUBMITTED, $transmission->getStatus());
        }
    }

    // ========================================
    // countByStatus() Tests
    // ========================================

    public function testCountByStatusReturnsCorrectCounts(): void
    {
        $invoice = $this->createInvoice();
        $this->entityManager->persist($invoice);

        // 2 pending, 3 submitted, 1 paid
        $this->entityManager->persist(new InvoiceTransmission($invoice, 'conn', 'TX-1', PdpStatusCode::PENDING));
        $this->entityManager->persist(new InvoiceTransmission($invoice, 'conn', 'TX-2', PdpStatusCode::PENDING));
        $this->entityManager->persist(new InvoiceTransmission($invoice, 'conn', 'TX-3', PdpStatusCode::SUBMITTED));
        $this->entityManager->persist(new InvoiceTransmission($invoice, 'conn', 'TX-4', PdpStatusCode::SUBMITTED));
        $this->entityManager->persist(new InvoiceTransmission($invoice, 'conn', 'TX-5', PdpStatusCode::SUBMITTED));
        $this->entityManager->persist(new InvoiceTransmission($invoice, 'conn', 'TX-6', PdpStatusCode::PAID));
        $this->entityManager->flush();

        $result = $this->repository->countByStatus();

        self::assertSame(2, $result['pending']);
        self::assertSame(3, $result['submitted']);
        self::assertSame(1, $result['paid']);
    }

    public function testCountByStatusReturnsEmptyArrayWhenNoTransmissions(): void
    {
        $result = $this->repository->countByStatus();

        self::assertSame([], $result);
    }

    // ========================================
    // Helper Methods
    // ========================================

    private function createInvoice(string $number = 'FA-2025-0001'): Invoice
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2025-01-15'),
            dueDate: new \DateTimeImmutable('2025-02-14'),
            customerName: 'Test Customer SA',
            customerAddress: '456 Customer Street, 75002 Paris',
            companyName: 'Test Company SARL',
            companyAddress: '123 Test Street, 75001 Paris',
        );

        $invoice->setStatus(InvoiceStatus::FINALIZED);
        $invoice->setNumber($number);
        $invoice->setCompanyId(1);

        $line = new InvoiceLine(
            description: 'Service de développement',
            quantity: 10,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );
        $invoice->addLine($line);

        return $invoice;
    }

    /**
     * Helper to set createdAt via reflection for testing timestamp ordering.
     */
    private function setCreatedAt(InvoiceTransmission $transmission, \DateTimeImmutable $createdAt): void
    {
        $reflection = new \ReflectionClass($transmission);
        $property = $reflection->getProperty('createdAt');
        $property->setValue($transmission, $createdAt);
    }
}
