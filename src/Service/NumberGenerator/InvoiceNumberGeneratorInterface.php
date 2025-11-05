<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\NumberGenerator;

use CorentinBoutillier\InvoiceBundle\DTO\CompanyData;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;

/**
 * Génère des numéros de facture uniques avec séquence thread-safe.
 *
 * Ce service gère la numérotation séquentielle des factures et avoirs
 * avec support des exercices comptables non-calendaires et multi-société.
 */
interface InvoiceNumberGeneratorInterface
{
    /**
     * Génère un numéro de facture unique avec incrémentation thread-safe de la séquence.
     *
     * IMPORTANT : Cette méthode DOIT être appelée dans une transaction Doctrine active.
     * Elle utilise un verrou PESSIMISTIC_WRITE sur l'entité InvoiceSequence pour
     * garantir l'unicité des numéros en cas d'accès concurrent.
     *
     * Format généré :
     * - Factures : FA-{YEAR}-{SEQUENCE} (ex: FA-2025-0001)
     * - Avoirs : AV-{YEAR}-{SEQUENCE} (ex: AV-2025-0042)
     *
     * Le service calcule automatiquement l'année fiscale depuis la date de facture
     * et la configuration de l'exercice comptable dans CompanyData.
     *
     * Si la séquence n'existe pas pour l'exercice/société/type donnés, elle est
     * créée automatiquement avec lastNumber = 0.
     *
     * @param Invoice $invoice Entité facture (doit avoir date, type, companyId)
     * @param CompanyData $company Données société (config exercice comptable)
     *
     * @return string Numéro de facture formaté (ex: "FA-2025-0042")
     *
     * @throws \RuntimeException Si la séquence ne peut être verrouillée ou créée
     * @throws \InvalidArgumentException Si les données sont invalides
     */
    public function generate(Invoice $invoice, CompanyData $company): string;
}
