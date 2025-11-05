<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\Pdf;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;

/**
 * Génère un PDF à partir d'une facture.
 */
interface PdfGeneratorInterface
{
    /**
     * Génère un PDF pour une facture.
     *
     * @param Invoice $invoice Facture à convertir en PDF
     *
     * @return string Contenu binaire du PDF généré
     */
    public function generate(Invoice $invoice): string;
}
