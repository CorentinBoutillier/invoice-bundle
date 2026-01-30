<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Pdp\Dto;

use CorentinBoutillier\InvoiceBundle\Pdp\Dto\HealthCheckResult;
use PHPUnit\Framework\TestCase;

final class HealthCheckResultTest extends TestCase
{
    public function testConstructWithMinimalData(): void
    {
        $result = new HealthCheckResult(
            healthy: true,
            connectorId: 'pennylane',
        );

        $this->assertTrue($result->healthy);
        $this->assertSame('pennylane', $result->connectorId);
        $this->assertSame(0.0, $result->responseTimeMs);
        $this->assertNull($result->message);
        $this->assertNull($result->version);
        $this->assertNull($result->checkedAt);
        $this->assertSame([], $result->details);
    }

    public function testConstructWithAllData(): void
    {
        $checkedAt = new \DateTimeImmutable();
        $result = new HealthCheckResult(
            healthy: true,
            connectorId: 'chorus_pro',
            responseTimeMs: 125.5,
            message: 'All systems operational',
            version: '2.1.0',
            checkedAt: $checkedAt,
            details: ['endpoint' => 'https://api.chorus-pro.gouv.fr'],
        );

        $this->assertTrue($result->healthy);
        $this->assertSame('chorus_pro', $result->connectorId);
        $this->assertSame(125.5, $result->responseTimeMs);
        $this->assertSame('All systems operational', $result->message);
        $this->assertSame('2.1.0', $result->version);
        $this->assertSame($checkedAt, $result->checkedAt);
        $this->assertSame(['endpoint' => 'https://api.chorus-pro.gouv.fr'], $result->details);
    }

    public function testHealthyFactoryMethod(): void
    {
        $result = HealthCheckResult::healthy(
            connectorId: 'pennylane',
            responseTimeMs: 50.0,
            version: '1.0.0',
            details: ['status' => 'ok'],
        );

        $this->assertTrue($result->healthy);
        $this->assertSame('pennylane', $result->connectorId);
        $this->assertSame(50.0, $result->responseTimeMs);
        $this->assertSame('Connection successful', $result->message);
        $this->assertSame('1.0.0', $result->version);
        $this->assertNotNull($result->checkedAt);
        $this->assertSame(['status' => 'ok'], $result->details);
    }

    public function testUnhealthyFactoryMethod(): void
    {
        $result = HealthCheckResult::unhealthy(
            connectorId: 'chorus_pro',
            message: 'Connection timeout after 30s',
            responseTimeMs: 30000.0,
            details: ['error_code' => 'ETIMEDOUT'],
        );

        $this->assertFalse($result->healthy);
        $this->assertSame('chorus_pro', $result->connectorId);
        $this->assertSame(30000.0, $result->responseTimeMs);
        $this->assertSame('Connection timeout after 30s', $result->message);
        $this->assertNull($result->version);
        $this->assertNotNull($result->checkedAt);
        $this->assertSame(['error_code' => 'ETIMEDOUT'], $result->details);
    }

    public function testIsResponseTimeAcceptableWithinThreshold(): void
    {
        $result = new HealthCheckResult(
            healthy: true,
            connectorId: 'test',
            responseTimeMs: 1000.0,
        );

        $this->assertTrue($result->isResponseTimeAcceptable(5000.0));
        $this->assertTrue($result->isResponseTimeAcceptable(1000.0)); // Equal to threshold
    }

    public function testIsResponseTimeAcceptableExceedsThreshold(): void
    {
        $result = new HealthCheckResult(
            healthy: true,
            connectorId: 'test',
            responseTimeMs: 6000.0,
        );

        $this->assertFalse($result->isResponseTimeAcceptable(5000.0));
    }

    public function testIsResponseTimeAcceptableDefaultThreshold(): void
    {
        $fastResult = new HealthCheckResult(
            healthy: true,
            connectorId: 'test',
            responseTimeMs: 100.0,
        );

        $slowResult = new HealthCheckResult(
            healthy: true,
            connectorId: 'test',
            responseTimeMs: 10000.0,
        );

        $this->assertTrue($fastResult->isResponseTimeAcceptable()); // Default 5000ms
        $this->assertFalse($slowResult->isResponseTimeAcceptable());
    }

    public function testClassIsReadonly(): void
    {
        $reflection = new \ReflectionClass(HealthCheckResult::class);
        $this->assertTrue($reflection->isReadOnly());
    }
}
