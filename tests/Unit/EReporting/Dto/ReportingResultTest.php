<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\EReporting\Dto;

use CorentinBoutillier\InvoiceBundle\EReporting\Dto\ReportingResult;
use PHPUnit\Framework\TestCase;

final class ReportingResultTest extends TestCase
{
    public function testConstructorWithDefaults(): void
    {
        $result = new ReportingResult(success: true);

        $this->assertTrue($result->success);
        $this->assertNull($result->reportId);
        $this->assertNull($result->message);
        $this->assertSame([], $result->errors);
        $this->assertSame([], $result->warnings);
        $this->assertSame(0, $result->transactions);
        $this->assertNull($result->submittedAt);
        $this->assertSame([], $result->metadata);
    }

    public function testConstructorWithAllFields(): void
    {
        $submittedAt = new \DateTimeImmutable();

        $result = new ReportingResult(
            success: true,
            reportId: 'RPT-2025-001',
            message: 'Submitted successfully',
            errors: [],
            warnings: ['Minor issue'],
            transactions: 10,
            submittedAt: $submittedAt,
            metadata: ['batch_id' => '123'],
        );

        $this->assertTrue($result->success);
        $this->assertSame('RPT-2025-001', $result->reportId);
        $this->assertSame('Submitted successfully', $result->message);
        $this->assertSame(['Minor issue'], $result->warnings);
        $this->assertSame(10, $result->transactions);
        $this->assertSame($submittedAt, $result->submittedAt);
        $this->assertSame(['batch_id' => '123'], $result->metadata);
    }

    public function testSuccess(): void
    {
        $result = ReportingResult::success(
            reportId: 'RPT-2025-002',
            transactions: 5,
            message: 'All done',
            metadata: ['source' => 'test'],
        );

        $this->assertTrue($result->success);
        $this->assertSame('RPT-2025-002', $result->reportId);
        $this->assertSame('All done', $result->message);
        $this->assertSame(5, $result->transactions);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result->submittedAt);
        $this->assertSame(['source' => 'test'], $result->metadata);
    }

    public function testSuccessWithDefaultMessage(): void
    {
        $result = ReportingResult::success(
            reportId: 'RPT-2025-003',
            transactions: 3,
        );

        $this->assertSame('Rapport soumis avec succès', $result->message);
    }

    public function testFailure(): void
    {
        $result = ReportingResult::failure(
            message: 'Validation error',
            errors: ['Field A missing', 'Field B invalid'],
            metadata: ['request_id' => '456'],
        );

        $this->assertFalse($result->success);
        $this->assertSame('Validation error', $result->message);
        $this->assertSame(['Field A missing', 'Field B invalid'], $result->errors);
        $this->assertNull($result->reportId);
        $this->assertSame(0, $result->transactions);
    }

    public function testHasErrors(): void
    {
        $withErrors = new ReportingResult(
            success: false,
            errors: ['Error 1'],
        );
        $this->assertTrue($withErrors->hasErrors());

        $noErrors = new ReportingResult(success: true);
        $this->assertFalse($noErrors->hasErrors());
    }

    public function testHasWarnings(): void
    {
        $withWarnings = new ReportingResult(
            success: true,
            warnings: ['Warning 1'],
        );
        $this->assertTrue($withWarnings->hasWarnings());

        $noWarnings = new ReportingResult(success: true);
        $this->assertFalse($noWarnings->hasWarnings());
    }
}
