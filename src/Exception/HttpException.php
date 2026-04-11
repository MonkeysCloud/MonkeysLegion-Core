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

namespace MonkeysLegion\Core\Exception;

/**
 * HTTP-aware exception with status code and headers.
 *
 * SECURITY: The $message is intended for the client. Internal details
 * should be passed via $previous or logged separately.
 */
class HttpException extends \RuntimeException
{
    /** @var array<string, string> Additional HTTP headers */
    private readonly array $headers;

    /**
     * @param int             $statusCode HTTP status code.
     * @param string          $message    Client-safe error message.
     * @param \Throwable|null $previous   Internal exception (never exposed to client).
     * @param array<string, string> $headers Additional response headers.
     */
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        ?\Throwable $previous = null,
        array $headers = [],
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->headers = $headers;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    // ── Static Factories ─────────────────────────────────────

    public static function notFound(string $message = 'Not Found'): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = 'Forbidden'): self
    {
        return new self(403, $message);
    }

    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return new self(401, $message, headers: ['WWW-Authenticate' => 'Bearer']);
    }

    public static function badRequest(string $message = 'Bad Request'): self
    {
        return new self(400, $message);
    }

    public static function conflict(string $message = 'Conflict'): self
    {
        return new self(409, $message);
    }

    public static function unprocessable(string $message = 'Unprocessable Entity'): self
    {
        return new self(422, $message);
    }

    public static function tooManyRequests(int $retryAfter = 60): self
    {
        return new self(429, 'Too Many Requests', headers: [
            'Retry-After' => (string) $retryAfter,
        ]);
    }

    public static function serverError(string $message = 'Internal Server Error', ?\Throwable $previous = null): self
    {
        return new self(500, $message, $previous);
    }

    public static function serviceUnavailable(int $retryAfter = 300): self
    {
        return new self(503, 'Service Unavailable', headers: [
            'Retry-After' => (string) $retryAfter,
        ]);
    }
}
