<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\Lettrage;

use CorentinBoutillier\InvoiceBundle\Entity\LettrageSequence;

/**
 * Génère des codes de lettrage alphabétiques.
 *
 * Convertit un numéro de séquence (1, 2, 3...) en code alphabétique
 * utilisable dans l'export FEC :
 *
 * 1→A, 2→B, ..., 26→Z, 27→AA, 28→AB, ..., 52→AZ, 53→BA, ..., 702→ZZ, 703→AAA
 *
 * C'est une numération bijective en base 26 où A=1, B=2, ..., Z=26.
 */
final class LettrageCodeGenerator implements LettrageCodeGeneratorInterface
{
    public function generate(LettrageSequence $sequence): string
    {
        $number = $sequence->getNextCode();

        return $this->numberToCode($number);
    }

    /**
     * Convertit un nombre en code alphabétique (numération bijective base 26).
     *
     * @param int $number Le nombre à convertir (>= 1)
     *
     * @return string Le code alphabétique correspondant
     */
    private function numberToCode(int $number): string
    {
        $code = '';

        while ($number > 0) {
            --$number;
            $remainder = $number % 26;
            $code = \chr(65 + $remainder).$code;
            $number = intdiv($number, 26);
        }

        return $code;
    }
}
