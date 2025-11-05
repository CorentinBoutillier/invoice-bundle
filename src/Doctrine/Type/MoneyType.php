<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Doctrine\Type;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class MoneyType extends Type
{
    public const NAME = 'money';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getIntegerTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?int
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof Money) {
            throw new \InvalidArgumentException(\sprintf(
                'Expected %s, got %s',
                Money::class,
                get_debug_type($value),
            ));
        }

        return $value->getAmount();
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?Money
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof Money) {
            return $value;
        }

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException(\sprintf(
                'Expected numeric value, got %s',
                get_debug_type($value),
            ));
        }

        return Money::fromCents((int) $value);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
