<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\Payment;
use CorentinBoutillier\InvoiceBundle\Enum\PaymentMethod;

/**
 * Gère l'enregistrement des paiements sur les factures.
 *
 * Ce service se charge de :
 * - Créer l'entité Payment
 * - Lier le paiement à la facture
 * - Mettre à jour le statut de la facture (PAID ou PARTIALLY_PAID)
 * - Dispatcher les événements appropriés (InvoicePaidEvent ou InvoicePartiallyPaidEvent)
 */
interface PaymentManagerInterface
{
    /**
     * Enregistre un paiement sur une facture.
     *
     * Le paiement est automatiquement lié à la facture et persiste en base de données.
     * Le statut de la facture est mis à jour selon le montant total payé :
     * - PAID si la facture est entièrement payée (ou surpayée)
     * - PARTIALLY_PAID si la facture est partiellement payée
     *
     * Les événements suivants sont dispatchés :
     * - InvoicePaidEvent : Quand la facture devient entièrement payée
     * - InvoicePartiallyPaidEvent : Pour chaque paiement partiel
     *
     * @param Invoice $invoice Facture sur laquelle enregistrer le paiement
     * @param Money $amount Montant du paiement
     * @param \DateTimeImmutable $paidAt Date du paiement
     * @param PaymentMethod $method Méthode de paiement
     * @param string|null $reference Référence du paiement (optionnel)
     * @param string|null $notes Notes sur le paiement (optionnel)
     *
     * @return Payment Le paiement créé et persisté
     *
     * @throws \InvalidArgumentException Si la facture a un statut DRAFT ou CANCELLED
     */
    public function recordPayment(
        Invoice $invoice,
        Money $amount,
        \DateTimeImmutable $paidAt,
        PaymentMethod $method,
        ?string $reference = null,
        ?string $notes = null,
    ): Payment;
}
