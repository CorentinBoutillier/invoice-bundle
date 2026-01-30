<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Pdp\Exception;

use CorentinBoutillier\InvoiceBundle\Pdp\Dto\TransmissionResult;
use CorentinBoutillier\InvoiceBundle\Pdp\Exception\PdpException;
use CorentinBoutillier\InvoiceBundle\Pdp\Exception\PdpTransmissionException;
use CorentinBoutillier\InvoiceBundle\Pdp\PdpStatusCode;
use PHPUnit\Framework\TestCase;

final class PdpTransmissionExceptionTest extends TestCase
{
    public function testConstructWithMinimalData(): void
    {
        $exception = new PdpTransmissionException('Transmission failed');

        $this->assertSame('Transmission failed', $exception->getMessage());
        $this->assertNull($exception->getResult());
        $this->assertSame([], $exception->getErrors());
        $this->assertNull($exception->getConnectorId());
    }

    public function testConstructWithAllParameters(): void
    {
        $result = TransmissionResult::failure('API error', ['Error 1', 'Error 2']);
        $previous = new \Exception('Previous');

        $exception = new PdpTransmissionException(
            message: 'Custom error',
            result: $result,
            errors: ['Error 1', 'Error 2'],
            connectorId: 'pennylane',
            code: 500,
            previous: $previous,
        );

        $this->assertSame('Custom error', $exception->getMessage());
        $this->assertSame($result, $exception->getResult());
        $this->assertSame(['Error 1', 'Error 2'], $exception->getErrors());
        $this->assertSame('pennylane', $exception->getConnectorId());
        $this->assertSame(500, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testFromResult(): void
    {
        $result = TransmissionResult::failure(
            message: 'Invalid SIRET format',
            errors: ['SIRET must be 14 digits', 'Missing VAT number'],
            status: PdpStatusCode::REJECTED,
        );

        $exception = PdpTransmissionException::fromResult($result, 'chorus_pro');

        $this->assertSame('Invalid SIRET format', $exception->getMessage());
        $this->assertSame($result, $exception->getResult());
        $this->assertSame(['SIRET must be 14 digits', 'Missing VAT number'], $exception->getErrors());
        $this->assertSame('chorus_pro', $exception->getConnectorId());
    }

    public function testFromResultWithNullMessage(): void
    {
        $result = new TransmissionResult(
            success: false,
            status: PdpStatusCode::FAILED,
        );

        $exception = PdpTransmissionException::fromResult($result);

        $this->assertSame('Transmission failed', $exception->getMessage());
    }

    public function testNetworkError(): void
    {
        $previous = new \Exception('Connection refused');
        $exception = PdpTransmissionException::networkError(
            message: 'Failed to connect to PDP API',
            connectorId: 'pennylane',
            previous: $previous,
        );

        $this->assertSame('Failed to connect to PDP API', $exception->getMessage());
        $this->assertNull($exception->getResult());
        $this->assertSame([], $exception->getErrors());
        $this->assertSame('pennylane', $exception->getConnectorId());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testValidationFailed(): void
    {
        $errors = ['Invalid total amount', 'Missing customer SIRET'];
        $exception = PdpTransmissionException::validationFailed($errors, 'chorus_pro');

        $this->assertStringContainsString('Invalid total amount', $exception->getMessage());
        $this->assertStringContainsString('Missing customer SIRET', $exception->getMessage());
        $this->assertSame($errors, $exception->getErrors());
        $this->assertSame('chorus_pro', $exception->getConnectorId());
    }

    public function testHasErrors(): void
    {
        $withErrors = new PdpTransmissionException(
            message: 'Error',
            errors: ['Error 1'],
        );
        $withoutErrors = new PdpTransmissionException(message: 'Error');

        $this->assertTrue($withErrors->hasErrors());
        $this->assertFalse($withoutErrors->hasErrors());
    }

    public function testExtendsPdpException(): void
    {
        $exception = new PdpTransmissionException('Test');

        $this->assertInstanceOf(PdpException::class, $exception);
    }
}
