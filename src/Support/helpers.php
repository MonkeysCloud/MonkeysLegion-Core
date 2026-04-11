<?php

declare(strict_types=1);

/**
 * MonkeysLegion Core v2 — Global Helper Functions
 *
 * @package   MonkeysLegion\Core
 * @author    MonkeysCloud <jorge@monkeyscloud.com>
 * @license   MIT
 *
 * @requires  PHP 8.4
 */

// ── Path Helpers ─────────────────────────────────────────────

if (!function_exists('base_path')) {
    /**
     * Return an absolute path relative to the project root.
     *
     * SECURITY: Uses ML_BASE_PATH constant — cannot be modified at runtime.
     * Rejects path traversal sequences to prevent directory escape.
     */
    function base_path(string $path = ''): string
    {
        if (defined('ML_BASE_PATH')) {
            if (!is_string(ML_BASE_PATH)) {
                throw new \RuntimeException('ML_BASE_PATH must be a string.');
            }
            $root = ML_BASE_PATH;
        } else {
            $root = dirname(__DIR__, 3);
        }

        if ($path === '') {
            return $root;
        }

        // SECURITY: Prevent path traversal
        $normalized = str_replace('\\', '/', $path);
        if (preg_match('#(^|/)\.\.(/|$)#', $normalized)) {
            throw new \InvalidArgumentException('Path traversal detected: directory traversal sequences are not allowed.');
        }

        return $root . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}

if (!function_exists('storage_path')) {
    /**
     * Return an absolute path relative to the storage directory.
     */
    function storage_path(string $path = ''): string
    {
        $storagePath = base_path('storage');

        return $path !== ''
            ? $storagePath . DIRECTORY_SEPARATOR . ltrim($path, '/\\')
            : $storagePath;
    }
}

if (!function_exists('config_path')) {
    /**
     * Return an absolute path relative to the config directory.
     */
    function config_path(string $path = ''): string
    {
        $configPath = base_path('config');

        return $path !== ''
            ? $configPath . DIRECTORY_SEPARATOR . ltrim($path, '/\\')
            : $configPath;
    }
}

if (!function_exists('app_path')) {
    /**
     * Return an absolute path relative to the app directory.
     */
    function app_path(string $path = ''): string
    {
        $appPath = base_path('app');

        return $path !== ''
            ? $appPath . DIRECTORY_SEPARATOR . ltrim($path, '/\\')
            : $appPath;
    }
}

if (!function_exists('public_path')) {
    /**
     * Return an absolute path relative to the public directory.
     */
    function public_path(string $path = ''): string
    {
        $publicPath = base_path('public');

        return $path !== ''
            ? $publicPath . DIRECTORY_SEPARATOR . ltrim($path, '/\\')
            : $publicPath;
    }
}

if (!function_exists('resource_path')) {
    /**
     * Return an absolute path relative to the resources directory.
     */
    function resource_path(string $path = ''): string
    {
        $resourcePath = base_path('resources');

        return $path !== ''
            ? $resourcePath . DIRECTORY_SEPARATOR . ltrim($path, '/\\')
            : $resourcePath;
    }
}

// ── Environment ──────────────────────────────────────────────

if (!function_exists('env')) {
    /**
     * Get an environment variable value with type casting.
     *
     * SECURITY: Only reads from server environment, never from user input.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}

// ── Debugging ────────────────────────────────────────────────

if (!function_exists('dump')) {
    /**
     * Dump variables without terminating the script.
     */
    function dump(mixed ...$args): void
    {
        $isCli = (PHP_SAPI === 'cli' || defined('STDOUT'));

        foreach ($args as $arg) {
            if ($isCli) {
                var_export($arg);
                echo PHP_EOL;
            } else {
                echo '<pre>' . htmlspecialchars(
                    print_r($arg, true),
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8',
                ) . '</pre>';
            }
        }
    }
}

if (!function_exists('dd')) {
    /**
     * Dump variables and terminate the script.
     *
     * SECURITY: In web context, output is HTML-escaped.
     */
    function dd(mixed ...$args): never
    {
        dump(...$args);
        exit(1);
    }
}

// ── Value Helpers ────────────────────────────────────────────

if (!function_exists('value')) {
    /**
     * Return the default value of the given value.
     * Resolves closures.
     */
    function value(mixed $value, mixed ...$args): mixed
    {
        return $value instanceof \Closure ? $value(...$args) : $value;
    }
}

if (!function_exists('tap')) {
    /**
     * Call the given Closure with the value and return the value.
     *
     * Useful for side effects without breaking fluent chains.
     */
    function tap(mixed $value, ?callable $callback = null): mixed
    {
        if ($callback !== null) {
            $callback($value);
        }

        return $value;
    }
}

if (!function_exists('with')) {
    /**
     * Return the given value, optionally passed through a callback.
     */
    function with(mixed $value, ?callable $callback = null): mixed
    {
        return $callback !== null ? $callback($value) : $value;
    }
}

// ── Safety Helpers ───────────────────────────────────────────

if (!function_exists('retry')) {
    /**
     * Retry a callback a given number of times.
     *
     * PERFORMANCE: Uses exponential backoff with usleep to avoid CPU spin.
     *
     * @param int $times    Number of attempts.
     * @param callable $callback The operation to retry.
     * @param int $sleepMs  Milliseconds to wait between attempts (doubles each retry).
     * @param callable|null $when   Only retry if this returns true.
     * @throws \Throwable The last exception if all attempts fail.
     */
    function retry(int $times, callable $callback, int $sleepMs = 0, ?callable $when = null): mixed
    {
        $attempts   = 0;
        $lastException = null;

        while ($attempts < $times) {
            $attempts++;

            try {
                return $callback($attempts);
            } catch (\Throwable $e) {
                $lastException = $e;

                if ($when !== null && !$when($e)) {
                    throw $e;
                }

                if ($attempts < $times && $sleepMs > 0) {
                    usleep($sleepMs * 1000 * (int) pow(2, $attempts - 1));
                }
            }
        }

        throw $lastException ?? new \RuntimeException('Retry failed.');
    }
}

if (!function_exists('rescue')) {
    /**
     * Execute a callback and catch exceptions silently.
     *
     * SECURITY: Exceptions are swallowed — use only for non-critical operations.
     */
    function rescue(callable $callback, mixed $rescue = null, bool|callable $report = true): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            if ($report === true || (is_callable($report) && $report($e))) {
                // In a full app, this would go to the exception handler.
                // For now, trigger a user notice.
                trigger_error($e->getMessage(), E_USER_NOTICE);
            }

            return value($rescue, $e);
        }
    }
}

// ── Type Checks ──────────────────────────────────────────────

if (!function_exists('blank')) {
    /**
     * Determine if a value is "blank" (null, empty string, empty array).
     */
    function blank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return $value === [];
        }

        if ($value instanceof \Countable) {
            return count($value) === 0;
        }

        return false;
    }
}

if (!function_exists('filled')) {
    /**
     * Determine if a value is "filled" (not blank).
     */
    function filled(mixed $value): bool
    {
        return !blank($value);
    }
}

if (!function_exists('class_basename')) {
    /**
     * Get the short class name (without namespace).
     */
    function class_basename(string|object $class): string
    {
        $class = is_object($class) ? $class::class : $class;
        $pos   = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }
}

if (!function_exists('transform')) {
    /**
     * Transform a value if it is filled, otherwise return default.
     */
    function transform(mixed $value, callable $callback, mixed $default = null): mixed
    {
        if (filled($value)) {
            return $callback($value);
        }

        return value($default);
    }
}

if (!function_exists('windows_os')) {
    /**
     * Check if the current OS is Windows.
     */
    function windows_os(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }
}
