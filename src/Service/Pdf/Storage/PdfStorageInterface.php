<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\Pdf\Storage;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Exception\StorageException;

/**
 * Interface pour le stockage des PDF de factures.
 */
interface PdfStorageInterface
{
    /**
     * Stocke un PDF pour une facture.
     *
     * @param Invoice $invoice    La facture pour laquelle stocker le PDF
     * @param string  $pdfContent Le contenu binaire du PDF
     *
     * @return string Le chemin relatif où le PDF a été stocké (ex: "2025/01/FA-2025-0001.pdf")
     *
     * @throws StorageException Si le stockage échoue
     */
    public function store(Invoice $invoice, string $pdfContent): string;

    /**
     * Récupère un PDF par son chemin relatif.
     *
     * @param string $path Le chemin relatif (ex: "2025/01/FA-2025-0001.pdf")
     *
     * @return string Le contenu binaire du PDF
     *
     * @throws StorageException Si le fichier n'existe pas ou ne peut être lu
     */
    public function retrieve(string $path): string;

    /**
     * Vérifie si un PDF existe.
     *
     * @param string $path Le chemin relatif (ex: "2025/01/FA-2025-0001.pdf")
     */
    public function exists(string $path): bool;

    /**
     * Supprime un PDF.
     *
     * @param string $path Le chemin relatif (ex: "2025/01/FA-2025-0001.pdf")
     *
     * @throws StorageException Si la suppression échoue
     */
    public function delete(string $path): void;
}
