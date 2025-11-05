<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Functional\Service\Pdf\Storage;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Exception\StorageException;
use CorentinBoutillier\InvoiceBundle\Service\Pdf\Storage\FilesystemPdfStorage;
use CorentinBoutillier\InvoiceBundle\Tests\Functional\Repository\RepositoryTestCase;

final class FilesystemPdfStorageTest extends RepositoryTestCase
{
    private FilesystemPdfStorage $storage;
    private string $tempBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary directory for tests
        $this->tempBasePath = sys_get_temp_dir().'/invoice_bundle_test_'.uniqid();
        mkdir($this->tempBasePath, 0755, true);

        // Instantiate storage with temp path
        $this->storage = new FilesystemPdfStorage($this->tempBasePath);
    }

    protected function tearDown(): void
    {
        // Clean up temporary directory
        if (is_dir($this->tempBasePath)) {
            $this->recursiveRemoveDirectory($this->tempBasePath);
        }

        parent::tearDown();
    }

    // ========== Basic Storage Tests ==========

    public function testStoreCreatesPdfFile(): void
    {
        $invoice = $this->createTestInvoice('FA-2025-0001', new \DateTimeImmutable('2025-01-15'));
        $pdfContent = '%PDF-1.4 test content';

        $path = $this->storage->store($invoice, $pdfContent);

        $expectedPath = '2025/01/FA-2025-0001.pdf';
        $this->assertSame($expectedPath, $path);

        $fullPath = $this->tempBasePath.'/'.$path;
        $this->assertFileExists($fullPath);
    }

    public function testStoreReturnsRelativePath(): void
    {
        $invoice = $this->createTestInvoice('FA-2025-0042', new \DateTimeImmutable('2025-03-20'));
        $pdfContent = '%PDF-1.4 test';

        $path = $this->storage->store($invoice, $pdfContent);

        // Should be relative path, not absolute
        $this->assertStringStartsNotWith('/', $path);
        $this->assertSame('2025/03/FA-2025-0042.pdf', $path);
    }

    public function testStoreWritesCorrectContent(): void
    {
        $invoice = $this->createTestInvoice('FA-2025-0001', new \DateTimeImmutable('2025-01-15'));
        $pdfContent = '%PDF-1.4 UNIQUE_CONTENT_12345';

        $path = $this->storage->store($invoice, $pdfContent);

        $fullPath = $this->tempBasePath.'/'.$path;
        $storedContent = file_get_contents($fullPath);
        $this->assertSame($pdfContent, $storedContent);
    }

    public function testRetrieveReturnsPdfContent(): void
    {
        $invoice = $this->createTestInvoice('FA-2025-0001', new \DateTimeImmutable('2025-01-15'));
        $pdfContent = '%PDF-1.4 RETRIEVE_TEST';

        $path = $this->storage->store($invoice, $pdfContent);
        $retrievedContent = $this->storage->retrieve($path);

        $this->assertSame($pdfContent, $retrievedContent);
    }

    public function testExistsReturnsTrueForStoredFile(): void
    {
        $invoice = $this->createTestInvoice('FA-2025-0001', new \DateTimeImmutable('2025-01-15'));
        $pdfContent = '%PDF-1.4 test';

        $path = $this->storage->store($invoice, $pdfContent);

        $this->assertTrue($this->storage->exists($path));
    }

    public function testExistsReturnsFalseForNonExistentFile(): void
    {
        $this->assertFalse($this->storage->exists('2025/01/NON_EXISTENT.pdf'));
    }

    public function testDeleteRemovesFile(): void
    {
        $invoice = $this->createTestInvoice('FA-2025-0001', new \DateTimeImmutable('2025-01-15'));
        $pdfContent = '%PDF-1.4 test';

        $path = $this->storage->store($invoice, $pdfContent);
        $this->assertTrue($this->storage->exists($path));

        $this->storage->delete($path);

        $this->assertFalse($this->storage->exists($path));
    }

    // ========== Directory Organization Tests ==========

    public function testStoreCreatesYearMonthDirectory(): void
    {
        $invoice = $this->createTestInvoice('FA-2025-0001', new \DateTimeImmutable('2025-01-15'));
        $pdfContent = '%PDF-1.4 test';

        $this->storage->store($invoice, $pdfContent);

        $yearDir = $this->tempBasePath.'/2025';
        $monthDir = $yearDir.'/01';

        $this->assertDirectoryExists($yearDir);
        $this->assertDirectoryExists($monthDir);
    }

    public function testStoreUsesCorrectYearFromInvoiceDate(): void
    {
        $invoice2024 = $this->createTestInvoice('FA-2024-0099', new \DateTimeImmutable('2024-12-31'));
        $invoice2025 = $this->createTestInvoice('FA-2025-0001', new \DateTimeImmutable('2025-01-01'));

        $path2024 = $this->storage->store($invoice2024, '%PDF test');
        $path2025 = $this->storage->store($invoice2025, '%PDF test');

        $this->assertStringStartsWith('2024/', $path2024);
        $this->assertStringStartsWith('2025/', $path2025);
    }

    public function testStoreUsesCorrectMonthFromInvoiceDate(): void
    {
        $invoiceJan = $this->createTestInvoice('FA-2025-0001', new \DateTimeImmutable('2025-01-15'));
        $invoiceDec = $this->createTestInvoice('FA-2025-0099', new \DateTimeImmutable('2025-12-31'));

        $pathJan = $this->storage->store($invoiceJan, '%PDF test');
        $pathDec = $this->storage->store($invoiceDec, '%PDF test');

        $this->assertSame('2025/01/FA-2025-0001.pdf', $pathJan);
        $this->assertSame('2025/12/FA-2025-0099.pdf', $pathDec);
    }

    public function testStoreCreatesDirectoriesWithCorrectPermissions(): void
    {
        $invoice = $this->createTestInvoice('FA-2025-0001', new \DateTimeImmutable('2025-01-15'));

        $this->storage->store($invoice, '%PDF test');

        $monthDir = $this->tempBasePath.'/2025/01';
        $perms = fileperms($monthDir) & 0777;

        $this->assertSame(0755, $perms);
    }

    // ========== Error Handling Tests ==========

    public function testStoreThrowsExceptionWhenDirectoryCreationFails(): void
    {
        // Create a file where directory should be (to force mkdir failure)
        $yearPath = $this->tempBasePath.'/2025';
        file_put_contents($yearPath, 'blocking file');

        $invoice = $this->createTestInvoice('FA-2025-0001', new \DateTimeImmutable('2025-01-15'));

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Cannot create directory');

        $this->storage->store($invoice, '%PDF test');
    }

    public function testStoreThrowsExceptionWhenWriteFails(): void
    {
        // Create directory but make it read-only
        $dir = $this->tempBasePath.'/2025/01';
        mkdir($dir, 0755, true);
        chmod($dir, 0444); // Read-only

        $invoice = $this->createTestInvoice('FA-2025-0001', new \DateTimeImmutable('2025-01-15'));

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Cannot write PDF to');

        try {
            $this->storage->store($invoice, '%PDF test');
        } finally {
            // Restore permissions for cleanup
            chmod($dir, 0755);
        }
    }

    public function testRetrieveThrowsExceptionForNonExistentFile(): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('PDF file not found');

        $this->storage->retrieve('2025/01/NON_EXISTENT.pdf');
    }

    public function testDeleteThrowsExceptionForNonExistentFile(): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Cannot delete PDF file');

        $this->storage->delete('2025/01/NON_EXISTENT.pdf');
    }

    public function testRetrieveThrowsExceptionForDirectoryTraversal(): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Invalid path');

        $this->storage->retrieve('../../../etc/passwd');
    }

    public function testExistsReturnsFalseForDirectoryTraversal(): void
    {
        // exists() should validate path and return false for invalid paths
        $this->assertFalse($this->storage->exists('../../../etc/passwd'));
    }

    public function testDeleteThrowsExceptionForDirectoryTraversal(): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Invalid path');

        $this->storage->delete('../../../etc/passwd');
    }

    // ========== Edge Cases ==========

    public function testStoreOverwritesExistingFile(): void
    {
        $invoice = $this->createTestInvoice('FA-2025-0001', new \DateTimeImmutable('2025-01-15'));

        $path1 = $this->storage->store($invoice, '%PDF-1.4 ORIGINAL');
        $path2 = $this->storage->store($invoice, '%PDF-1.4 UPDATED');

        $this->assertSame($path1, $path2);
        $this->assertSame('%PDF-1.4 UPDATED', $this->storage->retrieve($path2));
    }

    public function testStoreHandlesLargePdfContent(): void
    {
        $invoice = $this->createTestInvoice('FA-2025-0001', new \DateTimeImmutable('2025-01-15'));
        // Simulate large PDF (1MB)
        $largePdfContent = str_repeat('%PDF test content ', 50000);

        $path = $this->storage->store($invoice, $largePdfContent);

        $this->assertTrue($this->storage->exists($path));
        $retrieved = $this->storage->retrieve($path);
        $this->assertSame(strlen($largePdfContent), strlen($retrieved));
    }

    public function testMultipleInvoicesSameMonth(): void
    {
        $invoice1 = $this->createTestInvoice('FA-2025-0001', new \DateTimeImmutable('2025-01-05'));
        $invoice2 = $this->createTestInvoice('FA-2025-0002', new \DateTimeImmutable('2025-01-15'));
        $invoice3 = $this->createTestInvoice('FA-2025-0003', new \DateTimeImmutable('2025-01-25'));

        $path1 = $this->storage->store($invoice1, '%PDF 1');
        $path2 = $this->storage->store($invoice2, '%PDF 2');
        $path3 = $this->storage->store($invoice3, '%PDF 3');

        // All should be in same directory
        $this->assertStringStartsWith('2025/01/', $path1);
        $this->assertStringStartsWith('2025/01/', $path2);
        $this->assertStringStartsWith('2025/01/', $path3);

        // All should exist
        $this->assertTrue($this->storage->exists($path1));
        $this->assertTrue($this->storage->exists($path2));
        $this->assertTrue($this->storage->exists($path3));

        // Content should be different
        $this->assertSame('%PDF 1', $this->storage->retrieve($path1));
        $this->assertSame('%PDF 2', $this->storage->retrieve($path2));
        $this->assertSame('%PDF 3', $this->storage->retrieve($path3));
    }

    // ========== Helper Methods ==========

    private function createTestInvoice(string $number, \DateTimeImmutable $date): Invoice
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: $date,
            dueDate: $date->modify('+30 days'),
            customerName: 'Test Customer',
            customerAddress: '123 Test Street',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $invoice->setNumber($number);
        $invoice->setStatus(InvoiceStatus::FINALIZED);
        $invoice->setCompanyId(1);

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $invoice;
    }

    private function recursiveRemoveDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = array_diff(scandir($directory) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $path = $directory.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->recursiveRemoveDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($directory);
    }
}
