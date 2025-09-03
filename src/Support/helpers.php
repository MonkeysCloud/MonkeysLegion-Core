<?php

declare(strict_types=1);

/**
 * Return an absolute path relative to the project root.
 *
 * base_path();                    // → /full/path/to/project
 * base_path('var/migrations');    // → /full/path/to/project/var/migrations
 */
if (! function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        if (defined('ML_BASE_PATH')) {
            if (!is_string(ML_BASE_PATH)) {
                throw new \RuntimeException('ML_BASE_PATH must be a string');
            }
            $root = ML_BASE_PATH;
        } else {
            $root = dirname(__DIR__, 4); // Fallback for tests
        }

        return $path !== ''
            ? $root . DIRECTORY_SEPARATOR . ltrim($path, '/\\')
            : $root;
    }
}

if (!function_exists('dd')) {
    /**
     * Dump the given variables and terminate the script.
     *
     * @param mixed ...$args
     */
    function dd(mixed ...$args): void
    {
        $isCli = (php_sapi_name() === 'cli' || defined('STDOUT'));
        foreach ($args as $arg) {
            if (is_array($arg) || is_object($arg)) {
                if ($isCli) {
                    // CLI: plain text output
                    print_r($arg);
                } else {
                    // Web: HTML output, escape for safety
                    echo '<pre>' . htmlspecialchars(print_r($arg, true), ENT_QUOTES, 'UTF-8') . '</pre>';
                }
            } elseif (is_scalar($arg) || $arg === null) {
                if ($isCli) {
                    // CLI: plain text output
                    var_export($arg);
                    echo PHP_EOL;
                } else {
                    // Web: escape output
                    echo htmlspecialchars((string)$arg, ENT_QUOTES, 'UTF-8');
                }
            } else {
                // fallback for resources or unknown types
                if ($isCli) {
                    echo gettype($arg) . PHP_EOL;
                } else {
                    echo '<pre>' . htmlspecialchars(gettype($arg), ENT_QUOTES, 'UTF-8') . '</pre>';
                }
            }
        }

        exit(1);
    }
}
