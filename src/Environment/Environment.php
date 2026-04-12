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

namespace MonkeysLegion\Core\Environment;

/**
 * Application environment as a backed enum.
 *
 * Uses PHP 8.4: backed enum with helper methods.
 */
enum Environment: string
{
    case Local      = 'local';
    case Testing    = 'testing';
    case Staging    = 'staging';
    case Production = 'production';

    /**
     * Whether the application is in a local/development environment.
     */
    public function isLocal(): bool
    {
        return $this === self::Local;
    }

    /**
     * Whether the application is running tests.
     */
    public function isTesting(): bool
    {
        return $this === self::Testing;
    }

    /**
     * Whether the application is in production.
     */
    public function isProduction(): bool
    {
        return $this === self::Production;
    }

    /**
     * Whether the application is in a debug-safe environment (local or testing).
     */
    public function isDebug(): bool
    {
        return $this === self::Local || $this === self::Testing;
    }

    /**
     * Resolve from string with fallback.
     */
    public static function detect(string $value): self
    {
        $lower = strtolower(trim($value));

        return match ($lower) {
            'production', 'prod' => self::Production,
            'staging', 'stage', 'preprod' => self::Staging,
            'testing', 'test', 'ci'       => self::Testing,
            default                        => self::Local,
        };
    }

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Local      => 'Local',
            self::Testing    => 'Testing',
            self::Staging    => 'Staging',
            self::Production => 'Production',
        };
    }
}
