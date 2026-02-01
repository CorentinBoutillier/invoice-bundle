<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Enum;

/**
 * Type d'entrée dans un lettrage comptable.
 *
 * Utilisé pour distinguer les écritures au débit (factures) des écritures
 * au crédit (paiements) dans un lettrage.
 */
enum LettrageEntryType: string
{
    /**
     * Écriture au débit (typiquement une facture à recevoir).
     */
    case DEBIT = 'debit';

    /**
     * Écriture au crédit (typiquement un paiement reçu).
     */
    case CREDIT = 'credit';
}
