<?php
declare(strict_types=1);

namespace MonkeysLegion\Core\Health\Check;

use MonkeysLegion\Core\Health\HealthCheckInterface;
use MonkeysLegion\Core\Health\HealthCheckResult;
use PDO;
use PDOException;

/**
 * Checks database connectivity with a lightweight SELECT 1.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class DatabaseHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly ?PDO $pdo = null,
    ) {}

    public function name(): string
    {
        return 'database';
    }

    public function check(): HealthCheckResult
    {
        if ($this->pdo === null) {
            return new HealthCheckResult('database', 'degraded', 'No database connection configured');
        }

        $start = microtime(true);
        try {
            $this->pdo->query('SELECT 1');
            return new HealthCheckResult(
                'database',
                'healthy',
                'Database connection OK',
                (microtime(true) - $start) * 1000,
            );
        } catch (PDOException $e) {
            return new HealthCheckResult(
                'database',
                'unhealthy',
                'Database query failed: ' . $e->getMessage(),
                (microtime(true) - $start) * 1000,
            );
        }
    }
}
