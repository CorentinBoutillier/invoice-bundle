<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Entity;

use CorentinBoutillier\InvoiceBundle\Entity\LettrageSequence;
use PHPUnit\Framework\TestCase;

/**
 * Tests pour l'entité LettrageSequence.
 *
 * Cette entité gère la séquence des codes de lettrage par entreprise.
 * Les codes sont générés de manière séquentielle (1, 2, 3...) et convertis
 * ensuite en codes alphabétiques (A, B, C..., Z, AA, AB...).
 */
final class LettrageSequenceTest extends TestCase
{
    // ========== Construction ==========

    public function testConstructWithCompanyId(): void
    {
        $sequence = new LettrageSequence(companyId: 123);

        $this->assertSame(123, $sequence->getCompanyId());
    }

    public function testConstructWithNullCompanyId(): void
    {
        $sequence = new LettrageSequence(companyId: null);

        $this->assertNull($sequence->getCompanyId());
    }

    public function testConstructInitializesLastCodeToZero(): void
    {
        $sequence = new LettrageSequence(companyId: 1);

        $this->assertSame(0, $sequence->getLastCode());
    }

    public function testIdIsNullBeforePersistence(): void
    {
        $sequence = new LettrageSequence(companyId: 1);

        $this->assertNull($sequence->getId());
    }

    // ========== Génération du prochain code ==========

    public function testGetNextCodeReturnsOneForNewSequence(): void
    {
        $sequence = new LettrageSequence(companyId: 1);

        $this->assertSame(1, $sequence->getNextCode());
    }

    public function testGetNextCodeReturnsIncrementedValue(): void
    {
        $sequence = new LettrageSequence(companyId: 1);
        $sequence->incrementLastCode();

        $this->assertSame(2, $sequence->getNextCode());
    }

    public function testGetNextCodeDoesNotModifyLastCode(): void
    {
        $sequence = new LettrageSequence(companyId: 1);

        // Appeler getNextCode plusieurs fois ne doit pas modifier lastCode
        $sequence->getNextCode();
        $sequence->getNextCode();
        $sequence->getNextCode();

        $this->assertSame(0, $sequence->getLastCode());
    }

    // ========== Incrémentation ==========

    public function testIncrementLastCodeFromZero(): void
    {
        $sequence = new LettrageSequence(companyId: 1);

        $sequence->incrementLastCode();

        $this->assertSame(1, $sequence->getLastCode());
    }

    public function testIncrementLastCodeMultipleTimes(): void
    {
        $sequence = new LettrageSequence(companyId: 1);

        $sequence->incrementLastCode();
        $sequence->incrementLastCode();
        $sequence->incrementLastCode();

        $this->assertSame(3, $sequence->getLastCode());
    }

    public function testIncrementLastCodeReturnsSelf(): void
    {
        $sequence = new LettrageSequence(companyId: 1);

        $result = $sequence->incrementLastCode();

        $this->assertSame($sequence, $result);
    }

    // ========== Scénarios réels ==========

    public function testSequenceForSingleCompany(): void
    {
        $sequence = new LettrageSequence(companyId: 42);

        // Simule la création de 5 lettrages
        for ($i = 1; $i <= 5; ++$i) {
            $nextCode = $sequence->getNextCode();
            $this->assertSame($i, $nextCode);
            $sequence->incrementLastCode();
        }

        $this->assertSame(5, $sequence->getLastCode());
        $this->assertSame(6, $sequence->getNextCode());
    }

    public function testSequenceForMultiTenantWithNullCompany(): void
    {
        // Mode mono-entreprise : companyId est null
        $sequence = new LettrageSequence(companyId: null);

        $sequence->incrementLastCode();
        $sequence->incrementLastCode();

        $this->assertNull($sequence->getCompanyId());
        $this->assertSame(2, $sequence->getLastCode());
    }
}
