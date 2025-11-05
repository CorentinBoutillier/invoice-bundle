<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service;

use CorentinBoutillier\InvoiceBundle\DTO\CustomerData;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;

/**
 * Service de gestion des factures et avoirs.
 */
interface InvoiceManagerInterface
{
    /**
     * Crée une nouvelle facture (status DRAFT).
     *
     * @param CustomerData            $customerData Données client (snapshot)
     * @param \DateTimeImmutable      $date         Date de la facture
     * @param string                  $paymentTerms Conditions de paiement (ex: "30 jours net")
     * @param int|null                $companyId    ID société (mode multi-société uniquement)
     * @param \DateTimeImmutable|null $dueDate      Date d'échéance custom (sinon calculée)
     * @param string                  $currency     Code devise (défaut: EUR)
     *
     * @return Invoice La facture créée
     *
     * @throws \InvalidArgumentException Si les données sont invalides
     */
    public function createInvoice(
        CustomerData $customerData,
        \DateTimeImmutable $date,
        string $paymentTerms,
        ?int $companyId = null,
        ?\DateTimeImmutable $dueDate = null,
        string $currency = 'EUR',
    ): Invoice;

    /**
     * Crée un avoir (status DRAFT).
     *
     * @param CustomerData            $customerData    Données client (snapshot)
     * @param \DateTimeImmutable      $date            Date de l'avoir
     * @param string                  $paymentTerms    Conditions de paiement
     * @param Invoice|null            $creditedInvoice Facture créditée (optionnel)
     * @param int|null                $companyId       ID société (mode multi-société uniquement)
     * @param \DateTimeImmutable|null $dueDate         Date d'échéance custom (sinon calculée)
     * @param string                  $currency        Code devise (défaut: EUR)
     *
     * @return Invoice L'avoir créé
     *
     * @throws \InvalidArgumentException Si les données sont invalides
     */
    public function createCreditNote(
        CustomerData $customerData,
        \DateTimeImmutable $date,
        string $paymentTerms,
        ?Invoice $creditedInvoice = null,
        ?int $companyId = null,
        ?\DateTimeImmutable $dueDate = null,
        string $currency = 'EUR',
    ): Invoice;

    /**
     * Ajoute une ligne à une facture (DRAFT uniquement).
     *
     * @param Invoice     $invoice La facture (doit être DRAFT)
     * @param InvoiceLine $line    La ligne à ajouter
     *
     * @throws \InvalidArgumentException Si la facture n'est pas DRAFT
     */
    public function addLine(Invoice $invoice, InvoiceLine $line): void;

    /**
     * Modifie une facture (DRAFT uniquement).
     *
     * Tous les champs peuvent être modifiés tant que la facture est en DRAFT.
     *
     * @param Invoice              $invoice La facture à modifier
     * @param array<string, mixed> $data    Champs à modifier
     *
     * @throws \InvalidArgumentException Si la facture n'est pas DRAFT ou données invalides
     */
    public function updateInvoice(Invoice $invoice, array $data): void;

    /**
     * Annule une facture (DRAFT uniquement).
     *
     * @param Invoice     $invoice La facture à annuler
     * @param string|null $reason  Raison de l'annulation (optionnel)
     *
     * @throws \InvalidArgumentException Si la facture ne peut être annulée
     */
    public function cancelInvoice(Invoice $invoice, ?string $reason = null): void;
}
