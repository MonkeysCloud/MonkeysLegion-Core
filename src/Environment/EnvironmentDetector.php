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
 * Detects the current application environment from environment variables.
 *
 * Cascade: APP_ENV → ML_ENV → default (local).
 * Security: Never trusts user input — only reads from server environment.
 */
final class EnvironmentDetector
{
    /**
     * Detect environment from the server/system environment.
     *
     * @param string|null $override  Force a specific environment (testing only).
     */
    public static function detect(?string $override = null): Environment
    {
        if ($override !== null) {
            return Environment::detect($override);
        }

        // Cascade: APP_ENV → ML_ENV → default
        $value = $_ENV['APP_ENV']
            ?? $_SERVER['APP_ENV']
            ?? getenv('APP_ENV')
            ?: ($_ENV['ML_ENV']
                ?? $_SERVER['ML_ENV']
                ?? getenv('ML_ENV')
                ?: 'local');

        if ($value === false) {
            $value = 'local';
        }

        return Environment::detect((string) $value);
    }

    /**
     * Check if a .env file exists at the given path.
     */
    public static function hasEnvFile(string $basePath): bool
    {
        return file_exists($basePath . DIRECTORY_SEPARATOR . '.env');
    }
}
