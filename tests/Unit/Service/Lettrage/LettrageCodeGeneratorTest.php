<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Service\Lettrage;

use CorentinBoutillier\InvoiceBundle\Entity\LettrageSequence;
use CorentinBoutillier\InvoiceBundle\Service\Lettrage\LettrageCodeGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Tests pour LettrageCodeGenerator.
 *
 * Le générateur convertit un numéro de séquence en code alphabétique :
 * 1→A, 2→B, ..., 26→Z, 27→AA, 28→AB, ..., 52→AZ, 53→BA, ..., 702→ZZ, 703→AAA
 *
 * C'est une numération en base 26 où A=1, B=2, ..., Z=26.
 */
final class LettrageCodeGeneratorTest extends TestCase
{
    /** @phpstan-ignore property.uninitialized */
    private LettrageCodeGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new LettrageCodeGenerator();
    }

    // ========== Lettres simples (1-26) ==========

    public function testGenerateCodeForNumber1ReturnsA(): void
    {
        $sequence = new LettrageSequence(companyId: 1);
        // sequence.lastCode = 0, donc nextCode = 1

        $code = $this->generator->generate($sequence);

        $this->assertSame('A', $code);
    }

    public function testGenerateCodeForNumber2ReturnsB(): void
    {
        $sequence = new LettrageSequence(companyId: 1);
        $sequence->setLastCode(1); // nextCode = 2

        $code = $this->generator->generate($sequence);

        $this->assertSame('B', $code);
    }

    public function testGenerateCodeForNumber26ReturnsZ(): void
    {
        $sequence = new LettrageSequence(companyId: 1);
        $sequence->setLastCode(25); // nextCode = 26

        $code = $this->generator->generate($sequence);

        $this->assertSame('Z', $code);
    }

    // ========== Deux lettres (27-702) ==========

    public function testGenerateCodeForNumber27ReturnsAA(): void
    {
        $sequence = new LettrageSequence(companyId: 1);
        $sequence->setLastCode(26); // nextCode = 27

        $code = $this->generator->generate($sequence);

        $this->assertSame('AA', $code);
    }

    public function testGenerateCodeForNumber28ReturnsAB(): void
    {
        $sequence = new LettrageSequence(companyId: 1);
        $sequence->setLastCode(27); // nextCode = 28

        $code = $this->generator->generate($sequence);

        $this->assertSame('AB', $code);
    }

    public function testGenerateCodeForNumber52ReturnsAZ(): void
    {
        $sequence = new LettrageSequence(companyId: 1);
        $sequence->setLastCode(51); // nextCode = 52

        $code = $this->generator->generate($sequence);

        $this->assertSame('AZ', $code);
    }

    public function testGenerateCodeForNumber53ReturnsBA(): void
    {
        $sequence = new LettrageSequence(companyId: 1);
        $sequence->setLastCode(52); // nextCode = 53

        $code = $this->generator->generate($sequence);

        $this->assertSame('BA', $code);
    }

    public function testGenerateCodeForNumber702ReturnsZZ(): void
    {
        $sequence = new LettrageSequence(companyId: 1);
        $sequence->setLastCode(701); // nextCode = 702

        $code = $this->generator->generate($sequence);

        $this->assertSame('ZZ', $code);
    }

    // ========== Trois lettres (703+) ==========

    public function testGenerateCodeForNumber703ReturnsAAA(): void
    {
        $sequence = new LettrageSequence(companyId: 1);
        $sequence->setLastCode(702); // nextCode = 703

        $code = $this->generator->generate($sequence);

        $this->assertSame('AAA', $code);
    }

    public function testGenerateCodeForNumber704ReturnsAAB(): void
    {
        $sequence = new LettrageSequence(companyId: 1);
        $sequence->setLastCode(703); // nextCode = 704

        $code = $this->generator->generate($sequence);

        $this->assertSame('AAB', $code);
    }

    // ========== Cas particuliers ==========

    public function testGenerateCodeForNumber13ReturnsM(): void
    {
        $sequence = new LettrageSequence(companyId: 1);
        $sequence->setLastCode(12); // nextCode = 13

        $code = $this->generator->generate($sequence);

        $this->assertSame('M', $code);
    }

    public function testGenerateCodeForNumber78ReturnsBZ(): void
    {
        $sequence = new LettrageSequence(companyId: 1);
        $sequence->setLastCode(77); // nextCode = 78 = 26 + 26 + 26 = 52 + 26 = AZ + BA + ... = BZ

        // 78 = 2*26 + 26 = 78
        // 78 - 26 = 52 = 2*26 → B
        // 52 / 26 = 2, reste 0 → A? Non...
        // En fait : 78 = 27 + 51 = AA + 51 lettres après
        // 78 - 27 = 51 (positions dans les deux lettres)
        // (51 / 26) = 1 reste 25 → B + Z = BZ? Non...
        // Algorithme : 78 → (78-1) % 26 = 77 % 26 = 25 → Z
        //              (78-1) / 26 = 2 → 2-1 = 1 → B
        // Donc BZ
        $code = $this->generator->generate($sequence);

        $this->assertSame('BZ', $code);
    }

    // ========== Test avec numberToCode directement ==========

    /**
     * @dataProvider provideNumberToCodeCases
     */
    public function testNumberToCodeConversion(int $number, string $expectedCode): void
    {
        $sequence = new LettrageSequence(companyId: 1);
        $sequence->setLastCode($number - 1); // nextCode = $number

        $code = $this->generator->generate($sequence);

        $this->assertSame($expectedCode, $code);
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function provideNumberToCodeCases(): array
    {
        return [
            '1 → A' => [1, 'A'],
            '2 → B' => [2, 'B'],
            '3 → C' => [3, 'C'],
            '10 → J' => [10, 'J'],
            '25 → Y' => [25, 'Y'],
            '26 → Z' => [26, 'Z'],
            '27 → AA' => [27, 'AA'],
            '28 → AB' => [28, 'AB'],
            '51 → AY' => [51, 'AY'],
            '52 → AZ' => [52, 'AZ'],
            '53 → BA' => [53, 'BA'],
            '54 → BB' => [54, 'BB'],
            '78 → BZ' => [78, 'BZ'],
            '79 → CA' => [79, 'CA'],
            '100 → CV' => [100, 'CV'],
            '200 → GR' => [200, 'GR'],
            '500 → SF' => [500, 'SF'],
            '676 → YZ' => [676, 'YZ'],
            '677 → ZA' => [677, 'ZA'],
            '702 → ZZ' => [702, 'ZZ'],
            '703 → AAA' => [703, 'AAA'],
            '704 → AAB' => [704, 'AAB'],
            '728 → AAZ' => [728, 'AAZ'],
            '729 → ABA' => [729, 'ABA'],
            '1000 → ALL' => [1000, 'ALL'],
        ];
    }

    // ========== Scénarios réels ==========

    public function testTypicalUsageForNewCompany(): void
    {
        $sequence = new LettrageSequence(companyId: 42);

        // Premier lettrage
        $code1 = $this->generator->generate($sequence);
        $this->assertSame('A', $code1);
        $sequence->incrementLastCode();

        // Deuxième lettrage
        $code2 = $this->generator->generate($sequence);
        $this->assertSame('B', $code2);
        $sequence->incrementLastCode();

        // Troisième lettrage
        $code3 = $this->generator->generate($sequence);
        $this->assertSame('C', $code3);
    }

    public function testCompanyWithHighVolume(): void
    {
        // Entreprise avec beaucoup de lettrages (année très active)
        $sequence = new LettrageSequence(companyId: 42);
        $sequence->setLastCode(999); // nextCode = 1000

        $code = $this->generator->generate($sequence);

        $this->assertSame('ALL', $code);
    }
}
