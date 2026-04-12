<?php

declare(strict_types=1);

namespace MonkeysLegion\Core\Contract;

/**
 * Contract for rendering exceptions to output (HTTP response, CLI, etc.).
 *
 * SECURITY: Implementations MUST sanitize output in production environments.
 */
interface ExceptionRendererInterface
{
    /**
     * Render an exception for output.
     *
     * @return string|array<string, mixed> Rendered output (HTML, JSON, or array).
     */
    public function render(\Throwable $exception): string|array;

    /**
     * Whether this renderer can handle the given exception.
     */
    public function canRender(\Throwable $exception): bool;
}
