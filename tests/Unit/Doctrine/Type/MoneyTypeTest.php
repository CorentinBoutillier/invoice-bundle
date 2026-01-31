<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Doctrine\Type;

use CorentinBoutillier\InvoiceBundle\Doctrine\Type\MoneyType;
use CorentinBoutillier\InvoiceBundle\DTO\Money;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(MoneyType::class)]
final class MoneyTypeTest extends TestCase
{
    /** @phpstan-ignore property.uninitialized (initialized in setUp) */
    private MoneyType $type;

    /**
     * @var AbstractPlatform&MockObject
     *
     * @phpstan-ignore property.uninitialized (initialized in setUp)
     */
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new MoneyType();
        $this->platform = $this->createMock(AbstractPlatform::class);
    }

    public function testGetName(): void
    {
        self::assertSame('money', $this->type->getName());
        self::assertSame(MoneyType::NAME, $this->type->getName());
    }

    public function testGetSQLDeclaration(): void
    {
        $this->platform
            ->method('getIntegerTypeDeclarationSQL')
            ->with(['unsigned' => true])
            ->willReturn('INTEGER');

        $result = $this->type->getSQLDeclaration(['unsigned' => true], $this->platform);

        self::assertSame('INTEGER', $result);
    }

    public function testRequiresSQLCommentHint(): void
    {
        self::assertTrue($this->type->requiresSQLCommentHint($this->platform));
    }

    // --- convertToDatabaseValue ---

    public function testConvertToDatabaseValueWithNull(): void
    {
        $result = $this->type->convertToDatabaseValue(null, $this->platform);

        self::assertNull($result);
    }

    public function testConvertToDatabaseValueWithMoney(): void
    {
        $money = Money::fromCents(1500);

        $result = $this->type->convertToDatabaseValue($money, $this->platform);

        self::assertSame(1500, $result);
    }

    public function testConvertToDatabaseValueWithZeroMoney(): void
    {
        $money = Money::zero();

        $result = $this->type->convertToDatabaseValue($money, $this->platform);

        self::assertSame(0, $result);
    }

    public function testConvertToDatabaseValueWithNegativeMoney(): void
    {
        $money = Money::fromCents(-500);

        $result = $this->type->convertToDatabaseValue($money, $this->platform);

        self::assertSame(-500, $result);
    }

    public function testConvertToDatabaseValueWithInvalidTypeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected CorentinBoutillier\InvoiceBundle\DTO\Money, got int');

        $this->type->convertToDatabaseValue(1500, $this->platform);
    }

    public function testConvertToDatabaseValueWithStringThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected CorentinBoutillier\InvoiceBundle\DTO\Money, got string');

        $this->type->convertToDatabaseValue('1500', $this->platform);
    }

    // --- convertToPHPValue ---

    public function testConvertToPHPValueWithNull(): void
    {
        $result = $this->type->convertToPHPValue(null, $this->platform);

        self::assertNull($result);
    }

    public function testConvertToPHPValueWithEmptyString(): void
    {
        $result = $this->type->convertToPHPValue('', $this->platform);

        self::assertNull($result);
    }

    public function testConvertToPHPValueWithMoney(): void
    {
        $money = Money::fromCents(1500);

        $result = $this->type->convertToPHPValue($money, $this->platform);

        self::assertSame($money, $result);
    }

    public function testConvertToPHPValueWithInteger(): void
    {
        $result = $this->type->convertToPHPValue(1500, $this->platform);

        self::assertInstanceOf(Money::class, $result);
        self::assertSame(1500, $result->getAmount());
    }

    public function testConvertToPHPValueWithZero(): void
    {
        $result = $this->type->convertToPHPValue(0, $this->platform);

        self::assertInstanceOf(Money::class, $result);
        self::assertTrue($result->isZero());
    }

    public function testConvertToPHPValueWithNegativeInteger(): void
    {
        $result = $this->type->convertToPHPValue(-500, $this->platform);

        self::assertInstanceOf(Money::class, $result);
        self::assertSame(-500, $result->getAmount());
    }

    public function testConvertToPHPValueWithNumericString(): void
    {
        $result = $this->type->convertToPHPValue('1500', $this->platform);

        self::assertInstanceOf(Money::class, $result);
        self::assertSame(1500, $result->getAmount());
    }

    public function testConvertToPHPValueWithFloatString(): void
    {
        // Float strings are truncated to int
        $result = $this->type->convertToPHPValue('1500.99', $this->platform);

        self::assertInstanceOf(Money::class, $result);
        self::assertSame(1500, $result->getAmount());
    }

    public function testConvertToPHPValueWithInvalidStringThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected numeric value, got string');

        $this->type->convertToPHPValue('not-a-number', $this->platform);
    }

    public function testConvertToPHPValueWithArrayThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected numeric value, got array');

        $this->type->convertToPHPValue(['amount' => 1500], $this->platform);
    }

    public function testConvertToPHPValueWithObjectThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected numeric value, got stdClass');

        $this->type->convertToPHPValue(new \stdClass(), $this->platform);
    }

    // --- Round-trip tests ---

    #[DataProvider('roundTripDataProvider')]
    public function testRoundTrip(int $cents): void
    {
        $money = Money::fromCents($cents);

        $dbValue = $this->type->convertToDatabaseValue($money, $this->platform);
        $phpValue = $this->type->convertToPHPValue($dbValue, $this->platform);

        self::assertInstanceOf(Money::class, $phpValue);
        self::assertSame($cents, $phpValue->getAmount());
        self::assertTrue($money->equals($phpValue));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function roundTripDataProvider(): iterable
    {
        yield 'zero' => [0];
        yield 'positive small' => [100];
        yield 'positive large' => [999999];
        yield 'negative' => [-500];
        yield 'one cent' => [1];
    }
}
