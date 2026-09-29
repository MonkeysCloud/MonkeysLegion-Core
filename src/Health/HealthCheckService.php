<?php
declare(strict_types=1);

namespace MonkeysLegion\Core\Health;

/**
 * MonKeysLegion Framework — Core Package
 *
 * Aggregates individual health checks and provides an overall system health status.
 * Results are cached for a configurable TTL to avoid excessive load.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class HealthCheckService
{
    /** @var array<string, HealthCheckInterface> */
    private array $checks = [];

    /** @var array{result: array<string, mixed>, time: float}|null */
    private ?array $cache = null;

    public function __construct(
        private readonly int $cacheTtlSeconds = 5,
    ) {}

    /**
     * Register a health check.
     */
    public function register(HealthCheckInterface $check): void
    {
        $this->checks[$check->name()] = $check;
        $this->cache = null; // Invalidate cache on new check
    }

    /**
     * Run all registered health checks and return aggregated results.
     *
     * @return array{
     *     status: string,
     *     timestamp: string,
     *     checks: list<array<string, mixed>>,
     *     latency_ms: float
     * }
     */
    public function check(): array
    {
        // Check cache
        if ($this->cache !== null && (microtime(true) - $this->cache['time']) < $this->cacheTtlSeconds) {
            return $this->cache['result'];
        }

        $start = microtime(true);
        $results = [];
        $overallStatus = 'healthy';

        foreach ($this->checks as $check) {
            $checkStart = microtime(true);
            try {
                $result = $check->check();
            } catch (\Throwable $e) {
                $result = new HealthCheckResult(
                    $check->name(),
                    'unhealthy',
                    'Check threw exception: ' . $e->getMessage(),
                    (microtime(true) - $checkStart) * 1000,
                );
            }
            $results[] = $result->toArray();

            // Aggregate: unhealthy takes priority over degraded, degraded over healthy
            if ($result->isUnhealthy()) {
                $overallStatus = 'unhealthy';
            } elseif ($result->isDegraded() && $overallStatus !== 'unhealthy') {
                $overallStatus = 'degraded';
            }
        }

        $totalLatency = (microtime(true) - $start) * 1000;

        $result = [
            'status'     => $overallStatus,
            'timestamp'  => date('c'),
            'checks'     => $results,
            'latency_ms' => round($totalLatency, 2),
        ];

        $this->cache = ['result' => $result, 'time' => microtime(true)];

        return $result;
    }

    /**
     * Get a quick boolean health status (for simple /health endpoint).
     */
    public function isHealthy(): bool
    {
        return $this->check()['status'] === 'healthy';
    }

    /**
     * Get the list of registered check names.
     *
     * @return list<string>
     */
    public function getCheckNames(): array
    {
        return array_keys($this->checks);
    }

    /**
     * Clear the cached result.
     */
    public function clearCache(): void
    {
        $this->cache = null;
    }
}
