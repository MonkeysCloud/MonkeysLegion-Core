<?php
declare(strict_types=1);

namespace MonkeysLegion\Core\Error\Renderer;

use Throwable;

/**
 * MonkeysLegion Framework — Core Package
 *
 * Contract for error renderers.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
interface ErrorRendererInterface
{
    /**
     * Render a throwable into a string.
     */
    public function render(Throwable $exception, bool $debug = false): string;

    /**
     * Get the MIME content-type for the rendered output.
     */
    public function getContentType(): string;
}
