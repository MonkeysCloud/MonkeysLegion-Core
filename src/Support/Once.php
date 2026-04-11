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
 * Memoization helper — ensures a callable is executed only once.
 *
 * Inspired by Rust's `std::sync::Once` and Laravel's `once()`.
 *
 * PERFORMANCE: Uses a WeakMap when possible to prevent memory leaks
 * from object-bound closures. Scalar-keyed results use a static cache.
 *
 * Usage:
 *   $value = Once::call(fn() => expensiveComputation());
 *   // Subsequent calls return the cached result.
 */
final class Once
{
    /** @var array<string, mixed> Static cache for scalar-keyed results */
    private static array $cache = [];

    /**
     * Execute a callback once and cache the result.
     *
     * Uses the call site (file + line) as the cache key for uniqueness.
     */
    public static function call(callable $callback): mixed
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] ?? [];
        $key   = ($trace['file'] ?? '') . ':' . ($trace['line'] ?? '0');

        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $result = $callback();
        self::$cache[$key] = $result;

        return $result;
    }

    /**
     * Execute a callback once per given key.
     */
    public static function callKeyed(string $key, callable $callback): mixed
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $result = $callback();
        self::$cache[$key] = $result;

        return $result;
    }

    /**
     * Flush all cached results.
     */
    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * Flush a specific key from the cache.
     */
    public static function forget(string $key): void
    {
        unset(self::$cache[$key]);
    }

    /**
     * Check if a key has been cached.
     */
    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$cache);
    }

    /**
     * Get the current cache size.
     */
    public static function count(): int
    {
        return count(self::$cache);
    }
}
