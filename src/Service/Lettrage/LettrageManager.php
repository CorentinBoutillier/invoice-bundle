<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\Lettrage;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\Lettrage;
use CorentinBoutillier\InvoiceBundle\Entity\LettrageEntry;
use CorentinBoutillier\InvoiceBundle\Entity\Payment;
use CorentinBoutillier\InvoiceBundle\Enum\LettrageEntryType;
use CorentinBoutillier\InvoiceBundle\Exception\LettrageNotBalancedException;
use CorentinBoutillier\InvoiceBundle\Repository\LettrageSequenceRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de gestion des lettrages comptables.
 */
final class LettrageManager implements LettrageManagerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LettrageSequenceRepository $sequenceRepository,
        private readonly LettrageCodeGeneratorInterface $codeGenerator,
    ) {
    }

    public function createLettrage(
        array $invoices,
        array $payments,
        ?array $invoiceAmounts = null,
        ?array $paymentAmounts = null,
    ): Lettrage {
        // Validation
        if (0 === \count($invoices)) {
            throw new \InvalidArgumentException('At least one invoice is required');
        }

        if (0 === \count($payments)) {
            throw new \InvalidArgumentException('At least one payment is required');
        }

        // Déterminer le companyId depuis la première facture
        $companyId = $invoices[0]->getCompanyId();

        $this->entityManager->beginTransaction();
        try {
            // 1. Récupérer et verrouiller la séquence
            $sequence = $this->sequenceRepository->findOrCreate($companyId);
            $this->entityManager->flush();

            // Re-récupérer avec lock
            $sequence = $this->sequenceRepository->findForUpdate($companyId);
            if (null === $sequence) {
                throw new \RuntimeException('Failed to lock lettrage sequence');
            }

            // 2. Générer le code
            $code = $this->codeGenerator->generate($sequence);
            $sequence->incrementLastCode();

            // 3. Créer le lettrage
            $lettrage = new Lettrage(
                code: $code,
                date: new \DateTimeImmutable(),
                companyId: $companyId,
            );

            // 4. Créer les entrées pour les factures (débit)
            foreach ($invoices as $index => $invoice) {
                $amount = null !== $invoiceAmounts && isset($invoiceAmounts[$index])
                    ? $invoiceAmounts[$index]
                    : $invoice->getTotalIncludingVat()->getAmount();

                $entry = new LettrageEntry(
                    lettrage: $lettrage,
                    invoice: $invoice,
                    payment: null,
                    amountCents: $amount,
                    type: LettrageEntryType::DEBIT,
                );
                $lettrage->addEntry($entry);
            }

            // 5. Créer les entrées pour les paiements (crédit)
            foreach ($payments as $index => $payment) {
                $amount = null !== $paymentAmounts && isset($paymentAmounts[$index])
                    ? $paymentAmounts[$index]
                    : $payment->getAmount()->getAmount();

                $entry = new LettrageEntry(
                    lettrage: $lettrage,
                    invoice: null,
                    payment: $payment,
                    amountCents: $amount,
                    type: LettrageEntryType::CREDIT,
                );
                $lettrage->addEntry($entry);
            }

            // 6. Vérifier l'équilibre
            if (!$lettrage->isBalanced()) {
                throw new LettrageNotBalancedException(
                    $lettrage->getTotalDebitCents(),
                    $lettrage->getTotalCreditCents(),
                );
            }

            // 7. Persister
            $this->entityManager->persist($lettrage);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $lettrage;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function deleteLettrage(Lettrage $lettrage, ?string $reason = null): void
    {
        $lettrage->softDelete(new \DateTimeImmutable(), $reason);
        $this->entityManager->flush();
    }

    public function createSimpleLettrage(Invoice $invoice, Payment $payment): Lettrage
    {
        return $this->createLettrage(
            invoices: [$invoice],
            payments: [$payment],
        );
    }
}
