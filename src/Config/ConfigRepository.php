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

namespace MonkeysLegion\Core\Config;

/**
 * High-performance typed configuration repository with dot-notation.
 *
 * PERFORMANCE: Uses a flat cache for O(1) dot-notation lookups after first access.
 * SECURITY: Values are stored in-memory only — never serialized to disk.
 *
 * Uses PHP 8.4: property hooks.
 */
final class ConfigRepository implements ConfigRepositoryInterface
{
    /** @var array<string, mixed> Nested config data */
    private array $items;

    /** @var array<string, mixed> Flat lookup cache for dot-notation keys */
    private array $cache = [];

    /**
     * Number of top-level config keys via property hook.
     */
    public int $count {
        get => count($this->items);
    }

    /**
     * @param array<string, mixed> $items Initial configuration.
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        // Check flat cache first (O(1) after first access)
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        // Top-level key — no dot notation needed
        if (!str_contains($key, '.')) {
            return $this->items[$key] ?? $default;
        }

        // Walk the dot-notation path
        $value = $this->items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        // Cache for future lookups
        $this->cache[$key] = $value;

        return $value;
    }

    public function has(string $key): bool
    {
        if (array_key_exists($key, $this->cache)) {
            return true;
        }

        if (!str_contains($key, '.')) {
            return array_key_exists($key, $this->items);
        }

        $value = $this->items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }
            $value = $value[$segment];
        }

        return true;
    }

    public function set(string $key, mixed $value): void
    {
        // Invalidate cache for this key and any parent keys
        foreach (array_keys($this->cache) as $cached) {
            if (str_starts_with((string) $cached, $key . '.') || $cached === $key) {
                unset($this->cache[$cached]);
            }
        }

        if (!str_contains($key, '.')) {
            $this->items[$key] = $value;
            return;
        }

        $segments = explode('.', $key);
        $current  = &$this->items;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }

    public function all(): array
    {
        return $this->items;
    }

    // ── Type-safe getters ──────────────────────────────────────

    /**
     * Get a string value.
     */
    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);
        return is_string($value) ? $value : $default;
    }

    /**
     * Get an integer value.
     */
    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);
        return is_int($value) ? $value : $default;
    }

    /**
     * Get a float value.
     */
    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->get($key, $default);
        return is_float($value) || is_int($value) ? (float) $value : $default;
    }

    /**
     * Get a boolean value.
     */
    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);
        return is_bool($value) ? $value : $default;
    }

    /**
     * Get an array value.
     *
     * @return array<mixed>
     */
    public function array(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);
        return is_array($value) ? $value : $default;
    }

    /**
     * Merge additional configuration.
     *
     * @param array<string, mixed> $items
     */
    public function merge(array $items): void
    {
        $this->items = array_replace_recursive($this->items, $items);
        $this->cache = []; // Invalidate entire cache
    }
}
