<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\EventSubscriber;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\DTO\UserData;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceHistory;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceHistoryAction;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Event\CreditNoteCreatedEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceCancelledEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceCreatedEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceFinalizedEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceOverdueEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoicePaidEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoicePartiallyPaidEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoicePdfGeneratedEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceStatusChangedEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceUpdatedEvent;
use CorentinBoutillier\InvoiceBundle\EventSubscriber\InvoiceHistorySubscriber;
use CorentinBoutillier\InvoiceBundle\Provider\UserProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class InvoiceHistorySubscriberTest extends TestCase
{
    /** @phpstan-ignore property.uninitialized */
    private EntityManagerInterface&MockObject $entityManager;
    /** @phpstan-ignore property.uninitialized */
    private UserProviderInterface&MockObject $userProvider;
    /** @phpstan-ignore property.uninitialized */
    private InvoiceHistorySubscriber $subscriber;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userProvider = $this->createMock(UserProviderInterface::class);
        $this->subscriber = new InvoiceHistorySubscriber(
            $this->entityManager,
            $this->userProvider,
        );
    }

    // ========== Subscriber Registration ==========

    public function testImplementsEventSubscriberInterface(): void
    {
        $this->assertInstanceOf(EventSubscriberInterface::class, $this->subscriber);
    }

    public function testGetSubscribedEventsReturnsAllEvents(): void
    {
        $subscribedEvents = InvoiceHistorySubscriber::getSubscribedEvents();

        $this->assertIsArray($subscribedEvents);
        $this->assertCount(10, $subscribedEvents);
        $this->assertArrayHasKey(InvoiceCreatedEvent::class, $subscribedEvents);
        $this->assertArrayHasKey(InvoiceUpdatedEvent::class, $subscribedEvents);
        $this->assertArrayHasKey(InvoiceFinalizedEvent::class, $subscribedEvents);
        $this->assertArrayHasKey(InvoiceStatusChangedEvent::class, $subscribedEvents);
        $this->assertArrayHasKey(InvoicePaidEvent::class, $subscribedEvents);
        $this->assertArrayHasKey(InvoicePartiallyPaidEvent::class, $subscribedEvents);
        $this->assertArrayHasKey(InvoiceOverdueEvent::class, $subscribedEvents);
        $this->assertArrayHasKey(InvoiceCancelledEvent::class, $subscribedEvents);
        $this->assertArrayHasKey(CreditNoteCreatedEvent::class, $subscribedEvents);
        $this->assertArrayHasKey(InvoicePdfGeneratedEvent::class, $subscribedEvents);
    }

    public function testGetSubscribedEventsMapsToCorrectHandlers(): void
    {
        $subscribedEvents = InvoiceHistorySubscriber::getSubscribedEvents();

        $this->assertSame('onInvoiceCreated', $subscribedEvents[InvoiceCreatedEvent::class]);
        $this->assertSame('onInvoiceUpdated', $subscribedEvents[InvoiceUpdatedEvent::class]);
        $this->assertSame('onInvoiceFinalized', $subscribedEvents[InvoiceFinalizedEvent::class]);
        $this->assertSame('onInvoiceStatusChanged', $subscribedEvents[InvoiceStatusChangedEvent::class]);
        $this->assertSame('onInvoicePaid', $subscribedEvents[InvoicePaidEvent::class]);
        $this->assertSame('onInvoicePartiallyPaid', $subscribedEvents[InvoicePartiallyPaidEvent::class]);
        $this->assertSame('onInvoiceOverdue', $subscribedEvents[InvoiceOverdueEvent::class]);
        $this->assertSame('onInvoiceCancelled', $subscribedEvents[InvoiceCancelledEvent::class]);
        $this->assertSame('onCreditNoteCreated', $subscribedEvents[CreditNoteCreatedEvent::class]);
        $this->assertSame('onInvoicePdfGenerated', $subscribedEvents[InvoicePdfGeneratedEvent::class]);
    }

    // ========== onInvoiceCreated ==========

    public function testOnInvoiceCreatedRecordsHistoryWithActionCreated(): void
    {
        $invoice = $this->createInvoice();
        $event = new InvoiceCreatedEvent($invoice);

        $this->userProvider->method('getCurrentUser')->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (InvoiceHistory $history) use ($invoice) {
                return $history->getInvoice() === $invoice
                    && InvoiceHistoryAction::CREATED === $history->getAction()
                    && null === $history->getUserId()
                    && $history->getMetadata() === ['type' => 'invoice'];
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->subscriber->onInvoiceCreated($event);
    }

    // ========== onInvoiceUpdated ==========

    public function testOnInvoiceUpdatedRecordsHistoryWithChangedFields(): void
    {
        $invoice = $this->createInvoice();
        $changedFields = ['dueDate', 'paymentTerms'];
        $event = new InvoiceUpdatedEvent($invoice, $changedFields);

        $this->userProvider->method('getCurrentUser')->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (InvoiceHistory $history) use ($invoice, $changedFields) {
                return $history->getInvoice() === $invoice
                    && InvoiceHistoryAction::EDITED === $history->getAction()
                    && $history->getMetadata() === ['changed_fields' => $changedFields];
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->subscriber->onInvoiceUpdated($event);
    }

    // ========== onInvoiceFinalized ==========

    public function testOnInvoiceFinalizedRecordsHistoryWithInvoiceNumber(): void
    {
        $invoice = $this->createInvoice();
        $number = 'FA-2025-0001';
        $event = new InvoiceFinalizedEvent($invoice, $number);

        $this->userProvider->method('getCurrentUser')->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (InvoiceHistory $history) use ($invoice, $number) {
                return $history->getInvoice() === $invoice
                    && InvoiceHistoryAction::FINALIZED === $history->getAction()
                    && $history->getMetadata() === ['number' => $number];
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->subscriber->onInvoiceFinalized($event);
    }

    // ========== onInvoiceStatusChanged ==========

    public function testOnInvoiceStatusChangedRecordsHistoryWithOldAndNewStatus(): void
    {
        $invoice = $this->createInvoice();
        $oldStatus = InvoiceStatus::DRAFT;
        $newStatus = InvoiceStatus::FINALIZED;
        $event = new InvoiceStatusChangedEvent($invoice, $oldStatus, $newStatus);

        $this->userProvider->method('getCurrentUser')->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (InvoiceHistory $history) use ($invoice) {
                $metadata = $history->getMetadata();

                return $history->getInvoice() === $invoice
                    && InvoiceHistoryAction::STATUS_CHANGED === $history->getAction()
                    && isset($metadata['old_status'])
                    && isset($metadata['new_status'])
                    && 'draft' === $metadata['old_status']
                    && 'finalized' === $metadata['new_status'];
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->subscriber->onInvoiceStatusChanged($event);
    }

    // ========== onInvoicePaid ==========

    public function testOnInvoicePaidRecordsHistoryWithPaidDate(): void
    {
        $invoice = $this->createInvoice();
        $paidAt = new \DateTimeImmutable('2025-01-15 10:30:00');
        $event = new InvoicePaidEvent($invoice, $paidAt);

        $this->userProvider->method('getCurrentUser')->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (InvoiceHistory $history) use ($invoice) {
                $metadata = $history->getMetadata();

                return $history->getInvoice() === $invoice
                    && InvoiceHistoryAction::PAID === $history->getAction()
                    && isset($metadata['paid_at'])
                    && '2025-01-15T10:30:00+00:00' === $metadata['paid_at'];
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->subscriber->onInvoicePaid($event);
    }

    // ========== onInvoicePartiallyPaid ==========

    public function testOnInvoicePartiallyPaidRecordsHistoryWithAmounts(): void
    {
        $invoice = $this->createInvoice();
        $amountPaid = Money::fromCents(15000);
        $remainingAmount = Money::fromCents(5000);
        $event = new InvoicePartiallyPaidEvent($invoice, $amountPaid, $remainingAmount);

        $this->userProvider->method('getCurrentUser')->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (InvoiceHistory $history) use ($invoice) {
                $metadata = $history->getMetadata();

                return $history->getInvoice() === $invoice
                    && InvoiceHistoryAction::PAYMENT_RECEIVED === $history->getAction()
                    && null !== $metadata
                    && 15000 === $metadata['amount_paid_cents']
                    && 5000 === $metadata['remaining_cents'];
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->subscriber->onInvoicePartiallyPaid($event);
    }

    // ========== onInvoiceOverdue ==========

    public function testOnInvoiceOverdueRecordsHistoryWithDaysOverdue(): void
    {
        $invoice = $this->createInvoice();
        $daysOverdue = 15;
        $event = new InvoiceOverdueEvent($invoice, $daysOverdue);

        $this->userProvider->method('getCurrentUser')->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (InvoiceHistory $history) use ($invoice, $daysOverdue) {
                return $history->getInvoice() === $invoice
                    && InvoiceHistoryAction::STATUS_CHANGED === $history->getAction()
                    && $history->getMetadata() === ['days_overdue' => $daysOverdue];
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->subscriber->onInvoiceOverdue($event);
    }

    // ========== onInvoiceCancelled ==========

    public function testOnInvoiceCancelledRecordsHistoryWithReason(): void
    {
        $invoice = $this->createInvoice();
        $reason = 'Duplicate invoice';
        $event = new InvoiceCancelledEvent($invoice, $reason);

        $this->userProvider->method('getCurrentUser')->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (InvoiceHistory $history) use ($invoice, $reason) {
                return $history->getInvoice() === $invoice
                    && InvoiceHistoryAction::CANCELLED === $history->getAction()
                    && $history->getMetadata() === ['reason' => $reason];
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->subscriber->onInvoiceCancelled($event);
    }

    public function testOnInvoiceCancelledWithoutReasonRecordsHistoryWithNullMetadata(): void
    {
        $invoice = $this->createInvoice();
        $event = new InvoiceCancelledEvent($invoice, null);

        $this->userProvider->method('getCurrentUser')->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (InvoiceHistory $history) use ($invoice) {
                return $history->getInvoice() === $invoice
                    && InvoiceHistoryAction::CANCELLED === $history->getAction()
                    && $history->getMetadata() === ['reason' => null];
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->subscriber->onInvoiceCancelled($event);
    }

    // ========== onCreditNoteCreated ==========

    public function testOnCreditNoteCreatedRecordsHistoryWithTypeCreditNote(): void
    {
        $creditNote = $this->createInvoice(InvoiceType::CREDIT_NOTE);
        $event = new CreditNoteCreatedEvent($creditNote, null);

        $this->userProvider->method('getCurrentUser')->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (InvoiceHistory $history) use ($creditNote) {
                return $history->getInvoice() === $creditNote
                    && InvoiceHistoryAction::CREATED === $history->getAction()
                    && $history->getMetadata() === ['type' => 'credit_note'];
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->subscriber->onCreditNoteCreated($event);
    }

    // ========== onInvoicePdfGenerated ==========

    public function testOnInvoicePdfGeneratedRecordsHistoryWithPdfPath(): void
    {
        $invoice = $this->createInvoice();
        $pdfPath = '/var/invoices/2025/01/FA-2025-0001.pdf';
        $invoice->setPdfPath($pdfPath);

        $pdfContent = '%PDF-1.4 fake pdf content';
        $event = new InvoicePdfGeneratedEvent($invoice, $pdfContent);

        $this->userProvider->method('getCurrentUser')->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (InvoiceHistory $history) use ($invoice, $pdfPath) {
                return $history->getInvoice() === $invoice
                    && InvoiceHistoryAction::FINALIZED === $history->getAction()
                    && $history->getMetadata() === ['pdf_path' => $pdfPath];
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->subscriber->onInvoicePdfGenerated($event);
    }

    // ========== UserProvider Integration ==========

    public function testHistoryIncludesUserIdWhenUserIsAuthenticated(): void
    {
        $invoice = $this->createInvoice();
        $event = new InvoiceCreatedEvent($invoice);

        $userData = new UserData(
            id: '123',
            name: 'John Doe',
            email: 'john@example.com',
        );
        $this->userProvider->method('getCurrentUser')->willReturn($userData);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (InvoiceHistory $history) {
                return 123 === $history->getUserId();
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->subscriber->onInvoiceCreated($event);
    }

    public function testHistoryIncludesUserInfoInMetadataWhenUserIsAuthenticated(): void
    {
        $invoice = $this->createInvoice();
        $event = new InvoiceCreatedEvent($invoice);

        $userData = new UserData(
            id: '456',
            name: 'Jane Smith',
            email: 'jane@example.com',
        );
        $this->userProvider->method('getCurrentUser')->willReturn($userData);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (InvoiceHistory $history) {
                $metadata = $history->getMetadata();

                return null !== $metadata
                    && isset($metadata['user'])
                    && \is_array($metadata['user'])
                    && 'Jane Smith' === $metadata['user']['name']
                    && 'jane@example.com' === $metadata['user']['email'];
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->subscriber->onInvoiceCreated($event);
    }

    public function testHistoryHasNullUserIdWhenNoUserAuthenticated(): void
    {
        $invoice = $this->createInvoice();
        $event = new InvoiceCreatedEvent($invoice);

        $this->userProvider->method('getCurrentUser')->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (InvoiceHistory $history) {
                return null === $history->getUserId();
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->subscriber->onInvoiceCreated($event);
    }

    public function testHistoryDoesNotIncludeUserInfoInMetadataWhenNoUserAuthenticated(): void
    {
        $invoice = $this->createInvoice();
        $event = new InvoiceCreatedEvent($invoice);

        $this->userProvider->method('getCurrentUser')->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (InvoiceHistory $history) {
                $metadata = $history->getMetadata();

                return !isset($metadata['user']);
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->subscriber->onInvoiceCreated($event);
    }

    // ========== Helper Methods ==========

    private function createInvoice(?InvoiceType $type = null): Invoice
    {
        return new Invoice(
            type: $type ?? InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2025-01-15'),
            dueDate: new \DateTimeImmutable('2025-02-14'),
            customerName: 'Test Customer',
            customerAddress: '123 Test Street',
            companyName: 'Test Company',
            companyAddress: '456 Company Avenue',
        );
    }
}
