<?php
declare(strict_types=1);

namespace MonkeysLegion\Core\Tests\Unit\Health;

use MonkeysLegion\Core\Health\Check\DiskSpaceHealthCheck;
use MonkeysLegion\Core\Health\HealthCheckResult;
use MonkeysLegion\Core\Health\HealthCheckService;
use MonkeysLegion\Core\Health\HealthCheckInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the HealthCheckService.
 */
final class HealthCheckServiceTest extends TestCase
{
    #[Test]
    public function empty_service_returns_healthy(): void
    {
        $service = new HealthCheckService(0);
        $result = $service->check();

        self::assertSame('healthy', $result['status']);
        self::assertEmpty($result['checks']);
    }

    #[Test]
    public function registers_and_runs_checks(): void
    {
        $service = new HealthCheckService(0);
        $service->register(new AlwaysHealthyCheck('database'));
        $service->register(new AlwaysHealthyCheck('cache'));

        $result = $service->check();

        self::assertSame('healthy', $result['status']);
        self::assertCount(2, $result['checks']);
    }

    #[Test]
    public function unhealthy_check_makes_overall_unhealthy(): void
    {
        $service = new HealthCheckService(0);
        $service->register(new AlwaysHealthyCheck('database'));
        $service->register(new AlwaysUnhealthyCheck('redis'));

        $result = $service->check();

        self::assertSame('unhealthy', $result['status']);
    }

    #[Test]
    public function degraded_check_makes_overall_degraded(): void
    {
        $service = new HealthCheckService(0);
        $service->register(new AlwaysHealthyCheck('database'));
        $service->register(new AlwaysDegradedCheck('cache'));

        $result = $service->check();

        self::assertSame('degraded', $result['status']);
    }

    #[Test]
    public function unhealthy_takes_priority_over_degraded(): void
    {
        $service = new HealthCheckService(0);
        $service->register(new AlwaysDegradedCheck('cache'));
        $service->register(new AlwaysUnhealthyCheck('redis'));

        $result = $service->check();

        self::assertSame('unhealthy', $result['status']);
    }

    #[Test]
    public function check_exceptions_are_caught(): void
    {
        $service = new HealthCheckService(0);
        $service->register(new ThrowingCheck('broken'));

        $result = $service->check();

        self::assertSame('unhealthy', $result['status']);
        self::assertStringContainsString('Check threw exception', $result['checks'][0]['message']);
    }

    #[Test]
    public function results_are_cached(): void
    {
        $service = new HealthCheckService(cacheTtlSeconds: 10);
        $check = new CountingCheck('counter');
        $service->register($check);

        // First check — runs the check
        $result1 = $service->check();
        self::assertSame(1, $check->count);

        // Second check — should use cache
        $result2 = $service->check();
        self::assertSame(1, $check->count); // count didn't increase

        // Same result
        self::assertSame($result1, $result2);
    }

    #[Test]
    public function cache_can_be_cleared(): void
    {
        $service = new HealthCheckService(cacheTtlSeconds: 10);
        $check = new CountingCheck('counter');
        $service->register($check);

        $service->check();
        self::assertSame(1, $check->count);

        $service->clearCache();
        $service->check();
        self::assertSame(2, $check->count);
    }

    #[Test]
    public function disk_space_check_returns_result(): void
    {
        $check = new DiskSpaceHealthCheck(sys_get_temp_dir(), 99);
        $result = $check->check();

        self::assertContains($result->status, ['healthy', 'degraded']);
    }

    #[Test]
    public function is_healthy_returns_boolean(): void
    {
        $service = new HealthCheckService(0);
        $service->register(new AlwaysHealthyCheck('db'));

        self::assertTrue($service->isHealthy());

        $service->register(new AlwaysUnhealthyCheck('redis'));
        self::assertFalse($service->isHealthy());
    }
}

// ── Test Fixtures ──────────────────────────────────────────────

final class AlwaysHealthyCheck implements HealthCheckInterface
{
    public function __construct(private readonly string $name) {}

    public function name(): string { return $this->name; }

    public function check(): HealthCheckResult
    {
        return new HealthCheckResult($this->name, 'healthy', 'OK', 0.1);
    }
}

final class AlwaysUnhealthyCheck implements HealthCheckInterface
{
    public function __construct(private readonly string $name) {}

    public function name(): string { return $this->name; }

    public function check(): HealthCheckResult
    {
        return new HealthCheckResult($this->name, 'unhealthy', 'FAILED');
    }
}

final class AlwaysDegradedCheck implements HealthCheckInterface
{
    public function __construct(private readonly string $name) {}

    public function name(): string { return $this->name; }

    public function check(): HealthCheckResult
    {
        return new HealthCheckResult($this->name, 'degraded', 'Slow');
    }
}

final class ThrowingCheck implements HealthCheckInterface
{
    public function __construct(private readonly string $name) {}

    public function name(): string { return $this->name; }

    public function check(): HealthCheckResult
    {
        throw new \RuntimeException('Connection refused');
    }
}

final class CountingCheck implements HealthCheckInterface
{
    public int $count = 0;

    public function __construct(private readonly string $name) {}

    public function name(): string { return $this->name; }

    public function check(): HealthCheckResult
    {
        $this->count++;
        return new HealthCheckResult($this->name, 'healthy', "Run #{$this->count}");
    }
}
