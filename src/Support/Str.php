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
 * String utility class with modern PHP 8.4 features.
 *
 * SECURITY: All randomness uses CSPRNG (random_bytes/random_int).
 * PERFORMANCE: Static methods with no state, no allocations beyond results.
 */
final class Str
{
    /**
     * Convert a string to camelCase.
     */
    public static function camel(string $value): string
    {
        $studly = self::studly($value);
        return lcfirst($studly);
    }

    /**
     * Convert a string to snake_case.
     */
    public static function snake(string $value, string $delimiter = '_'): string
    {
        $result = preg_replace('/[A-Z]/', $delimiter . '$0', $value) ?? $value;
        return strtolower(ltrim($result, $delimiter));
    }

    /**
     * Convert a string to kebab-case.
     */
    public static function kebab(string $value): string
    {
        return self::snake($value, '-');
    }

    /**
     * Convert a string to StudlyCase (PascalCase).
     */
    public static function studly(string $value): string
    {
        $words = explode(' ', str_replace(['-', '_'], ' ', $value));
        return implode('', array_map('ucfirst', $words));
    }

    /**
     * Generate a URL-safe slug.
     */
    public static function slug(string $value, string $separator = '-'): string
    {
        // Transliterate to ASCII
        $value = transliterator_transliterate('Any-Latin; Latin-ASCII', $value) ?? $value;

        // Remove non-alphanumeric characters
        $value = preg_replace('/[^a-zA-Z0-9\s]/', '', $value) ?? $value;

        // Replace whitespace with separator
        $value = preg_replace('/\s+/', $separator, trim($value)) ?? $value;

        return strtolower($value);
    }

    /**
     * Generate a cryptographically secure random string.
     *
     * SECURITY: Uses random_int() which is CSPRNG-backed.
     */
    public static function random(int $length = 16): string
    {
        $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $max    = strlen($chars) - 1;
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, $max)];
        }

        return $result;
    }

    /**
     * Check if a string contains a substring (case-sensitive).
     */
    public static function contains(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a string contains all given substrings.
     */
    public static function containsAll(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (!str_contains($haystack, (string) $needle)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a string starts with a given substring.
     */
    public static function startsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && str_starts_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a string ends with a given substring.
     */
    public static function endsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && str_ends_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the portion of a string after a delimiter.
     */
    public static function after(string $subject, string $search): string
    {
        $pos = strpos($subject, $search);
        return $pos === false ? $subject : substr($subject, $pos + strlen($search));
    }

    /**
     * Get the portion of a string after the last occurrence of a delimiter.
     */
    public static function afterLast(string $subject, string $search): string
    {
        $pos = strrpos($subject, $search);
        return $pos === false ? $subject : substr($subject, $pos + strlen($search));
    }

    /**
     * Get the portion of a string before a delimiter.
     */
    public static function before(string $subject, string $search): string
    {
        $pos = strpos($subject, $search);
        return $pos === false ? $subject : substr($subject, 0, $pos);
    }

    /**
     * Get the portion of a string before the last occurrence of a delimiter.
     */
    public static function beforeLast(string $subject, string $search): string
    {
        $pos = strrpos($subject, $search);
        return $pos === false ? $subject : substr($subject, 0, $pos);
    }

    /**
     * Get the string between two delimiters.
     */
    public static function between(string $subject, string $from, string $to): string
    {
        if ($from === '' || $to === '') {
            return $subject;
        }

        return self::before(self::after($subject, $from), $to);
    }

    /**
     * Limit a string to a given number of characters.
     */
    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit) . $end;
    }

    /**
     * Generate a UUID v4.
     *
     * SECURITY: Uses random_bytes() (CSPRNG).
     */
    public static function uuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant RFC 4122

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Generate a ULID (Universally Unique Lexicographically Sortable Identifier).
     *
     * SECURITY: Uses random_bytes() (CSPRNG).
     */
    public static function ulid(): string
    {
        $time    = (int) (microtime(true) * 1000);
        $chars   = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'; // Crockford's Base32
        $result  = '';

        // Encode timestamp (first 10 chars)
        for ($i = 9; $i >= 0; $i--) {
            $result = $chars[$time % 32] . $result;
            $time   = intdiv($time, 32);
        }

        // Encode randomness (last 16 chars)
        $random = random_bytes(10);
        for ($i = 0; $i < 16; $i++) {
            $byte    = ord($random[(int) ($i / 2)]);
            $result .= $chars[($i % 2 === 0) ? ($byte >> 3) : ($byte & 0x1f)];
        }

        return $result;
    }

    /**
     * Mask a string, showing only the first N and last N characters.
     *
     * SECURITY: Useful for displaying partial tokens, API keys, etc.
     */
    public static function mask(string $value, string $character = '*', int $start = 0, ?int $length = null): string
    {
        $strLen     = mb_strlen($value);
        $maskLength = $length ?? max(0, $strLen - $start);

        if ($start >= $strLen) {
            return $value;
        }

        $masked = mb_substr($value, 0, $start)
            . str_repeat($character, $maskLength)
            . mb_substr($value, $start + $maskLength);

        return $masked;
    }

    /**
     * Count the number of words in a string.
     */
    public static function wordCount(string $value): int
    {
        return str_word_count($value);
    }

    /**
     * Limit a string by word count.
     */
    public static function words(string $value, int $words = 100, string $end = '...'): string
    {
        preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $value, $matches);

        if (!isset($matches[0]) || mb_strlen($value) === mb_strlen($matches[0])) {
            return $value;
        }

        return rtrim($matches[0]) . $end;
    }

    /**
     * Check if a string is a valid JSON string.
     */
    public static function isJson(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Check if a string is a valid UUID.
     */
    public static function isUuid(string $value): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $value,
        ) === 1;
    }

    /**
     * Get the title case of a string.
     */
    public static function title(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Reverse a string (multibyte-safe).
     */
    public static function reverse(string $value): string
    {
        $chars = mb_str_split($value);
        return implode('', array_reverse($chars));
    }

    /**
     * Pad a string to a given length.
     */
    public static function padBoth(string $value, int $length, string $pad = ' '): string
    {
        return str_pad($value, $length, $pad, STR_PAD_BOTH);
    }

    /**
     * Repeat a string N times.
     */
    public static function repeat(string $value, int $times): string
    {
        return str_repeat($value, max(0, $times));
    }

    /**
     * Replace the first occurrence of a substring.
     */
    public static function replaceFirst(string $search, string $replace, string $subject): string
    {
        $pos = strpos($subject, $search);
        if ($pos === false) {
            return $subject;
        }

        return substr_replace($subject, $replace, $pos, strlen($search));
    }

    /**
     * Replace the last occurrence of a substring.
     */
    public static function replaceLast(string $search, string $replace, string $subject): string
    {
        $pos = strrpos($subject, $search);
        if ($pos === false) {
            return $subject;
        }

        return substr_replace($subject, $replace, $pos, strlen($search));
    }
}
