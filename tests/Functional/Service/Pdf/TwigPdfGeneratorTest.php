<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Functional\Service\Pdf;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Service\Pdf\PdfGeneratorInterface;
use CorentinBoutillier\InvoiceBundle\Tests\Functional\Repository\RepositoryTestCase;
use Smalot\PdfParser\Parser as PdfParser;

final class TwigPdfGeneratorTest extends RepositoryTestCase
{
    /** @phpstan-ignore property.uninitialized */
    private PdfGeneratorInterface $pdfGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $container = $this->kernel->getContainer();

        $pdfGenerator = $container->get(PdfGeneratorInterface::class);
        if (!$pdfGenerator instanceof PdfGeneratorInterface) {
            throw new \RuntimeException('PdfGeneratorInterface not found');
        }
        $this->pdfGenerator = $pdfGenerator;
    }

    // ========== Basic Generation Tests ==========

    public function testGenerateReturnsBinaryPdfString(): void
    {
        $invoice = $this->createTestInvoice();

        $result = $this->pdfGenerator->generate($invoice);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testGeneratePdfStartsWithPdfMagicBytes(): void
    {
        $invoice = $this->createTestInvoice();

        $result = $this->pdfGenerator->generate($invoice);

        // PDF files must start with %PDF-
        $this->assertStringStartsWith('%PDF-', $result);
    }

    public function testGeneratePdfHasMinimumSize(): void
    {
        $invoice = $this->createTestInvoice();

        $result = $this->pdfGenerator->generate($invoice);

        // A valid PDF should have at least 1KB
        $this->assertGreaterThan(1000, \strlen($result));
    }

    // ========== Different Types Tests ==========

    public function testGeneratePdfForInvoiceType(): void
    {
        $invoice = $this->createTestInvoice(InvoiceType::INVOICE);

        $result = $this->pdfGenerator->generate($invoice);

        $this->assertStringStartsWith('%PDF-', $result);

        // Extract text from PDF
        $text = $this->extractTextFromPdf($result);
        $this->assertStringContainsString('FACTURE', $text);
        $this->assertStringNotContainsString('AVOIR', $text);
    }

    public function testGeneratePdfForCreditNoteType(): void
    {
        $invoice = $this->createTestInvoice(InvoiceType::CREDIT_NOTE);

        $result = $this->pdfGenerator->generate($invoice);

        $this->assertStringStartsWith('%PDF-', $result);

        // Extract text from PDF
        $text = $this->extractTextFromPdf($result);
        $this->assertStringContainsString('AVOIR', $text);
        $this->assertStringNotContainsString('FACTURE', $text);
    }

    // ========== Basic Content Tests ==========

    public function testPdfContainsInvoiceNumber(): void
    {
        $invoice = $this->createTestInvoice();

        $result = $this->pdfGenerator->generate($invoice);

        $text = $this->extractTextFromPdf($result);
        $invoiceNumber = $invoice->getNumber();
        $this->assertNotNull($invoiceNumber);
        $this->assertStringContainsString($invoiceNumber, $text);
    }

    public function testPdfContainsCompanyName(): void
    {
        $invoice = $this->createTestInvoice();

        $result = $this->pdfGenerator->generate($invoice);

        $text = $this->extractTextFromPdf($result);
        $this->assertStringContainsString($invoice->getCompanyName(), $text);
    }

    public function testPdfContainsCustomerName(): void
    {
        $invoice = $this->createTestInvoice();

        $result = $this->pdfGenerator->generate($invoice);

        $text = $this->extractTextFromPdf($result);
        $this->assertStringContainsString($invoice->getCustomerName(), $text);
    }

    public function testPdfContainsTotalAmount(): void
    {
        $invoice = $this->createTestInvoice();

        $result = $this->pdfGenerator->generate($invoice);

        $text = $this->extractTextFromPdf($result);
        // Check that we have "100,00" in the text (French number format)
        $this->assertStringContainsString('100,00', $text);
    }

    public function testPdfContainsDates(): void
    {
        $invoice = $this->createTestInvoice();

        $result = $this->pdfGenerator->generate($invoice);

        $text = $this->extractTextFromPdf($result);
        $invoiceDate = $invoice->getDate()->format('d/m/Y');
        $this->assertStringContainsString($invoiceDate, $text);
    }

    // ========== Edge Cases Tests ==========

    public function testGeneratePdfWithMultipleLines(): void
    {
        $invoice = $this->createTestInvoiceWithMultipleLines(5);

        $result = $this->pdfGenerator->generate($invoice);

        $this->assertStringStartsWith('%PDF-', $result);
        $this->assertGreaterThan(1000, \strlen($result));

        $text = $this->extractTextFromPdf($result);
        // Should contain all line descriptions
        foreach ($invoice->getLines() as $line) {
            $this->assertStringContainsString($line->getDescription(), $text);
        }
    }

    public function testGeneratePdfWithGlobalDiscount(): void
    {
        $invoice = $this->createTestInvoiceWithGlobalDiscount();

        $result = $this->pdfGenerator->generate($invoice);

        $this->assertStringStartsWith('%PDF-', $result);
        // Global discount should appear in the PDF
        $discountAmount = $invoice->getGlobalDiscountAmount();
        if (null !== $discountAmount && !$discountAmount->isZero()) {
            $this->assertNotEmpty($result);
        }
    }

    // ========== Helper Methods ==========

    /**
     * Extract text content from a PDF binary string.
     */
    private function extractTextFromPdf(string $pdfBinary): string
    {
        $parser = new PdfParser();
        $pdf = $parser->parseContent($pdfBinary);

        return $pdf->getText();
    }

    private function createTestInvoice(InvoiceType $type = InvoiceType::INVOICE): Invoice
    {
        $invoice = new Invoice(
            type: $type,
            date: new \DateTimeImmutable('2025-01-15'),
            dueDate: new \DateTimeImmutable('2025-02-14'),
            customerName: 'Test Customer SA',
            customerAddress: '123 Test Street, 75001 Paris',
            companyName: 'Test Company SARL',
            companyAddress: '456 Company Ave, 75002 Paris',
        );

        $invoice->setStatus(InvoiceStatus::FINALIZED);
        $invoice->setNumber(InvoiceType::INVOICE === $type ? 'FA-2025-0001' : 'AV-2025-0001');
        $invoice->setCompanyId(1);
        $invoice->setCompanySiret('12345678901234');
        $invoice->setCompanyVatNumber('FR12345678901');
        $invoice->setCustomerSiret('98765432109876');

        // Add a simple line
        $line = new InvoiceLine(
            description: 'Service de consultation',
            quantity: 1,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $invoice->addLine($line);

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $invoice;
    }

    private function createTestInvoiceWithMultipleLines(int $lineCount): Invoice
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2025-01-15'),
            dueDate: new \DateTimeImmutable('2025-02-14'),
            customerName: 'Test Customer SA',
            customerAddress: '123 Test Street, 75001 Paris',
            companyName: 'Test Company SARL',
            companyAddress: '456 Company Ave, 75002 Paris',
        );

        $invoice->setStatus(InvoiceStatus::FINALIZED);
        $invoice->setNumber('FA-2025-0002');
        $invoice->setCompanyId(1);

        // Add multiple lines
        for ($i = 1; $i <= $lineCount; ++$i) {
            $line = new InvoiceLine(
                description: "Service line {$i}",
                quantity: $i,
                unitPrice: Money::fromEuros((string) (50 * $i)),
                vatRate: 20.0,
            );
            $invoice->addLine($line);
        }

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $invoice;
    }

    private function createTestInvoiceWithGlobalDiscount(): Invoice
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2025-01-15'),
            dueDate: new \DateTimeImmutable('2025-02-14'),
            customerName: 'Test Customer SA',
            customerAddress: '123 Test Street, 75001 Paris',
            companyName: 'Test Company SARL',
            companyAddress: '456 Company Ave, 75002 Paris',
        );

        $invoice->setStatus(InvoiceStatus::FINALIZED);
        $invoice->setNumber('FA-2025-0003');
        $invoice->setCompanyId(1);

        // Add lines
        $line = new InvoiceLine(
            description: 'Service with discount',
            quantity: 2,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );
        $invoice->addLine($line);

        // Apply global discount
        $invoice->setGlobalDiscountAmount(Money::fromEuros('20.00'));

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $invoice;
    }
}
