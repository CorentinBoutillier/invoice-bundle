<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Exception;

/**
 * Exception levée quand un lettrage n'est pas équilibré.
 *
 * Un lettrage doit avoir une somme des débits égale à la somme des crédits.
 */
final class LettrageNotBalancedException extends \InvalidArgumentException
{
    public function __construct(
        int $debitCents,
        int $creditCents,
        ?\Throwable $previous = null,
    ) {
        $message = \sprintf(
            'Lettrage is not balanced: debit = %d cents, credit = %d cents (difference = %d cents)',
            $debitCents,
            $creditCents,
            abs($debitCents - $creditCents),
        );

        parent::__construct($message, 0, $previous);
    }
}
