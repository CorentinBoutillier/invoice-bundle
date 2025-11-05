<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service;

/**
 * Default implementation of due date calculator for French payment terms.
 *
 * Implements business logic for calculating invoice due dates based on
 * standard French payment terms. Handles calendar edge cases like leap years
 * and month-end transitions.
 */
final class DueDateCalculator implements DueDateCalculatorInterface
{
    public function calculate(
        \DateTimeImmutable $invoiceDate,
        string $paymentTerms,
    ): \DateTimeImmutable {
        // Comptant = immediate payment (same day)
        if ('comptant' === $paymentTerms) {
            return $invoiceDate;
        }

        // Extract days number from payment terms
        // Format: "X jours net" or "X jours fin de mois"
        if (preg_match('/^(\d+) jours (net|fin de mois)$/', $paymentTerms, $matches)) {
            $days = (int) $matches[1];
            $type = $matches[2];

            $date = $invoiceDate->modify("+{$days} days");

            if ('fin de mois' === $type) {
                return $this->getEndOfMonth($date);
            }

            return $date;
        }

        // Default fallback: 30 jours net for unknown/invalid terms
        return $invoiceDate->modify('+30 days');
    }

    /**
     * Returns the last day of the month for the given date.
     */
    private function getEndOfMonth(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->modify('last day of this month');
    }
}
