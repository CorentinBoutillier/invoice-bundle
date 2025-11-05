<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\Pdf\Storage;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Exception\StorageException;

/**
 * Stockage des PDF sur le système de fichiers.
 *
 * Organisation : {basePath}/{ANNÉE}/{MOIS}/{numéro}.pdf
 * Exemple : var/invoices/2025/01/FA-2025-0001.pdf
 */
final class FilesystemPdfStorage implements PdfStorageInterface
{
    public function __construct(
        private readonly string $basePath,
    ) {
    }

    public function store(Invoice $invoice, string $pdfContent): string
    {
        // Extract year and month from invoice date
        $year = $invoice->getDate()->format('Y');
        $month = $invoice->getDate()->format('m');

        // Build directory path
        $directory = \sprintf('%s/%s/%s', $this->basePath, $year, $month);

        // Create directory if it doesn't exist
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new StorageException(\sprintf('Cannot create directory: %s', $directory));
            }
        }

        // Build file path
        $filename = $invoice->getNumber().'.pdf';
        $fullPath = \sprintf('%s/%s', $directory, $filename);

        // Write file with exclusive lock (atomic write)
        $handle = fopen($fullPath, 'c');
        if (false === $handle) {
            throw new StorageException(\sprintf('Cannot write PDF to: %s', $fullPath));
        }

        try {
            // Acquire exclusive lock
            if (!flock($handle, \LOCK_EX)) {
                throw new StorageException(\sprintf('Cannot acquire lock on: %s', $fullPath));
            }

            // Truncate file and write content
            if (false === ftruncate($handle, 0)) {
                throw new StorageException(\sprintf('Cannot truncate file: %s', $fullPath));
            }

            if (false === fwrite($handle, $pdfContent)) {
                throw new StorageException(\sprintf('Cannot write PDF to: %s', $fullPath));
            }

            // Lock is automatically released when handle is closed
        } finally {
            fclose($handle);
        }

        // Return relative path
        return \sprintf('%s/%s/%s', $year, $month, $filename);
    }

    public function retrieve(string $path): string
    {
        // Validate path to prevent directory traversal
        $this->validatePath($path);

        $fullPath = $this->basePath.'/'.$path;

        if (!file_exists($fullPath)) {
            throw new StorageException(\sprintf('PDF file not found: %s', $path));
        }

        $content = file_get_contents($fullPath);
        if (false === $content) {
            throw new StorageException(\sprintf('Cannot read PDF file: %s', $path));
        }

        return $content;
    }

    public function exists(string $path): bool
    {
        // Validate path to prevent directory traversal
        try {
            $this->validatePath($path);
        } catch (StorageException) {
            return false;
        }

        $fullPath = $this->basePath.'/'.$path;

        return file_exists($fullPath);
    }

    public function delete(string $path): void
    {
        // Validate path to prevent directory traversal
        $this->validatePath($path);

        $fullPath = $this->basePath.'/'.$path;

        if (!file_exists($fullPath)) {
            throw new StorageException(\sprintf('Cannot delete PDF file: file not found at %s', $path));
        }

        if (!unlink($fullPath)) {
            throw new StorageException(\sprintf('Cannot delete PDF file: %s', $path));
        }
    }

    /**
     * Valide le chemin pour éviter les attaques directory traversal.
     *
     * @throws StorageException Si le chemin contient des séquences dangereuses
     */
    private function validatePath(string $path): void
    {
        if (str_contains($path, '..')) {
            throw new StorageException(\sprintf('Invalid path: %s', $path));
        }
    }
}
