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
use CorentinBoutillier\InvoiceBundle\Service\Lettrage\LettrageManagerInterface;
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
        private readonly ?LettrageManagerInterface $lettrageManager = null,
    ) {
    }

    public function recordPayment(
        Invoice $invoice,
        Money $amount,
        \DateTimeImmutable $paidAt,
        PaymentMethod $method,
        ?string $reference = null,
        ?string $notes = null,
        bool $autoLettrage = true,
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

        // 8. Create lettrage if payment completes the invoice and auto-lettrage is enabled
        if ($autoLettrage && null !== $this->lettrageManager && $invoice->isFullyPaid()) {
            $this->createLettrageForFullPayment($invoice);
        }

        // 9. Return created payment
        return $payment;
    }

    /**
     * Crée un lettrage pour une facture entièrement payée.
     *
     * Regroupe tous les paiements de la facture dans un seul lettrage.
     */
    private function createLettrageForFullPayment(Invoice $invoice): void
    {
        // Cette méthode n'est appelée que si lettrageManager n'est pas null
        // (vérifié dans recordPayment). On l'asserte pour PHPStan.
        \assert(null !== $this->lettrageManager);

        $payments = $invoice->getPayments();
        if ([] === $payments) {
            return;
        }

        // Vérifier que la facture n'est pas déjà lettrée
        if (null !== $invoice->getLettrageCode()) {
            return;
        }

        // Calculer les montants à lettrer pour chaque paiement
        // On lettre le montant total de la facture réparti sur les paiements
        $totalInvoice = $invoice->getTotalIncludingVat()->getAmount();
        $totalPaid = 0;
        $paymentAmounts = [];

        foreach ($payments as $index => $payment) {
            $paymentAmount = $payment->getAmount()->getAmount();
            $totalPaid += $paymentAmount;
            $paymentAmounts[$index] = $paymentAmount;
        }

        // Ajuster si trop payé (on ne lettre que le montant de la facture)
        if ($totalPaid > $totalInvoice) {
            // Réduire proportionnellement
            $ratio = $totalInvoice / $totalPaid;
            $adjustedTotal = 0;
            foreach ($paymentAmounts as $index => $amount) {
                if ($index === array_key_last($paymentAmounts)) {
                    // Dernier paiement : ajuster pour avoir exactement le total facture
                    $paymentAmounts[$index] = $totalInvoice - $adjustedTotal;
                } else {
                    $paymentAmounts[$index] = (int) round($amount * $ratio);
                    $adjustedTotal += $paymentAmounts[$index];
                }
            }
        }

        try {
            $this->lettrageManager->createLettrage(
                invoices: [$invoice],
                payments: $payments,
                invoiceAmounts: [0 => $totalInvoice],
                paymentAmounts: $paymentAmounts,
            );
        } catch (\Exception) {
            // En cas d'erreur de lettrage, on ne bloque pas le paiement
            // Le lettrage pourra être fait manuellement ultérieurement
        }
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
