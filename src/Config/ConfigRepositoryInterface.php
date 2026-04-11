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
 * Contract for typed configuration access with dot-notation.
 */
interface ConfigRepositoryInterface
{
    /**
     * Get a configuration value.
     *
     * @param string $key     Dot-notation key (e.g., 'database.host').
     * @param mixed  $default Fallback value.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Check if a key exists.
     */
    public function has(string $key): bool;

    /**
     * Set a configuration value (runtime only — not persisted).
     */
    public function set(string $key, mixed $value): void;

    /**
     * Get all configuration as a flat array.
     *
     * @return array<string, mixed>
     */
    public function all(): array;
}
