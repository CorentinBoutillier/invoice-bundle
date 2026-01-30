<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Pdp\Exception;

use CorentinBoutillier\InvoiceBundle\Pdp\Exception\PdpConnectorNotFoundException;
use CorentinBoutillier\InvoiceBundle\Pdp\Exception\PdpException;
use PHPUnit\Framework\TestCase;

final class PdpConnectorNotFoundExceptionTest extends TestCase
{
    public function testConstructWithConnectorId(): void
    {
        $exception = new PdpConnectorNotFoundException('pennylane');

        $this->assertSame('PDP connector "pennylane" not found', $exception->getMessage());
        $this->assertSame('pennylane', $exception->getConnectorId());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructWithAllParameters(): void
    {
        $previous = new \Exception('Previous error');
        $exception = new PdpConnectorNotFoundException(
            connectorId: 'chorus_pro',
            code: 404,
            previous: $previous,
        );

        $this->assertSame('PDP connector "chorus_pro" not found', $exception->getMessage());
        $this->assertSame('chorus_pro', $exception->getConnectorId());
        $this->assertSame(404, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExtendsPdpException(): void
    {
        $exception = new PdpConnectorNotFoundException('test');

        $this->assertInstanceOf(PdpException::class, $exception);
    }
}
