<?php
declare(strict_types=1);

namespace MonkeysLegion\Core\Health\Check;

use MonkeysLegion\Core\Health\HealthCheckInterface;
use MonkeysLegion\Core\Health\HealthCheckResult;

/**
 * Checks available disk space on the storage path.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class DiskSpaceHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly string $path = '/',
        private readonly int $warningThresholdPercent = 90,
    ) {}

    public function name(): string
    {
        return 'disk_space';
    }

    public function check(): HealthCheckResult
    {
        $start = microtime(true);

        if (!is_dir($this->path)) {
            return new HealthCheckResult(
                'disk_space',
                'unhealthy',
                "Path does not exist: {$this->path}",
                (microtime(true) - $start) * 1000,
            );
        }

        $free = @disk_free_space($this->path);
        $total = @disk_total_space($this->path);

        if ($free === false || $total === false || $total <= 0) {
            return new HealthCheckResult(
                'disk_space',
                'unhealthy',
                'Cannot determine disk space',
                (microtime(true) - $start) * 1000,
            );
        }

        $usedPercent = round((($total - $free) / $total) * 100, 1);
        $freeGb = round($free / 1024 / 1024 / 1024, 2);

        if ($usedPercent >= $this->warningThresholdPercent) {
            return new HealthCheckResult(
                'disk_space',
                'degraded',
                "Disk usage at {$usedPercent}% ({$freeGb} GB free)",
                (microtime(true) - $start) * 1000,
                ['used_percent' => $usedPercent, 'free_gb' => $freeGb],
            );
        }

        return new HealthCheckResult(
            'disk_space',
            'healthy',
            "Disk usage at {$usedPercent}% ({$freeGb} GB free)",
            (microtime(true) - $start) * 1000,
            ['used_percent' => $usedPercent, 'free_gb' => $freeGb],
        );
    }
}
