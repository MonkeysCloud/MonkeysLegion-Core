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
        // Project root is defined once in bootstrap.php
        $root = defined('ML_BASE_PATH')
            ?  (is_string(ML_BASE_PATH) ? ML_BASE_PATH : var_export(ML_BASE_PATH, true))
            : dirname(__DIR__, 4);          // Fallback for tests

        return $path
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
        foreach ($args as $arg) {
            if (is_array($arg) || is_object($arg)) {
                echo '<pre>' . print_r($arg, true) . '</pre>';
            } elseif (is_scalar($arg) || $arg === null) {
                // safe conversion for int, float, bool, string, null
                echo htmlspecialchars((string)$arg, ENT_QUOTES, 'UTF-8');
            } else {
                // fallback for resources or unknown types
                echo '<pre>' . gettype($arg) . '</pre>';
            }
        }

        exit(1);
    }
}
