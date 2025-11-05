<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\DTO;

readonly class Money implements \Stringable
{
    private function __construct(
        private int $amount, // Centimes
    ) {
    }

    // ========== Factory methods ==========

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public static function fromEuros(string $euros): self
    {
        $cents = (int) round((float) $euros * 100);

        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    // ========== Getters ==========

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function toEuros(): string
    {
        $euros = $this->amount / 100;

        return number_format($euros, 2, '.', '');
    }

    public function __toString(): string
    {
        return $this->toEuros();
    }

    public function format(string $locale = 'fr_FR'): string
    {
        $euros = $this->amount / 100;

        // Format français par défaut
        if ('fr_FR' === $locale || str_starts_with($locale, 'fr')) {
            return number_format($euros, 2, ',', ' ').' €';
        }

        // Format anglais
        if ('en_US' === $locale || str_starts_with($locale, 'en')) {
            return number_format($euros, 2, '.', ',').' €';
        }

        // Format par défaut (français)
        return number_format($euros, 2, ',', ' ').' €';
    }

    // ========== Arithmetic operations (immutable) ==========

    public function add(self $other): self
    {
        return new self($this->amount + $other->amount);
    }

    public function subtract(self $other): self
    {
        return new self($this->amount - $other->amount);
    }

    public function multiply(int|float $multiplier): self
    {
        $result = (int) round($this->amount * $multiplier);

        return new self($result);
    }

    public function divide(int $divisor): self
    {
        if (0 === $divisor) {
            throw new \InvalidArgumentException('Division by zero');
        }

        $result = (int) round($this->amount / $divisor);

        return new self($result);
    }

    // ========== Comparisons ==========

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount;
    }

    public function isZero(): bool
    {
        return 0 === $this->amount;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function greaterThan(self $other): bool
    {
        return $this->amount > $other->amount;
    }

    public function lessThan(self $other): bool
    {
        return $this->amount < $other->amount;
    }

    public function greaterThanOrEqual(self $other): bool
    {
        return $this->amount >= $other->amount;
    }

    public function lessThanOrEqual(self $other): bool
    {
        return $this->amount <= $other->amount;
    }
}
