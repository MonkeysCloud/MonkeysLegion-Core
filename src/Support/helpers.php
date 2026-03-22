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
            $root = dirname(__DIR__, 4);
        }

        return $path !== ''
            ? $root . DIRECTORY_SEPARATOR . ltrim($path, '/\\')
            : $root;
    }
}

/**
 * Get the value of an environment variable.
 *
 * @param string $key     The environment variable name
 * @param mixed  $default Default value if not found
 * @return mixed
 */
if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        // Convert common string representations to their proper types
        return match (strtolower((string) $value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}

/**
 * Return an absolute path relative to the storage directory.
 *
 * storage_path();           // → /full/path/to/project/storage
 * storage_path('logs');     // → /full/path/to/project/storage/logs
 */
if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        $storagePath = base_path('storage');

        return $path !== ''
            ? $storagePath . DIRECTORY_SEPARATOR . ltrim($path, '/\\')
            : $storagePath;
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$args): void
    {
        $isCli = (php_sapi_name() === 'cli' || defined('STDOUT'));
        $bt = debug_backtrace();
        $caller = $bt[0];

        foreach ($args as $arg) {
            $type = gettype($arg);
            $label = $type;

            if (is_array($arg)) $label .= ' (' . count($arg) . ')';
            elseif (is_string($arg)) $label .= ' (' . strlen($arg) . ')';

            if ($isCli) {
                // CLI Output
                echo "\033[1;33m[" . $caller['file'] . ":" . $caller['line'] . "]\033[0m" . PHP_EOL;
                echo "\033[0;32mType: $label\033[0m" . PHP_EOL;
                print_r($arg);
                echo PHP_EOL . PHP_EOL;
            } else {
                // Web Output
                echo '<div style="background:#18171b; color:#eee; padding:15px; margin:10px; border-radius:5px; border-left:5px solid #ff851b; font-family:monospace; font-size:13px; box-shadow:0 10px 15px -3px rgba(0,0,0,0.5); overflow:auto;">';
                echo '<div style="color:#666; margin-bottom:8px; border-bottom:1px solid #333; padding-bottom:4px;">' . $caller['file'] . ':' . $caller['line'] . '</div>';
                echo '<span style="color:#ff851b; font-weight:bold; display:block; margin-bottom:5px;">' . strtoupper($label) . '</span>';

                if (is_scalar($arg) || is_null($arg)) {
                    $color = match ($type) {
                        'string' => '#ce9178',
                        'integer', 'double' => '#b5cea8',
                        'boolean' => '#569cd6',
                        'NULL' => '#569cd6',
                        default => '#eee'
                    };
                    echo '<span style="color:' . $color . ';">' . htmlspecialchars(var_export($arg, true)) . '</span>';
                } else {
                    echo '<pre style="margin:0; color:#9cdcfe;">' . htmlspecialchars(print_r($arg, true)) . '</pre>';
                }
                echo '</div>';
            }
        }
        exit(1);
    }
}