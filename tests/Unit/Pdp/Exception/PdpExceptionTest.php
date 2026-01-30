<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Pdp\Exception;

use CorentinBoutillier\InvoiceBundle\Pdp\Exception\PdpException;
use PHPUnit\Framework\TestCase;

final class PdpExceptionTest extends TestCase
{
    public function testConstructWithDefaultMessage(): void
    {
        $exception = new PdpException();

        $this->assertSame('PDP operation failed', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertNull($exception->getConnectorId());
    }

    public function testConstructWithAllParameters(): void
    {
        $previous = new \Exception('Previous error');
        $exception = new PdpException(
            message: 'Custom error message',
            code: 42,
            previous: $previous,
            connectorId: 'pennylane',
        );

        $this->assertSame('Custom error message', $exception->getMessage());
        $this->assertSame(42, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame('pennylane', $exception->getConnectorId());
    }

    public function testIsRuntimeException(): void
    {
        $exception = new PdpException();

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
