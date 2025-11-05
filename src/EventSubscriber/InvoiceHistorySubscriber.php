<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\EventSubscriber;

use CorentinBoutillier\InvoiceBundle\Entity\InvoiceHistory;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceHistoryAction;
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
use CorentinBoutillier\InvoiceBundle\Provider\UserProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Automatically records all invoice state changes in the audit trail.
 *
 * This subscriber listens to all invoice domain events and creates
 * InvoiceHistory entries with appropriate metadata.
 */
final class InvoiceHistorySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserProviderInterface $userProvider,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            InvoiceCreatedEvent::class => 'onInvoiceCreated',
            InvoiceUpdatedEvent::class => 'onInvoiceUpdated',
            InvoiceFinalizedEvent::class => 'onInvoiceFinalized',
            InvoiceStatusChangedEvent::class => 'onInvoiceStatusChanged',
            InvoicePaidEvent::class => 'onInvoicePaid',
            InvoicePartiallyPaidEvent::class => 'onInvoicePartiallyPaid',
            InvoiceOverdueEvent::class => 'onInvoiceOverdue',
            InvoiceCancelledEvent::class => 'onInvoiceCancelled',
            CreditNoteCreatedEvent::class => 'onCreditNoteCreated',
            InvoicePdfGeneratedEvent::class => 'onInvoicePdfGenerated',
        ];
    }

    public function onInvoiceCreated(InvoiceCreatedEvent $event): void
    {
        $this->recordHistory(
            invoice: $event->invoice,
            action: InvoiceHistoryAction::CREATED,
            metadata: ['type' => 'invoice'],
        );
    }

    public function onInvoiceUpdated(InvoiceUpdatedEvent $event): void
    {
        $this->recordHistory(
            invoice: $event->invoice,
            action: InvoiceHistoryAction::EDITED,
            metadata: ['changed_fields' => $event->changedFields],
        );
    }

    public function onInvoiceFinalized(InvoiceFinalizedEvent $event): void
    {
        $this->recordHistory(
            invoice: $event->invoice,
            action: InvoiceHistoryAction::FINALIZED,
            metadata: ['number' => $event->number],
        );
    }

    public function onInvoiceStatusChanged(InvoiceStatusChangedEvent $event): void
    {
        $this->recordHistory(
            invoice: $event->invoice,
            action: InvoiceHistoryAction::STATUS_CHANGED,
            metadata: [
                'old_status' => $event->oldStatus->value,
                'new_status' => $event->newStatus->value,
            ],
        );
    }

    public function onInvoicePaid(InvoicePaidEvent $event): void
    {
        $this->recordHistory(
            invoice: $event->invoice,
            action: InvoiceHistoryAction::PAID,
            metadata: [
                'paid_at' => $event->paidAt->format(\DateTimeInterface::ATOM),
            ],
        );
    }

    public function onInvoicePartiallyPaid(InvoicePartiallyPaidEvent $event): void
    {
        $this->recordHistory(
            invoice: $event->invoice,
            action: InvoiceHistoryAction::PAYMENT_RECEIVED,
            metadata: [
                'amount_paid_cents' => $event->amountPaid->getAmount(),
                'remaining_cents' => $event->remainingAmount->getAmount(),
            ],
        );
    }

    public function onInvoiceOverdue(InvoiceOverdueEvent $event): void
    {
        $this->recordHistory(
            invoice: $event->invoice,
            action: InvoiceHistoryAction::STATUS_CHANGED,
            metadata: [
                'days_overdue' => $event->daysOverdue,
            ],
        );
    }

    public function onInvoiceCancelled(InvoiceCancelledEvent $event): void
    {
        $this->recordHistory(
            invoice: $event->invoice,
            action: InvoiceHistoryAction::CANCELLED,
            metadata: [
                'reason' => $event->reason,
            ],
        );
    }

    public function onCreditNoteCreated(CreditNoteCreatedEvent $event): void
    {
        $this->recordHistory(
            invoice: $event->creditNote,
            action: InvoiceHistoryAction::CREATED,
            metadata: ['type' => 'credit_note'],
        );
    }

    public function onInvoicePdfGenerated(InvoicePdfGeneratedEvent $event): void
    {
        $this->recordHistory(
            invoice: $event->invoice,
            action: InvoiceHistoryAction::FINALIZED,
            metadata: ['pdf_path' => $event->pdfPath],
        );
    }

    /**
     * Records an invoice history entry with current user information.
     *
     * @param array<string, mixed> $metadata
     */
    private function recordHistory(
        \CorentinBoutillier\InvoiceBundle\Entity\Invoice $invoice,
        InvoiceHistoryAction $action,
        array $metadata,
    ): void {
        $history = new InvoiceHistory(
            invoice: $invoice,
            action: $action,
            executedAt: new \DateTimeImmutable(),
        );

        // Add current user information if available
        $userData = $this->userProvider->getCurrentUser();
        if (null !== $userData) {
            $history->setUserId((int) $userData->id);

            // Add user info to metadata for readability
            $metadata['user'] = [
                'name' => $userData->name,
                'email' => $userData->email,
            ];
        }

        $history->setMetadata($metadata);

        $this->entityManager->persist($history);
        $this->entityManager->flush();
    }
}
