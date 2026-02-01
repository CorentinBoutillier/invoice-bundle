<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\Lettrage;

use CorentinBoutillier\InvoiceBundle\Entity\LettrageSequence;

/**
 * Interface pour la génération des codes de lettrage.
 *
 * Convertit un numéro de séquence en code alphabétique utilisable
 * dans l'export FEC (colonne EcritureLet).
 */
interface LettrageCodeGeneratorInterface
{
    /**
     * Génère le prochain code de lettrage basé sur la séquence.
     *
     * Utilise getNextCode() de la séquence pour obtenir le numéro
     * à convertir en code alphabétique (A, B, ..., Z, AA, AB, ...).
     *
     * @param LettrageSequence $sequence La séquence de lettrage
     *
     * @return string Le code alphabétique généré
     */
    public function generate(LettrageSequence $sequence): string;
}
