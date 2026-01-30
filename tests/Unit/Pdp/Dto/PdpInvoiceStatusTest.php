<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Pdp\Dto;

use CorentinBoutillier\InvoiceBundle\Pdp\Dto\PdpInvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Pdp\PdpStatusCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PdpInvoiceStatusTest extends TestCase
{
    public function testConstructWithMinimalData(): void
    {
        $statusAt = new \DateTimeImmutable();
        $status = new PdpInvoiceStatus(
            transmissionId: 'TX-123',
            status: PdpStatusCode::SUBMITTED,
            statusAt: $statusAt,
        );

        $this->assertSame('TX-123', $status->transmissionId);
        $this->assertSame(PdpStatusCode::SUBMITTED, $status->status);
        $this->assertSame($statusAt, $status->statusAt);
        $this->assertNull($status->message);
        $this->assertNull($status->recipientId);
        $this->assertNull($status->recipientName);
        $this->assertSame([], $status->metadata);
    }

    public function testConstructWithAllData(): void
    {
        $statusAt = new \DateTimeImmutable();
        $status = new PdpInvoiceStatus(
            transmissionId: 'TX-456',
            status: PdpStatusCode::DELIVERED,
            statusAt: $statusAt,
            message: 'Invoice delivered to recipient',
            recipientId: '12345678901234',
            recipientName: 'Acme Corp',
            metadata: ['delivery_id' => 'D-789'],
        );

        $this->assertSame('TX-456', $status->transmissionId);
        $this->assertSame(PdpStatusCode::DELIVERED, $status->status);
        $this->assertSame('Invoice delivered to recipient', $status->message);
        $this->assertSame('12345678901234', $status->recipientId);
        $this->assertSame('Acme Corp', $status->recipientName);
        $this->assertSame(['delivery_id' => 'D-789'], $status->metadata);
    }

    /**
     * @return array<string, array{PdpStatusCode, bool}>
     */
    public static function inProgressStatusProvider(): array
    {
        return [
            'PENDING' => [PdpStatusCode::PENDING, true],
            'SUBMITTED' => [PdpStatusCode::SUBMITTED, true],
            'ACCEPTED' => [PdpStatusCode::ACCEPTED, true],
            'TRANSMITTED' => [PdpStatusCode::TRANSMITTED, true],
            'DELIVERED' => [PdpStatusCode::DELIVERED, true],
            'ACKNOWLEDGED' => [PdpStatusCode::ACKNOWLEDGED, true],
            'APPROVED' => [PdpStatusCode::APPROVED, true],
            'PAID' => [PdpStatusCode::PAID, false],
            'REJECTED' => [PdpStatusCode::REJECTED, false],
            'REFUSED' => [PdpStatusCode::REFUSED, false],
            'FAILED' => [PdpStatusCode::FAILED, false],
            'CANCELLED' => [PdpStatusCode::CANCELLED, false],
        ];
    }

    #[DataProvider('inProgressStatusProvider')]
    public function testIsInProgress(PdpStatusCode $statusCode, bool $expected): void
    {
        $status = new PdpInvoiceStatus(
            transmissionId: 'TX-123',
            status: $statusCode,
            statusAt: new \DateTimeImmutable(),
        );

        $this->assertSame($expected, $status->isInProgress());
    }

    /**
     * @return array<string, array{PdpStatusCode, bool}>
     */
    public static function completeStatusProvider(): array
    {
        return [
            'PAID' => [PdpStatusCode::PAID, true],
            'REJECTED' => [PdpStatusCode::REJECTED, true],
            'REFUSED' => [PdpStatusCode::REFUSED, true],
            'FAILED' => [PdpStatusCode::FAILED, true],
            'CANCELLED' => [PdpStatusCode::CANCELLED, true],
            'PENDING' => [PdpStatusCode::PENDING, false],
            'SUBMITTED' => [PdpStatusCode::SUBMITTED, false],
            'DELIVERED' => [PdpStatusCode::DELIVERED, false],
        ];
    }

    #[DataProvider('completeStatusProvider')]
    public function testIsComplete(PdpStatusCode $statusCode, bool $expected): void
    {
        $status = new PdpInvoiceStatus(
            transmissionId: 'TX-123',
            status: $statusCode,
            statusAt: new \DateTimeImmutable(),
        );

        $this->assertSame($expected, $status->isComplete());
    }

    /**
     * @return array<string, array{PdpStatusCode, bool}>
     */
    public static function deliveredStatusProvider(): array
    {
        return [
            'DELIVERED' => [PdpStatusCode::DELIVERED, true],
            'ACKNOWLEDGED' => [PdpStatusCode::ACKNOWLEDGED, true],
            'APPROVED' => [PdpStatusCode::APPROVED, true],
            'PAID' => [PdpStatusCode::PAID, true],
            'PENDING' => [PdpStatusCode::PENDING, false],
            'SUBMITTED' => [PdpStatusCode::SUBMITTED, false],
            'TRANSMITTED' => [PdpStatusCode::TRANSMITTED, false],
            'REJECTED' => [PdpStatusCode::REJECTED, false],
        ];
    }

    #[DataProvider('deliveredStatusProvider')]
    public function testIsDelivered(PdpStatusCode $statusCode, bool $expected): void
    {
        $status = new PdpInvoiceStatus(
            transmissionId: 'TX-123',
            status: $statusCode,
            statusAt: new \DateTimeImmutable(),
        );

        $this->assertSame($expected, $status->isDelivered());
    }

    /**
     * @return array<string, array{PdpStatusCode, bool}>
     */
    public static function errorStatusProvider(): array
    {
        return [
            'REJECTED' => [PdpStatusCode::REJECTED, true],
            'REFUSED' => [PdpStatusCode::REFUSED, true],
            'FAILED' => [PdpStatusCode::FAILED, true],
            'CANCELLED' => [PdpStatusCode::CANCELLED, true],
            'PENDING' => [PdpStatusCode::PENDING, false],
            'DELIVERED' => [PdpStatusCode::DELIVERED, false],
            'PAID' => [PdpStatusCode::PAID, false],
        ];
    }

    #[DataProvider('errorStatusProvider')]
    public function testHasError(PdpStatusCode $statusCode, bool $expected): void
    {
        $status = new PdpInvoiceStatus(
            transmissionId: 'TX-123',
            status: $statusCode,
            statusAt: new \DateTimeImmutable(),
        );

        $this->assertSame($expected, $status->hasError());
    }

    public function testClassIsReadonly(): void
    {
        $reflection = new \ReflectionClass(PdpInvoiceStatus::class);
        $this->assertTrue($reflection->isReadOnly());
    }
}
