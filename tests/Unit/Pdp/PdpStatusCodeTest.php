<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Pdp;

use CorentinBoutillier\InvoiceBundle\Pdp\PdpStatusCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PdpStatusCodeTest extends TestCase
{
    public function testAllStatusCodesExist(): void
    {
        $this->assertSame('pending', PdpStatusCode::PENDING->value);
        $this->assertSame('submitted', PdpStatusCode::SUBMITTED->value);
        $this->assertSame('accepted', PdpStatusCode::ACCEPTED->value);
        $this->assertSame('rejected', PdpStatusCode::REJECTED->value);
        $this->assertSame('transmitted', PdpStatusCode::TRANSMITTED->value);
        $this->assertSame('delivered', PdpStatusCode::DELIVERED->value);
        $this->assertSame('acknowledged', PdpStatusCode::ACKNOWLEDGED->value);
        $this->assertSame('approved', PdpStatusCode::APPROVED->value);
        $this->assertSame('refused', PdpStatusCode::REFUSED->value);
        $this->assertSame('paid', PdpStatusCode::PAID->value);
        $this->assertSame('failed', PdpStatusCode::FAILED->value);
        $this->assertSame('cancelled', PdpStatusCode::CANCELLED->value);
    }

    public function testStatusCodeCount(): void
    {
        $this->assertCount(12, PdpStatusCode::cases());
    }

    /**
     * @return array<string, array{PdpStatusCode, bool}>
     */
    public static function successfulStatusProvider(): array
    {
        return [
            'ACCEPTED' => [PdpStatusCode::ACCEPTED, true],
            'TRANSMITTED' => [PdpStatusCode::TRANSMITTED, true],
            'DELIVERED' => [PdpStatusCode::DELIVERED, true],
            'ACKNOWLEDGED' => [PdpStatusCode::ACKNOWLEDGED, true],
            'APPROVED' => [PdpStatusCode::APPROVED, true],
            'PAID' => [PdpStatusCode::PAID, true],
            'PENDING' => [PdpStatusCode::PENDING, false],
            'SUBMITTED' => [PdpStatusCode::SUBMITTED, false],
            'REJECTED' => [PdpStatusCode::REJECTED, false],
            'REFUSED' => [PdpStatusCode::REFUSED, false],
            'FAILED' => [PdpStatusCode::FAILED, false],
            'CANCELLED' => [PdpStatusCode::CANCELLED, false],
        ];
    }

    #[DataProvider('successfulStatusProvider')]
    public function testIsSuccessful(PdpStatusCode $status, bool $expected): void
    {
        $this->assertSame($expected, $status->isSuccessful());
    }

    /**
     * @return array<string, array{PdpStatusCode, bool}>
     */
    public static function failureStatusProvider(): array
    {
        return [
            'REJECTED' => [PdpStatusCode::REJECTED, true],
            'REFUSED' => [PdpStatusCode::REFUSED, true],
            'FAILED' => [PdpStatusCode::FAILED, true],
            'CANCELLED' => [PdpStatusCode::CANCELLED, true],
            'PENDING' => [PdpStatusCode::PENDING, false],
            'SUBMITTED' => [PdpStatusCode::SUBMITTED, false],
            'ACCEPTED' => [PdpStatusCode::ACCEPTED, false],
            'TRANSMITTED' => [PdpStatusCode::TRANSMITTED, false],
            'DELIVERED' => [PdpStatusCode::DELIVERED, false],
            'ACKNOWLEDGED' => [PdpStatusCode::ACKNOWLEDGED, false],
            'APPROVED' => [PdpStatusCode::APPROVED, false],
            'PAID' => [PdpStatusCode::PAID, false],
        ];
    }

    #[DataProvider('failureStatusProvider')]
    public function testIsFailure(PdpStatusCode $status, bool $expected): void
    {
        $this->assertSame($expected, $status->isFailure());
    }

    /**
     * @return array<string, array{PdpStatusCode, bool}>
     */
    public static function terminalStatusProvider(): array
    {
        return [
            'PAID' => [PdpStatusCode::PAID, true],
            'REJECTED' => [PdpStatusCode::REJECTED, true],
            'REFUSED' => [PdpStatusCode::REFUSED, true],
            'FAILED' => [PdpStatusCode::FAILED, true],
            'CANCELLED' => [PdpStatusCode::CANCELLED, true],
            'PENDING' => [PdpStatusCode::PENDING, false],
            'SUBMITTED' => [PdpStatusCode::SUBMITTED, false],
            'ACCEPTED' => [PdpStatusCode::ACCEPTED, false],
            'TRANSMITTED' => [PdpStatusCode::TRANSMITTED, false],
            'DELIVERED' => [PdpStatusCode::DELIVERED, false],
            'ACKNOWLEDGED' => [PdpStatusCode::ACKNOWLEDGED, false],
            'APPROVED' => [PdpStatusCode::APPROVED, false],
        ];
    }

    #[DataProvider('terminalStatusProvider')]
    public function testIsTerminal(PdpStatusCode $status, bool $expected): void
    {
        $this->assertSame($expected, $status->isTerminal());
    }

    /**
     * @return array<string, array{PdpStatusCode, bool}>
     */
    public static function pendingStatusProvider(): array
    {
        return [
            'PENDING' => [PdpStatusCode::PENDING, true],
            'SUBMITTED' => [PdpStatusCode::SUBMITTED, true],
            'ACCEPTED' => [PdpStatusCode::ACCEPTED, true],
            'TRANSMITTED' => [PdpStatusCode::TRANSMITTED, true],
            'DELIVERED' => [PdpStatusCode::DELIVERED, true],
            'ACKNOWLEDGED' => [PdpStatusCode::ACKNOWLEDGED, true],
            'APPROVED' => [PdpStatusCode::APPROVED, true],
            'REJECTED' => [PdpStatusCode::REJECTED, false],
            'REFUSED' => [PdpStatusCode::REFUSED, false],
            'PAID' => [PdpStatusCode::PAID, false],
            'FAILED' => [PdpStatusCode::FAILED, false],
            'CANCELLED' => [PdpStatusCode::CANCELLED, false],
        ];
    }

    #[DataProvider('pendingStatusProvider')]
    public function testIsPending(PdpStatusCode $status, bool $expected): void
    {
        $this->assertSame($expected, $status->isPending());
    }

    /**
     * @return array<string, array{PdpStatusCode, string}>
     */
    public static function labelProvider(): array
    {
        return [
            'PENDING' => [PdpStatusCode::PENDING, 'En attente'],
            'SUBMITTED' => [PdpStatusCode::SUBMITTED, 'Soumise'],
            'ACCEPTED' => [PdpStatusCode::ACCEPTED, 'Acceptée'],
            'REJECTED' => [PdpStatusCode::REJECTED, 'Rejetée'],
            'TRANSMITTED' => [PdpStatusCode::TRANSMITTED, 'Transmise'],
            'DELIVERED' => [PdpStatusCode::DELIVERED, 'Livrée'],
            'ACKNOWLEDGED' => [PdpStatusCode::ACKNOWLEDGED, 'Accusée de réception'],
            'APPROVED' => [PdpStatusCode::APPROVED, 'Approuvée'],
            'REFUSED' => [PdpStatusCode::REFUSED, 'Refusée'],
            'PAID' => [PdpStatusCode::PAID, 'Payée'],
            'FAILED' => [PdpStatusCode::FAILED, 'Échec'],
            'CANCELLED' => [PdpStatusCode::CANCELLED, 'Annulée'],
        ];
    }

    #[DataProvider('labelProvider')]
    public function testGetLabel(PdpStatusCode $status, string $expected): void
    {
        $this->assertSame($expected, $status->getLabel());
    }

    public function testStatusFromValue(): void
    {
        $this->assertSame(PdpStatusCode::PENDING, PdpStatusCode::from('pending'));
        $this->assertSame(PdpStatusCode::TRANSMITTED, PdpStatusCode::from('transmitted'));
        $this->assertSame(PdpStatusCode::PAID, PdpStatusCode::from('paid'));
    }

    public function testStatusTryFromInvalidValue(): void
    {
        $this->assertNull(PdpStatusCode::tryFrom('invalid'));
    }
}
