<?php
declare(strict_types=1);

namespace MonkeysLegion\Core\Health;

/**
 * MonKeysLegion Framework — Core Package
 *
 * Interface for individual health checks.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
interface HealthCheckInterface
{
    /**
     * The human-readable name of this check (e.g., "database", "cache").
     */
    public function name(): string;

    /**
     * Run the health check and return the result.
     */
    public function check(): HealthCheckResult;
}
