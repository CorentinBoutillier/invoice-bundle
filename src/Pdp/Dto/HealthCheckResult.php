<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Pdp\Dto;

/**
 * Result of a PDP connector health check.
 *
 * Used to verify connectivity and authentication with the PDP.
 */
readonly class HealthCheckResult
{
    /**
     * @param bool               $healthy      Whether the PDP is accessible
     * @param string             $connectorId  Connector identifier
     * @param float              $responseTimeMs Response time in milliseconds
     * @param string|null        $message      Status message
     * @param string|null        $version      PDP API version (if available)
     * @param \DateTimeImmutable $checkedAt    When the check was performed
     * @param array<string, mixed> $details    Additional diagnostic info
     */
    public function __construct(
        public bool $healthy,
        public string $connectorId,
        public float $responseTimeMs = 0.0,
        public ?string $message = null,
        public ?string $version = null,
        public ?\DateTimeImmutable $checkedAt = null,
        public array $details = [],
    ) {
    }

    /**
     * Create a healthy result.
     *
     * @param array<string, mixed> $details
     */
    public static function healthy(
        string $connectorId,
        float $responseTimeMs = 0.0,
        ?string $version = null,
        array $details = [],
    ): self {
        return new self(
            healthy: true,
            connectorId: $connectorId,
            responseTimeMs: $responseTimeMs,
            message: 'Connection successful',
            version: $version,
            checkedAt: new \DateTimeImmutable(),
            details: $details,
        );
    }

    /**
     * Create an unhealthy result.
     *
     * @param array<string, mixed> $details
     */
    public static function unhealthy(
        string $connectorId,
        string $message,
        float $responseTimeMs = 0.0,
        array $details = [],
    ): self {
        return new self(
            healthy: false,
            connectorId: $connectorId,
            responseTimeMs: $responseTimeMs,
            message: $message,
            checkedAt: new \DateTimeImmutable(),
            details: $details,
        );
    }

    /**
     * Check if response time is acceptable (under threshold).
     */
    public function isResponseTimeAcceptable(float $thresholdMs = 5000.0): bool
    {
        return $this->responseTimeMs <= $thresholdMs;
    }
}
