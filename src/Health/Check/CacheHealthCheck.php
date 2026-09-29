<?php
declare(strict_types=1);

namespace MonkeysLegion\Core\Health\Check;

use MonkeysLegion\Core\Health\HealthCheckInterface;
use MonkeysLegion\Core\Health\HealthCheckResult;
use Psr\SimpleCache\CacheInterface;

/**
 * Checks cache connectivity by writing and reading a test key.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class CacheHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly ?CacheInterface $cache = null,
    ) {}

    public function name(): string
    {
        return 'cache';
    }

    public function check(): HealthCheckResult
    {
        if ($this->cache === null) {
            return new HealthCheckResult('cache', 'degraded', 'No cache configured');
        }

        $start = microtime(true);
        $testKey = 'health_check_' . uniqid();
        $testValue = 'ok';

        try {
            $this->cache->set($testKey, $testValue, 5);
            $value = $this->cache->get($testKey, '');

            if ($value !== $testValue) {
                return new HealthCheckResult(
                    'cache',
                    'unhealthy',
                    'Cache read/write mismatch: expected "ok", got "' . $value . '"',
                    (microtime(true) - $start) * 1000,
                );
            }

            $this->cache->delete($testKey);

            return new HealthCheckResult(
                'cache',
                'healthy',
                'Cache read/write OK',
                (microtime(true) - $start) * 1000,
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult(
                'cache',
                'unhealthy',
                'Cache error: ' . $e->getMessage(),
                (microtime(true) - $start) * 1000,
            );
        }
    }
}
