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
 * High-performance array utility class.
 *
 * PERFORMANCE: All methods are static with no allocations beyond the result.
 * Dot-notation functions use string splitting for O(d) where d = depth.
 */
final class Arr
{
    /**
     * Get a value from a nested array using dot-notation.
     *
     * @param array<string, mixed> $array
     */
    public static function get(array $array, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Set a value in a nested array using dot-notation.
     *
     * @param array<string, mixed> $array
     */
    public static function set(array &$array, string $key, mixed $value): void
    {
        $keys    = explode('.', $key);
        $current = &$array;
        $count   = count($keys);

        foreach ($keys as $i => $segment) {
            if ($i === $count - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }

    /**
     * Check if a key exists using dot-notation.
     *
     * @param array<string, mixed> $array
     */
    public static function has(array $array, string $key): bool
    {
        if (array_key_exists($key, $array)) {
            return true;
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return false;
            }
            $array = $array[$segment];
        }

        return true;
    }

    /**
     * Remove a key from a nested array using dot-notation.
     *
     * @param array<string, mixed> $array
     */
    public static function forget(array &$array, string $key): void
    {
        $keys    = explode('.', $key);
        $current = &$array;
        $count   = count($keys);

        foreach ($keys as $i => $segment) {
            if ($i === $count - 1) {
                unset($current[$segment]);
                return;
            }
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                return;
            }
            $current = &$current[$segment];
        }
    }

    /**
     * Flatten a multi-dimensional array into dot-notation keys.
     *
     * @param array<string, mixed> $array
     * @return array<string, mixed>
     */
    public static function dot(array $array, string $prepend = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $fullKey = $prepend !== '' ? $prepend . '.' . $key : (string) $key;

            if (is_array($value) && $value !== []) {
                $result = [...$result, ...self::dot($value, $fullKey)];
            } else {
                $result[$fullKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Un-flatten a dot-notation array into a nested structure.
     *
     * @param array<string, mixed> $array
     * @return array<string, mixed>
     */
    public static function undot(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            self::set($result, (string) $key, $value);
        }

        return $result;
    }

    /**
     * Flatten a multi-dimensional array to a single level.
     *
     * @return list<mixed>
     */
    public static function flatten(array $array, int $depth = PHP_INT_MAX): array
    {
        $result = [];

        foreach ($array as $value) {
            if (is_array($value) && $depth > 0) {
                $result = [...$result, ...self::flatten($value, $depth - 1)];
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * Get a subset of an array by keys.
     *
     * @param array<string, mixed> $array
     * @param list<string>         $keys
     * @return array<string, mixed>
     */
    public static function only(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }

    /**
     * Get an array without the specified keys.
     *
     * @param array<string, mixed> $array
     * @param list<string>         $keys
     * @return array<string, mixed>
     */
    public static function except(array $array, array $keys): array
    {
        return array_diff_key($array, array_flip($keys));
    }

    /**
     * Get the first element matching a truth test.
     */
    public static function first(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return $array === [] ? $default : reset($array);
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Get the last element matching a truth test.
     */
    public static function last(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return $array === [] ? $default : end($array);
        }

        return self::first(array_reverse($array, true), $callback, $default);
    }

    /**
     * Wrap a value in an array if it isn't one already.
     */
    public static function wrap(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    /**
     * Check if an array is associative (non-sequential keys).
     */
    public static function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Pluck a single column from a multi-dimensional array.
     *
     * @param list<array<string, mixed>> $array
     * @return list<mixed>
     */
    public static function pluck(array $array, string $key): array
    {
        return array_column($array, $key);
    }

    /**
     * Group an array by a key or callback.
     *
     * @param list<array<string, mixed>> $array
     * @return array<string, list<mixed>>
     */
    public static function groupBy(array $array, string|callable $groupBy): array
    {
        $result = [];

        foreach ($array as $item) {
            $key = is_callable($groupBy)
                ? (string) $groupBy($item)
                : (string) ($item[$groupBy] ?? '');

            $result[$key][] = $item;
        }

        return $result;
    }

    /**
     * Sort an array by a key or callback, preserving keys.
     *
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function sortBy(array $array, string|callable $sortBy, bool $descending = false): array
    {
        $callback = is_callable($sortBy)
            ? $sortBy
            : fn($item) => is_array($item) ? ($item[$sortBy] ?? null) : null;

        uasort($array, function ($a, $b) use ($callback, $descending) {
            $va = $callback($a);
            $vb = $callback($b);
            $cmp = $va <=> $vb;
            return $descending ? -$cmp : $cmp;
        });

        return $array;
    }
}
