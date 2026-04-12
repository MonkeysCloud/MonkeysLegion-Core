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
 * PERFORMANCE: Uses a static array cache keyed by the caller's file and line.
 *
 * Usage:
 *   $value = Once::call(fn() => expensiveComputation());
 *   // Subsequent calls from the same call site return the cached result.
 *
 * NOTE: Once::call() uses the caller's file:line as cache key. For
 * per-instance or per-context caching, use Once::callKeyed() instead.
 */
final class Once
{
    /** @var array<string, mixed> Static cache for keyed results */
    private static array $cache = [];

    /**
     * Execute a callback once per call site and cache the result.
     *
     * Uses the caller's file + line (frame[1]) as the cache key.
     *
     * CAVEAT: Different object instances calling from the same line share
     * the cache. For per-instance memoization, use callKeyed() with a
     * unique key (e.g., spl_object_id).
     */
    public static function call(callable $callback): mixed
    {
        // Frame 0 = Once::call(), Frame 1 = the actual caller
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? [];
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
