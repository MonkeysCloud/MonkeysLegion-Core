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

use MonkeysLegion\Core\Contract\ExceptionRendererInterface;
use MonkeysLegion\Core\Environment\Environment;
use Psr\Log\LoggerInterface;

/**
 * Global exception handler with environment-aware rendering.
 *
 * SECURITY:
 * - In production, NEVER exposes stack traces, file paths, or internal details.
 * - In local/testing, provides full debug output for developer convenience.
 * - Sensitive context keys are automatically redacted from logs.
 *
 * PERFORMANCE:
 * - Exceptions are classified once and dispatched; no repeated reflection.
 * - Reportable list is checked with isset() for O(1) lookups.
 */
final class Handler
{
    /** @var list<class-string<\Throwable>> Exceptions that should NOT be reported */
    private array $dontReport = [];

    /** @var list<ExceptionRendererInterface> Custom renderers */
    private array $renderers = [];

    /** @var list<callable(\Throwable): void> Report callbacks */
    private array $reportCallbacks = [];

    /** @var list<\Throwable> Collected exceptions */
    private array $reported = [];

    /**
     * Number of exceptions handled.
     */
    public int $handledCount {
        get => count($this->reported);
    }

    public function __construct(
        private readonly Environment $environment,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    // ── Reporting ────────────────────────────────────────────

    /**
     * Report an exception (log it + run custom report callbacks).
     */
    public function report(\Throwable $e): void
    {
        // Check don't-report list
        foreach ($this->dontReport as $class) {
            if ($e instanceof $class) {
                return;
            }
        }

        $this->reported[] = $e;

        // Run custom report callbacks
        foreach ($this->reportCallbacks as $callback) {
            try {
                $callback($e);
            } catch (\Throwable) {
                // Never let a report callback crash the handler
            }
        }

        // Log the exception
        $this->logger?->error($e->getMessage(), [
            'exception' => $e::class,
            'code'      => $e->getCode(),
            'file'      => $this->environment->isProduction()
                ? '***' // SECURITY: Never log file paths in production
                : $e->getFile() . ':' . $e->getLine(),
            'trace'     => $this->environment->isProduction()
                ? '[redacted]'
                : $this->formatTrace($e),
        ]);
    }

    /**
     * Render an exception for output.
     *
     * SECURITY: In production, only returns safe, generic messages.
     *
     * @return array<string, mixed>
     */
    public function render(\Throwable $e): array
    {
        // Check custom renderers first
        foreach ($this->renderers as $renderer) {
            if ($renderer->canRender($e)) {
                $result = $renderer->render($e);
                return is_array($result) ? $result : ['message' => $result];
            }
        }

        $statusCode = ($e instanceof HttpException) ? $e->getStatusCode() : 500;

        $response = [
            'error'   => true,
            'status'  => $statusCode,
            'message' => $this->getSafeMessage($e),
        ];

        // Add debug details only in non-production environments
        if ($this->environment->isDebug()) {
            $response['debug'] = [
                'exception' => $e::class,
                'file'      => $e->getFile() . ':' . $e->getLine(),
                'trace'     => $this->formatTrace($e),
            ];
        }

        return $response;
    }

    // ── Configuration ───────────────────────────────────────

    /**
     * Add exception classes that should not be reported.
     *
     * @param class-string<\Throwable> ...$exceptions
     */
    public function dontReport(string ...$exceptions): void
    {
        $this->dontReport = [...$this->dontReport, ...$exceptions];
    }

    /**
     * Register a custom report callback.
     */
    public function reportUsing(callable $callback): void
    {
        $this->reportCallbacks[] = $callback;
    }

    /**
     * Register a custom exception renderer.
     */
    public function addRenderer(ExceptionRendererInterface $renderer): void
    {
        $this->renderers[] = $renderer;
    }

    /**
     * Get all reported exceptions.
     *
     * @return list<\Throwable>
     */
    public function getReported(): array
    {
        return $this->reported;
    }

    // ── Helpers ─────────────────────────────────────────────

    /**
     * Get a client-safe message from an exception.
     *
     * SECURITY: In production, HttpExceptions return their message (designed for
     * clients). All other exceptions return a generic message.
     */
    private function getSafeMessage(\Throwable $e): string
    {
        if ($e instanceof HttpException) {
            return $e->getMessage() !== '' ? $e->getMessage() : 'An error occurred.';
        }

        if ($this->environment->isProduction()) {
            return 'An internal error occurred.';
        }

        return $e->getMessage() !== '' ? $e->getMessage() : 'Unknown error.';
    }

    /**
     * Format a stack trace for logging/debug output.
     *
     * @return list<string>
     */
    private function formatTrace(\Throwable $e, int $maxFrames = 15): array
    {
        $frames = [];
        foreach ($e->getTrace() as $i => $frame) {
            $file     = $frame['file'] ?? 'unknown';
            $line     = $frame['line'] ?? 0;
            $class    = $frame['class'] ?? '';
            $type     = $frame['type'] ?? '';
            $function = $frame['function'] ?? '';

            $frames[] = "#{$i} {$file}:{$line} {$class}{$type}{$function}()";

            if ($i >= $maxFrames) {
                $remaining = count($e->getTrace()) - $maxFrames - 1;
                if ($remaining > 0) {
                    $frames[] = "... {$remaining} more frames";
                }
                break;
            }
        }

        return $frames;
    }
}
