<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Functional\Service;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;
use CorentinBoutillier\InvoiceBundle\Entity\Lettrage;
use CorentinBoutillier\InvoiceBundle\Entity\Payment;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Enum\LettrageEntryType;
use CorentinBoutillier\InvoiceBundle\Enum\PaymentMethod;
use CorentinBoutillier\InvoiceBundle\Exception\LettrageNotBalancedException;
use CorentinBoutillier\InvoiceBundle\Service\Lettrage\LettrageManagerInterface;
use CorentinBoutillier\InvoiceBundle\Tests\Functional\Repository\RepositoryTestCase;

/**
 * Tests fonctionnels pour LettrageManager.
 */
final class LettrageManagerTest extends RepositoryTestCase
{
    /** @phpstan-ignore property.uninitialized */
    private LettrageManagerInterface $lettrageManager;

    protected function setUp(): void
    {
        parent::setUp();

        $container = $this->kernel->getContainer();

        $lettrageManager = $container->get(LettrageManagerInterface::class);
        if (!$lettrageManager instanceof LettrageManagerInterface) {
            throw new \RuntimeException('LettrageManagerInterface not found');
        }
        $this->lettrageManager = $lettrageManager;
    }

    // ========== createLettrage() ==========

    public function testCreateLettrageWithOneInvoiceAndOnePayment(): void
    {
        $invoice = $this->createInvoice(companyId: 1, totalTtc: 12000);
        $payment = $this->createPayment($invoice, 12000);

        $lettrage = $this->lettrageManager->createLettrage(
            invoices: [$invoice],
            payments: [$payment],
        );

        $this->assertInstanceOf(Lettrage::class, $lettrage);
        $this->assertNotNull($lettrage->getId());
        $this->assertSame('A', $lettrage->getCode());
        $this->assertSame(1, $lettrage->getCompanyId());
        $this->assertTrue($lettrage->isBalanced());
    }

    public function testCreateLettrageGeneratesSequentialCodes(): void
    {
        $invoice1 = $this->createInvoice(companyId: 1, totalTtc: 10000);
        $payment1 = $this->createPayment($invoice1, 10000);

        $invoice2 = $this->createInvoice(companyId: 1, totalTtc: 20000);
        $payment2 = $this->createPayment($invoice2, 20000);

        $invoice3 = $this->createInvoice(companyId: 1, totalTtc: 30000);
        $payment3 = $this->createPayment($invoice3, 30000);

        $lettrage1 = $this->lettrageManager->createLettrage([$invoice1], [$payment1]);
        $lettrage2 = $this->lettrageManager->createLettrage([$invoice2], [$payment2]);
        $lettrage3 = $this->lettrageManager->createLettrage([$invoice3], [$payment3]);

        $this->assertSame('A', $lettrage1->getCode());
        $this->assertSame('B', $lettrage2->getCode());
        $this->assertSame('C', $lettrage3->getCode());
    }

    public function testCreateLettrageWithMultipleInvoicesOnePayment(): void
    {
        // Deux factures payées par un seul virement
        $invoice1 = $this->createInvoice(companyId: 1, totalTtc: 5000);
        $invoice2 = $this->createInvoice(companyId: 1, totalTtc: 7000);
        $payment = $this->createPayment($invoice1, 12000); // Total des deux factures

        $lettrage = $this->lettrageManager->createLettrage(
            invoices: [$invoice1, $invoice2],
            payments: [$payment],
        );

        $this->assertTrue($lettrage->isBalanced());
        $this->assertCount(3, $lettrage->getEntries());
        $this->assertSame(12000, $lettrage->getTotalDebitCents());
        $this->assertSame(12000, $lettrage->getTotalCreditCents());
    }

    public function testCreateLettrageWithPartialAmounts(): void
    {
        // Facture de 200€ avec paiement partiel de 75€
        $invoice = $this->createInvoice(companyId: 1, totalTtc: 20000);
        $payment = $this->createPayment($invoice, 7500);

        $lettrage = $this->lettrageManager->createLettrage(
            invoices: [$invoice],
            payments: [$payment],
            invoiceAmounts: [0 => 7500], // Lettrer seulement 75€ de la facture
            paymentAmounts: [0 => 7500],
        );

        $this->assertTrue($lettrage->isBalanced());
        $this->assertSame(7500, $lettrage->getTotalDebitCents());
        $this->assertSame(7500, $lettrage->getTotalCreditCents());
    }

    public function testCreateLettrageThrowsExceptionWhenNotBalanced(): void
    {
        $invoice = $this->createInvoice(companyId: 1, totalTtc: 15000);
        $payment = $this->createPayment($invoice, 10000);

        $this->expectException(LettrageNotBalancedException::class);

        $this->lettrageManager->createLettrage(
            invoices: [$invoice],
            payments: [$payment],
        );
    }

    public function testCreateLettrageThrowsExceptionWithNoInvoices(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one invoice is required');

        $invoice = $this->createInvoice(companyId: 1, totalTtc: 10000);
        $payment = $this->createPayment($invoice, 10000);

        $this->lettrageManager->createLettrage(
            invoices: [],
            payments: [$payment],
        );
    }

    public function testCreateLettrageThrowsExceptionWithNoPayments(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one payment is required');

        $invoice = $this->createInvoice(companyId: 1, totalTtc: 10000);

        $this->lettrageManager->createLettrage(
            invoices: [$invoice],
            payments: [],
        );
    }

    public function testCreateLettrageCreatesCorrectEntries(): void
    {
        $invoice = $this->createInvoice(companyId: 1, totalTtc: 10000);
        $payment = $this->createPayment($invoice, 10000);

        $lettrage = $this->lettrageManager->createLettrage(
            invoices: [$invoice],
            payments: [$payment],
        );

        $entries = $lettrage->getEntries()->toArray();
        $this->assertCount(2, $entries);

        // Vérifier l'entrée débit (facture)
        $debitEntry = $entries[0];
        $this->assertSame(LettrageEntryType::DEBIT, $debitEntry->getType());
        $this->assertSame($invoice, $debitEntry->getInvoice());
        $this->assertNull($debitEntry->getPayment());
        $this->assertSame(10000, $debitEntry->getAmountCents());

        // Vérifier l'entrée crédit (paiement)
        $creditEntry = $entries[1];
        $this->assertSame(LettrageEntryType::CREDIT, $creditEntry->getType());
        $this->assertNull($creditEntry->getInvoice());
        $this->assertSame($payment, $creditEntry->getPayment());
        $this->assertSame(10000, $creditEntry->getAmountCents());
    }

    // ========== deleteLettrage() ==========

    public function testDeleteLettragePerformsSoftDelete(): void
    {
        $invoice = $this->createInvoice(companyId: 1, totalTtc: 10000);
        $payment = $this->createPayment($invoice, 10000);
        $lettrage = $this->lettrageManager->createLettrage([$invoice], [$payment]);

        $this->assertFalse($lettrage->isDeleted());

        $this->lettrageManager->deleteLettrage($lettrage, 'Erreur de saisie');

        $this->assertTrue($lettrage->isDeleted());
        $this->assertNotNull($lettrage->getDeletedAt());
        $this->assertSame('Erreur de saisie', $lettrage->getDeletionReason());
    }

    public function testDeleteLettragePreservesInDatabase(): void
    {
        $invoice = $this->createInvoice(companyId: 1, totalTtc: 10000);
        $payment = $this->createPayment($invoice, 10000);
        $lettrage = $this->lettrageManager->createLettrage([$invoice], [$payment]);
        $lettrageId = $lettrage->getId();

        $this->lettrageManager->deleteLettrage($lettrage);

        // Vérifier que le lettrage est toujours en base
        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(Lettrage::class, $lettrageId);
        $this->assertNotNull($reloaded);
        $this->assertTrue($reloaded->isDeleted());
    }

    // ========== createSimpleLettrage() ==========

    public function testCreateSimpleLettrage(): void
    {
        $invoice = $this->createInvoice(companyId: 1, totalTtc: 15000);
        $payment = $this->createPayment($invoice, 15000);

        $lettrage = $this->lettrageManager->createSimpleLettrage($invoice, $payment);

        $this->assertTrue($lettrage->isBalanced());
        $this->assertCount(2, $lettrage->getEntries());
    }

    public function testCreateSimpleLettrageThrowsExceptionWhenAmountsDiffer(): void
    {
        $invoice = $this->createInvoice(companyId: 1, totalTtc: 15000);
        $payment = $this->createPayment($invoice, 10000);

        $this->expectException(LettrageNotBalancedException::class);

        $this->lettrageManager->createSimpleLettrage($invoice, $payment);
    }

    // ========== Invoice.getLettrageCode() ==========

    public function testInvoiceGetLettrageCodeReturnsCode(): void
    {
        $invoice = $this->createInvoice(companyId: 1, totalTtc: 10000);
        $payment = $this->createPayment($invoice, 10000);

        $this->assertNull($invoice->getLettrageCode());

        $this->lettrageManager->createLettrage([$invoice], [$payment]);

        // Rafraîchir l'entité
        $this->entityManager->refresh($invoice);

        $this->assertSame('A', $invoice->getLettrageCode());
    }

    public function testInvoiceGetLettrageCodeIgnoresDeletedLettrages(): void
    {
        $invoice = $this->createInvoice(companyId: 1, totalTtc: 10000);
        $payment = $this->createPayment($invoice, 10000);

        $lettrage = $this->lettrageManager->createLettrage([$invoice], [$payment]);
        $this->entityManager->refresh($invoice);
        $this->assertSame('A', $invoice->getLettrageCode());

        $this->lettrageManager->deleteLettrage($lettrage);
        $this->entityManager->refresh($invoice);

        $this->assertNull($invoice->getLettrageCode());
    }

    // ========== Sequence isolation by company ==========

    public function testSequencesAreIsolatedByCompany(): void
    {
        // Entreprise 1
        $invoice1 = $this->createInvoice(companyId: 1, totalTtc: 10000);
        $payment1 = $this->createPayment($invoice1, 10000);
        $lettrage1 = $this->lettrageManager->createLettrage([$invoice1], [$payment1]);

        // Entreprise 2
        $invoice2 = $this->createInvoice(companyId: 2, totalTtc: 10000);
        $payment2 = $this->createPayment($invoice2, 10000);
        $lettrage2 = $this->lettrageManager->createLettrage([$invoice2], [$payment2]);

        // Chaque entreprise a son propre code "A"
        $this->assertSame('A', $lettrage1->getCode());
        $this->assertSame('A', $lettrage2->getCode());
    }

    // ========== Helper Methods ==========

    /**
     * Crée une facture finalisée avec un montant TTC spécifié (en centimes).
     */
    private function createInvoice(?int $companyId, int $totalTtc): Invoice
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Test Customer',
            customerAddress: '123 Test Street',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $invoice->setStatus(InvoiceStatus::FINALIZED);
        $invoice->setNumber('FA-2025-'.uniqid());
        $invoice->setCompanyId($companyId);

        // Calculer le montant HT pour obtenir le TTC souhaité (TVA 20%)
        $vatRate = 20.0;
        $amountHt = (int) round($totalTtc / (1 + $vatRate / 100));

        $line = new InvoiceLine(
            description: 'Test Service',
            quantity: 1,
            unitPrice: Money::fromCents($amountHt),
            vatRate: $vatRate,
        );

        $invoice->addLine($line);

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $invoice;
    }

    /**
     * Crée un paiement lié à une facture.
     */
    private function createPayment(Invoice $invoice, int $amountCents): Payment
    {
        $payment = new Payment(
            amount: Money::fromCents($amountCents),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $payment->setInvoice($invoice);
        $invoice->addPayment($payment);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $payment;
    }
}
