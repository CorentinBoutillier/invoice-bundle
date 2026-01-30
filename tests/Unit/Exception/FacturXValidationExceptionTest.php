<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Exception;

use CorentinBoutillier\InvoiceBundle\Exception\FacturXValidationException;
use CorentinBoutillier\InvoiceBundle\Service\Validation\ValidationError;
use CorentinBoutillier\InvoiceBundle\Service\Validation\ValidationResult;
use PHPUnit\Framework\TestCase;

class FacturXValidationExceptionTest extends TestCase
{
    public function testConstructWithValidationResult(): void
    {
        $result = ValidationResult::invalid([
            new ValidationError(message: 'Error 1'),
            new ValidationError(message: 'Error 2'),
        ]);

        $exception = new FacturXValidationException($result);

        $this->assertSame($result, $exception->getValidationResult());
        $this->assertStringContainsString('Factur-X XML validation failed', $exception->getMessage());
        $this->assertStringContainsString('Error 1', $exception->getMessage());
        $this->assertStringContainsString('Error 2', $exception->getMessage());
    }

    public function testGetErrorMessages(): void
    {
        $result = ValidationResult::invalid([
            new ValidationError(message: 'Error 1', code: 'E1'),
            new ValidationError(message: 'Error 2', line: 10),
        ]);

        $exception = new FacturXValidationException($result);

        $messages = $exception->getErrorMessages();

        $this->assertCount(2, $messages);
        $this->assertSame('[E1] Error 1', $messages[0]);
        $this->assertSame('Line 10: Error 2', $messages[1]);
    }

    public function testGetErrorCount(): void
    {
        $result = ValidationResult::invalid([
            new ValidationError(message: 'Error 1'),
            new ValidationError(message: 'Error 2'),
            new ValidationError(message: 'Error 3'),
        ]);

        $exception = new FacturXValidationException($result);

        $this->assertSame(3, $exception->getErrorCount());
    }

    public function testCustomMessage(): void
    {
        $result = ValidationResult::invalid([
            new ValidationError(message: 'Error'),
        ]);

        $exception = new FacturXValidationException($result, 'Custom validation message');

        $this->assertStringContainsString('Custom validation message', $exception->getMessage());
    }

    public function testMessageWithoutErrors(): void
    {
        $result = ValidationResult::invalid([]);

        $exception = new FacturXValidationException($result);

        $this->assertSame('Factur-X XML validation failed', $exception->getMessage());
    }

    public function testExceptionIsRuntimeException(): void
    {
        $result = ValidationResult::invalid([]);
        $exception = new FacturXValidationException($result);

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testExceptionWithPreviousException(): void
    {
        $previous = new \Exception('Previous error');
        $result = ValidationResult::invalid([]);

        $exception = new FacturXValidationException(
            $result,
            'Test message',
            42,
            $previous,
        );

        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame(42, $exception->getCode());
    }
}
