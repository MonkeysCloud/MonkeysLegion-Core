<?php
declare(strict_types=1);

namespace MonkeysLegion\Core\Health;

use MonkeysLegion\Core\Health\Check\DatabaseHealthCheck;
use MonkeysLegion\Core\Health\Check\CacheHealthCheck;
use MonkeysLegion\Core\Health\Check\DiskSpaceHealthCheck;

/**
 * MonKeysLegion Framework — Core Package
 *
 * Service provider that registers health check services and default checks.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class HealthCheckServiceProvider
{
    /**
     * Create and configure the HealthCheckService with default checks.
     *
     * @param array<string, mixed> $config Configuration from observability.mlc
     */
    public function createService(array $config = []): HealthCheckService
    {
        $cacheTtl = (int) ($config['cache_ttl'] ?? 5);
        $service = new HealthCheckService($cacheTtl);

        // Register default checks if enabled
        $checks = $config['checks'] ?? ['database', 'cache', 'disk_space'];

        if (in_array('disk_space', $checks, true)) {
            $path = $config['disk_path'] ?? '/';
            $threshold = (int) ($config['disk_threshold_percent'] ?? 90);
            $service->register(new DiskSpaceHealthCheck($path, $threshold));
        }

        // Database and cache checks are registered with null dependencies;
        // they will return 'degraded' if the services aren't available.
        // The DI container should override these with real instances.
        if (in_array('database', $checks, true)) {
            $service->register(new DatabaseHealthCheck(null));
        }

        if (in_array('cache', $checks, true)) {
            $service->register(new CacheHealthCheck(null));
        }

        return $service;
    }
}
