<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Pdp\Dto;

use CorentinBoutillier\InvoiceBundle\Pdp\Dto\TransmissionResult;
use CorentinBoutillier\InvoiceBundle\Pdp\PdpStatusCode;
use PHPUnit\Framework\TestCase;

final class TransmissionResultTest extends TestCase
{
    public function testConstructWithMinimalData(): void
    {
        $result = new TransmissionResult(success: true);

        $this->assertTrue($result->success);
        $this->assertNull($result->transmissionId);
        $this->assertSame(PdpStatusCode::PENDING, $result->status);
        $this->assertNull($result->message);
        $this->assertSame([], $result->errors);
        $this->assertSame([], $result->warnings);
        $this->assertNull($result->transmittedAt);
        $this->assertSame([], $result->metadata);
    }

    public function testConstructWithAllData(): void
    {
        $transmittedAt = new \DateTimeImmutable();
        $result = new TransmissionResult(
            success: true,
            transmissionId: 'TX-123',
            status: PdpStatusCode::SUBMITTED,
            message: 'Invoice submitted successfully',
            errors: [],
            warnings: ['Minor format issue'],
            transmittedAt: $transmittedAt,
            metadata: ['pdp_id' => 'abc'],
        );

        $this->assertTrue($result->success);
        $this->assertSame('TX-123', $result->transmissionId);
        $this->assertSame(PdpStatusCode::SUBMITTED, $result->status);
        $this->assertSame('Invoice submitted successfully', $result->message);
        $this->assertSame([], $result->errors);
        $this->assertSame(['Minor format issue'], $result->warnings);
        $this->assertSame($transmittedAt, $result->transmittedAt);
        $this->assertSame(['pdp_id' => 'abc'], $result->metadata);
    }

    public function testSuccessFactoryMethod(): void
    {
        $result = TransmissionResult::success(
            transmissionId: 'TX-456',
            message: 'Sent',
            status: PdpStatusCode::ACCEPTED,
            metadata: ['ref' => 'xyz'],
        );

        $this->assertTrue($result->success);
        $this->assertSame('TX-456', $result->transmissionId);
        $this->assertSame(PdpStatusCode::ACCEPTED, $result->status);
        $this->assertSame('Sent', $result->message);
        $this->assertNotNull($result->transmittedAt);
        $this->assertSame(['ref' => 'xyz'], $result->metadata);
    }

    public function testSuccessFactoryMethodDefaultStatus(): void
    {
        $result = TransmissionResult::success('TX-789');

        $this->assertSame(PdpStatusCode::SUBMITTED, $result->status);
    }

    public function testFailureFactoryMethod(): void
    {
        $result = TransmissionResult::failure(
            message: 'Connection timeout',
            errors: ['Network error', 'Retry failed'],
            status: PdpStatusCode::FAILED,
            metadata: ['attempt' => 3],
        );

        $this->assertFalse($result->success);
        $this->assertNull($result->transmissionId);
        $this->assertSame(PdpStatusCode::FAILED, $result->status);
        $this->assertSame('Connection timeout', $result->message);
        $this->assertSame(['Network error', 'Retry failed'], $result->errors);
        $this->assertSame(['attempt' => 3], $result->metadata);
    }

    public function testRejectedFactoryMethod(): void
    {
        $result = TransmissionResult::rejected(
            message: 'Invalid SIRET',
            errors: ['SIRET format invalid', 'Missing VAT number'],
            metadata: ['validation' => 'failed'],
        );

        $this->assertFalse($result->success);
        $this->assertSame(PdpStatusCode::REJECTED, $result->status);
        $this->assertSame('Invalid SIRET', $result->message);
        $this->assertSame(['SIRET format invalid', 'Missing VAT number'], $result->errors);
    }

    public function testHasErrors(): void
    {
        $withErrors = new TransmissionResult(
            success: false,
            errors: ['Error 1'],
        );
        $withoutErrors = new TransmissionResult(success: true);

        $this->assertTrue($withErrors->hasErrors());
        $this->assertFalse($withoutErrors->hasErrors());
    }

    public function testHasWarnings(): void
    {
        $withWarnings = new TransmissionResult(
            success: true,
            warnings: ['Warning 1'],
        );
        $withoutWarnings = new TransmissionResult(success: true);

        $this->assertTrue($withWarnings->hasWarnings());
        $this->assertFalse($withoutWarnings->hasWarnings());
    }

    public function testGetErrorCount(): void
    {
        $result = new TransmissionResult(
            success: false,
            errors: ['Error 1', 'Error 2', 'Error 3'],
        );

        $this->assertSame(3, $result->getErrorCount());
    }

    public function testClassIsReadonly(): void
    {
        $reflection = new \ReflectionClass(TransmissionResult::class);
        $this->assertTrue($reflection->isReadOnly());
    }
}
