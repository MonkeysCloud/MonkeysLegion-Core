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
            ? ML_BASE_PATH
            : dirname(__DIR__, 4);          // Fallback for tests

        return $path
            ? $root . DIRECTORY_SEPARATOR . ltrim($path, '/\\')
            : $root;
    }
}