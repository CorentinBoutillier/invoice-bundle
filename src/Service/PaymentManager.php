<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\Payment;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Enum\PaymentMethod;
use CorentinBoutillier\InvoiceBundle\Event\InvoicePaidEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoicePartiallyPaidEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Gère l'enregistrement des paiements sur les factures.
 */
final class PaymentManager implements PaymentManagerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function recordPayment(
        Invoice $invoice,
        Money $amount,
        \DateTimeImmutable $paidAt,
        PaymentMethod $method,
        ?string $reference = null,
        ?string $notes = null,
    ): Payment {
        // 1. Validate invoice status
        $this->validateInvoiceStatus($invoice);

        // 2. Create Payment entity
        $payment = new Payment($amount, $paidAt, $method);

        // 3. Set optional fields
        if (null !== $reference) {
            $payment->setReference($reference);
        }

        if (null !== $notes) {
            $payment->setNotes($notes);
        }

        // 4. Link payment to invoice
        $payment->setInvoice($invoice);
        $invoice->addPayment($payment);

        // 5. Persist payment (cascade will update invoice)
        $this->entityManager->persist($payment);

        // 6. Update invoice status and dispatch events
        $this->updateInvoiceStatusAndDispatchEvents($invoice, $amount, $paidAt);

        // 7. Flush all changes
        $this->entityManager->flush();

        // 8. Return created payment
        return $payment;
    }

    /**
     * Validate that the invoice can receive payments.
     *
     * @throws \InvalidArgumentException If invoice status is DRAFT or CANCELLED
     */
    private function validateInvoiceStatus(Invoice $invoice): void
    {
        $status = $invoice->getStatus();

        if (InvoiceStatus::DRAFT === $status) {
            throw new \InvalidArgumentException('Cannot record payment on invoice with status DRAFT');
        }

        if (InvoiceStatus::CANCELLED === $status) {
            throw new \InvalidArgumentException('Cannot record payment on invoice with status CANCELLED');
        }
    }

    /**
     * Update invoice status based on payment amounts and dispatch appropriate events.
     */
    private function updateInvoiceStatusAndDispatchEvents(
        Invoice $invoice,
        Money $paymentAmount,
        \DateTimeImmutable $paidAt,
    ): void {
        $previousStatus = $invoice->getStatus();

        if ($invoice->isFullyPaid()) {
            // Invoice is fully paid (or overpaid)
            $invoice->setStatus(InvoiceStatus::PAID);

            // Only dispatch InvoicePaidEvent if status actually changed to PAID
            if (InvoiceStatus::PAID !== $previousStatus) {
                $this->eventDispatcher->dispatch(
                    new InvoicePaidEvent(
                        invoice: $invoice,
                        paidAt: $paidAt,
                    ),
                );
            }
        } elseif ($invoice->isPartiallyPaid()) {
            // Invoice is partially paid
            $invoice->setStatus(InvoiceStatus::PARTIALLY_PAID);

            // Always dispatch InvoicePartiallyPaidEvent for partial payments
            // to track each payment individually
            $this->eventDispatcher->dispatch(
                new InvoicePartiallyPaidEvent(
                    invoice: $invoice,
                    amountPaid: $paymentAmount,
                    remainingAmount: $invoice->getRemainingAmount(),
                ),
            );
        }
        // If neither fully nor partially paid, status remains unchanged
    }
}
