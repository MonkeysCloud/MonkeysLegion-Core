<?php
declare(strict_types=1);

namespace MonkeysLegion\Core\Health;

/**
 * MonKeysLegion Framework — Core Package
 *
 * Immutable value object representing the result of a health check.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final readonly class HealthCheckResult
{
    public function __construct(
        public string  $name,
        public string  $status,
        public string  $message = '',
        public float   $latencyMs = 0.0,
        public array   $metadata = [],
    ) {}

    public function isHealthy(): bool
    {
        return $this->status === 'healthy';
    }

    public function isDegraded(): bool
    {
        return $this->status === 'degraded';
    }

    public function isUnhealthy(): bool
    {
        return $this->status === 'unhealthy';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name'       => $this->name,
            'status'     => $this->status,
            'message'    => $this->message,
            'latency_ms' => round($this->latencyMs, 2),
            'metadata'   => $this->metadata,
        ];
    }
}
