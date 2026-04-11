<?php

declare(strict_types=1);

/**
 * MonkeysLegion Core v2
 *
 * @package   MonkeysLegion\Core
 * @author    MonkeysCloud <jorge@monkeyscloud.com>
 * @license   MIT
 *
 * @requires  PHP 8.4
 */

namespace MonkeysLegion\Core\Support;

/**
 * Micro-benchmark utility for measuring execution time and memory.
 *
 * PERFORMANCE: Uses hrtime() for nanosecond precision.
 *
 * Usage:
 *   $ms = Benchmark::measure(fn() => expensiveOperation());
 *   [$ms, $result] = Benchmark::measureWithResult(fn() => compute());
 */
final class Benchmark
{
    /**
     * Measure execution time of a callable in milliseconds.
     */
    public static function measure(callable $callback, int $iterations = 1): float
    {
        $start = hrtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $callback();
        }

        $elapsed = (hrtime(true) - $start) / 1e6; // ns → ms

        return $iterations > 1 ? $elapsed / $iterations : $elapsed;
    }

    /**
     * Measure execution time and return the result.
     *
     * @return array{0: float, 1: mixed} [duration_ms, result]
     */
    public static function measureWithResult(callable $callback): array
    {
        $start  = hrtime(true);
        $result = $callback();
        $elapsed = (hrtime(true) - $start) / 1e6;

        return [$elapsed, $result];
    }

    /**
     * Measure memory usage of a callable in bytes.
     */
    public static function memory(callable $callback): int
    {
        $before = memory_get_usage(true);
        $callback();
        $after = memory_get_usage(true);

        return $after - $before;
    }

    /**
     * Run multiple callables and compare their execution times.
     *
     * @param array<string, callable> $benchmarks Named callables.
     * @param int $iterations Number of iterations per benchmark.
     * @return array<string, float> Name → average ms.
     */
    public static function compare(array $benchmarks, int $iterations = 100): array
    {
        $results = [];

        foreach ($benchmarks as $name => $callback) {
            $results[$name] = self::measure($callback, $iterations);
        }

        // Sort fastest first
        asort($results);

        return $results;
    }

    /**
     * Format a duration in milliseconds to a human-readable string.
     */
    public static function format(float $ms): string
    {
        if ($ms < 1.0) {
            return round($ms * 1000, 2) . 'μs';
        }

        if ($ms < 1000.0) {
            return round($ms, 2) . 'ms';
        }

        return round($ms / 1000, 3) . 's';
    }
}
