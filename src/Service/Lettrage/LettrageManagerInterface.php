<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\Lettrage;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\Lettrage;
use CorentinBoutillier\InvoiceBundle\Entity\Payment;

/**
 * Interface pour la gestion des lettrages comptables.
 *
 * Le lettrage est le rapprochement entre des factures (débit) et des paiements (crédit).
 * Un lettrage est équilibré quand la somme des débits égale la somme des crédits.
 */
interface LettrageManagerInterface
{
    /**
     * Crée un lettrage entre des factures et des paiements.
     *
     * Le lettrage doit être équilibré : la somme des montants des factures
     * doit être égale à la somme des montants des paiements.
     *
     * @param array<Invoice>       $invoices      Factures à lettrer (au débit)
     * @param array<Payment>       $payments      Paiements à lettrer (au crédit)
     * @param array<int, int>|null $invoiceAmounts Montants à lettrer par facture (cents) - null = montant total
     * @param array<int, int>|null $paymentAmounts Montants à lettrer par paiement (cents) - null = montant total
     *
     * @return Lettrage Le lettrage créé
     *
     * @throws \InvalidArgumentException Si le lettrage n'est pas équilibré
     * @throws \InvalidArgumentException Si aucune facture ou paiement fourni
     */
    public function createLettrage(
        array $invoices,
        array $payments,
        ?array $invoiceAmounts = null,
        ?array $paymentAmounts = null,
    ): Lettrage;

    /**
     * Supprime un lettrage (soft delete).
     *
     * Le lettrage n'est pas physiquement supprimé pour préserver l'audit trail.
     *
     * @param Lettrage    $lettrage Le lettrage à supprimer
     * @param string|null $reason   Raison de la suppression (optionnel)
     */
    public function deleteLettrage(Lettrage $lettrage, ?string $reason = null): void;

    /**
     * Crée un lettrage simple entre une facture et un paiement.
     *
     * Méthode de convenance pour le cas le plus courant :
     * une facture payée intégralement par un seul paiement.
     *
     * @param Invoice $invoice Facture à lettrer
     * @param Payment $payment Paiement à lettrer
     *
     * @return Lettrage Le lettrage créé
     *
     * @throws \InvalidArgumentException Si les montants ne correspondent pas
     */
    public function createSimpleLettrage(Invoice $invoice, Payment $payment): Lettrage;
}
