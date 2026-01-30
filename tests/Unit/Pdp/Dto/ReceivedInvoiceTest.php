<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Pdp\Dto;

use CorentinBoutillier\InvoiceBundle\Pdp\Dto\ReceivedInvoice;
use PHPUnit\Framework\TestCase;

final class ReceivedInvoiceTest extends TestCase
{
    public function testConstructWithMinimalData(): void
    {
        $invoiceDate = new \DateTimeImmutable('2025-01-15');
        $invoice = new ReceivedInvoice(
            transmissionId: 'TX-123',
            invoiceNumber: 'FA-2025-001',
            invoiceDate: $invoiceDate,
            supplierName: 'Supplier Co',
        );

        $this->assertSame('TX-123', $invoice->transmissionId);
        $this->assertSame('FA-2025-001', $invoice->invoiceNumber);
        $this->assertSame($invoiceDate, $invoice->invoiceDate);
        $this->assertSame('Supplier Co', $invoice->supplierName);
        $this->assertNull($invoice->supplierSiret);
        $this->assertNull($invoice->supplierVatNumber);
        $this->assertSame(0, $invoice->totalAmountCents);
        $this->assertSame(0, $invoice->vatAmountCents);
        $this->assertNull($invoice->pdfContent);
        $this->assertNull($invoice->xmlContent);
        $this->assertNull($invoice->receivedAt);
        $this->assertSame([], $invoice->metadata);
    }

    public function testConstructWithAllData(): void
    {
        $invoiceDate = new \DateTimeImmutable('2025-01-15');
        $receivedAt = new \DateTimeImmutable('2025-01-16');
        $invoice = new ReceivedInvoice(
            transmissionId: 'TX-456',
            invoiceNumber: 'FA-2025-002',
            invoiceDate: $invoiceDate,
            supplierName: 'Big Corp',
            supplierSiret: '12345678901234',
            supplierVatNumber: 'FR12345678901',
            totalAmountCents: 120000,
            vatAmountCents: 20000,
            pdfContent: 'PDF_BINARY_CONTENT',
            xmlContent: '<?xml version="1.0"?>',
            receivedAt: $receivedAt,
            metadata: ['source' => 'pennylane'],
        );

        $this->assertSame('TX-456', $invoice->transmissionId);
        $this->assertSame('FA-2025-002', $invoice->invoiceNumber);
        $this->assertSame('Big Corp', $invoice->supplierName);
        $this->assertSame('12345678901234', $invoice->supplierSiret);
        $this->assertSame('FR12345678901', $invoice->supplierVatNumber);
        $this->assertSame(120000, $invoice->totalAmountCents);
        $this->assertSame(20000, $invoice->vatAmountCents);
        $this->assertSame('PDF_BINARY_CONTENT', $invoice->pdfContent);
        $this->assertSame('<?xml version="1.0"?>', $invoice->xmlContent);
        $this->assertSame($receivedAt, $invoice->receivedAt);
        $this->assertSame(['source' => 'pennylane'], $invoice->metadata);
    }

    public function testGetTotalAmountEuros(): void
    {
        $invoice = new ReceivedInvoice(
            transmissionId: 'TX-123',
            invoiceNumber: 'FA-001',
            invoiceDate: new \DateTimeImmutable(),
            supplierName: 'Supplier',
            totalAmountCents: 123456,
        );

        $this->assertSame('1234.56', $invoice->getTotalAmountEuros());
    }

    public function testGetVatAmountEuros(): void
    {
        $invoice = new ReceivedInvoice(
            transmissionId: 'TX-123',
            invoiceNumber: 'FA-001',
            invoiceDate: new \DateTimeImmutable(),
            supplierName: 'Supplier',
            vatAmountCents: 20595,
        );

        $this->assertSame('205.95', $invoice->getVatAmountEuros());
    }

    public function testGetNetAmountCents(): void
    {
        $invoice = new ReceivedInvoice(
            transmissionId: 'TX-123',
            invoiceNumber: 'FA-001',
            invoiceDate: new \DateTimeImmutable(),
            supplierName: 'Supplier',
            totalAmountCents: 120000,
            vatAmountCents: 20000,
        );

        $this->assertSame(100000, $invoice->getNetAmountCents());
    }

    public function testHasPdf(): void
    {
        $withPdf = new ReceivedInvoice(
            transmissionId: 'TX-123',
            invoiceNumber: 'FA-001',
            invoiceDate: new \DateTimeImmutable(),
            supplierName: 'Supplier',
            pdfContent: 'PDF_CONTENT',
        );

        $withoutPdf = new ReceivedInvoice(
            transmissionId: 'TX-123',
            invoiceNumber: 'FA-001',
            invoiceDate: new \DateTimeImmutable(),
            supplierName: 'Supplier',
        );

        $withEmptyPdf = new ReceivedInvoice(
            transmissionId: 'TX-123',
            invoiceNumber: 'FA-001',
            invoiceDate: new \DateTimeImmutable(),
            supplierName: 'Supplier',
            pdfContent: '',
        );

        $this->assertTrue($withPdf->hasPdf());
        $this->assertFalse($withoutPdf->hasPdf());
        $this->assertFalse($withEmptyPdf->hasPdf());
    }

    public function testHasXml(): void
    {
        $withXml = new ReceivedInvoice(
            transmissionId: 'TX-123',
            invoiceNumber: 'FA-001',
            invoiceDate: new \DateTimeImmutable(),
            supplierName: 'Supplier',
            xmlContent: '<?xml version="1.0"?>',
        );

        $withoutXml = new ReceivedInvoice(
            transmissionId: 'TX-123',
            invoiceNumber: 'FA-001',
            invoiceDate: new \DateTimeImmutable(),
            supplierName: 'Supplier',
        );

        $withEmptyXml = new ReceivedInvoice(
            transmissionId: 'TX-123',
            invoiceNumber: 'FA-001',
            invoiceDate: new \DateTimeImmutable(),
            supplierName: 'Supplier',
            xmlContent: '',
        );

        $this->assertTrue($withXml->hasXml());
        $this->assertFalse($withoutXml->hasXml());
        $this->assertFalse($withEmptyXml->hasXml());
    }

    public function testClassIsReadonly(): void
    {
        $reflection = new \ReflectionClass(ReceivedInvoice::class);
        $this->assertTrue($reflection->isReadOnly());
    }
}
