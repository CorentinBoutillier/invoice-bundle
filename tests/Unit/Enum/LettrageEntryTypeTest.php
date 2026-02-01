<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Enum;

use CorentinBoutillier\InvoiceBundle\Enum\LettrageEntryType;
use PHPUnit\Framework\TestCase;

/**
 * Tests pour l'enum LettrageEntryType.
 *
 * Vérifie que les types d'entrée de lettrage (DEBIT/CREDIT) sont correctement définis.
 */
final class LettrageEntryTypeTest extends TestCase
{
    // ========== Valeurs de base ==========

    public function testDebitValueExists(): void
    {
        $type = LettrageEntryType::DEBIT;

        $this->assertSame('debit', $type->value);
    }

    public function testCreditValueExists(): void
    {
        $type = LettrageEntryType::CREDIT;

        $this->assertSame('credit', $type->value);
    }

    // ========== Enumération complète ==========

    public function testOnlyTwoTypesExist(): void
    {
        $cases = LettrageEntryType::cases();

        $this->assertCount(2, $cases);
    }

    public function testAllCasesHaveExpectedValues(): void
    {
        $expectedValues = ['debit', 'credit'];
        $actualValues = array_map(
            static fn (LettrageEntryType $type): string => $type->value,
            LettrageEntryType::cases(),
        );

        sort($expectedValues);
        sort($actualValues);

        $this->assertSame($expectedValues, $actualValues);
    }

    // ========== Création depuis valeur string ==========

    public function testFromStringDebit(): void
    {
        $type = LettrageEntryType::from('debit');

        $this->assertSame(LettrageEntryType::DEBIT, $type);
    }

    public function testFromStringCredit(): void
    {
        $type = LettrageEntryType::from('credit');

        $this->assertSame(LettrageEntryType::CREDIT, $type);
    }

    public function testTryFromInvalidValueReturnsNull(): void
    {
        $type = LettrageEntryType::tryFrom('invalid');

        $this->assertNull($type);
    }
}
