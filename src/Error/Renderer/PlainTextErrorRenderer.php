<?php

declare(strict_types=1);

namespace MonkeysLegion\Core\Error\Renderer;

use MonkeysLegion\Cli\Console\Traits\Cli;

/**
 * MonkeysLegion Framework — Core Package
 *
 * Plain-text error renderer for CLI and API debugging.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
class PlainTextErrorRenderer implements ErrorRendererInterface
{
    use Cli;
    public function render(\Throwable $exception, bool $debug = false): string
    {
        $useColor = $this->supportsColor();
        $output = "";
        $current = $exception;
        $index = 0;

        while ($current !== null) {
            if ($index > 0) {
                $output .= "\n" . ($useColor ? "\e[1;34m" : "") . str_repeat('┈', 80) . ($useColor ? "\e[0m" : "") . "\n";
                $output .= ($useColor ? "\e[1;33m" : "") . "  CAUSED BY:" . ($useColor ? "\e[0m" : "") . "\n";
            }

            // Header Box
            $className = get_class($current);
            $message = $debug ? $current->getMessage() : 'An unexpected error occurred.';

            $output .= "\n";
            $output .= ($useColor ? "\e[1;41;37m" : "") . " " . $className . " " . ($useColor ? "\e[0m" : "") . "\n";
            $output .= ($useColor ? "\e[1m" : "") . " " . $message . ($useColor ? "\e[0m" : "") . "\n\n";

            if ($debug) {
                $file = $current->getFile();
                $line = $current->getLine();

                $output .= ($useColor ? "\e[38;5;244m" : "") . "  at " . ($useColor ? "\e[38;5;250m" : "") . $file . ($useColor ? "\e[1;31m" : "") . ":" . $line . ($useColor ? "\e[0m" : "") . "\n\n";

                // Code Snippet
                $snippet = $this->getCodeSnippet($file, $line, $useColor);
                if ($snippet) {
                    $output .= $snippet . "\n";
                }

                $output .= ($useColor ? "\e[1;30m" : "") . "  Stack Trace:" . ($useColor ? "\e[0m" : "") . "\n";
                $output .= $this->formatStackTrace($current, $useColor);
            }

            $current = $current->getPrevious();
            $index++;

            if (!$debug) {
                break;
            }
        }

        return $output . "\n";
    }

    public function getContentType(): string
    {
        return 'text/plain';
    }

    private function supportsColor(): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return false !== getenv('ANSICON') || 'ON' === getenv('ConEmuANSI') || 'xterm' === getenv('TERM');
        }

        if (function_exists('posix_isatty')) {
            return @posix_isatty(STDOUT);
        }

        return true;
    }

    private function getCodeSnippet(string $file, int $errorLine, bool $useColor): string
    {
        if (!is_file($file) || !is_readable($file)) {
            return '';
        }

        $lines = file($file);
        if ($lines === false) {
            return '';
        }

        $start = max(0, $errorLine - 4);
        $end = min(count($lines), $errorLine + 3);
        $snippet = "";

        for ($i = $start; $i < $end; $i++) {
            $currentLine = $i + 1;
            $content = rtrim($lines[$i]);
            $isError = $currentLine === $errorLine;

            $lineNumStr = str_pad((string)$currentLine, 5, ' ', STR_PAD_LEFT);

            if ($isError) {
                $snippet .= ($useColor ? "\e[48;5;52;38;5;255m" : "> ") . $lineNumStr . "▕ " . $content . ($useColor ? " \e[0m" : "") . "\n";
            } else {
                $snippet .= ($useColor ? "\e[38;5;239m" : "  ") . $lineNumStr . "▕ " . ($useColor ? "\e[38;5;248m" : "") . $content . ($useColor ? "\e[0m" : "") . "\n";
            }
        }

        return $snippet;
    }

    private function formatStackTrace(\Throwable $e, bool $useColor): string
    {
        $trace = "";
        $frames = $e->getTrace();

        foreach ($frames as $i => $frame) {
            $num = str_pad((string)$i, 2, ' ', STR_PAD_LEFT);
            $file = $frame['file'] ?? '[internal function]';
            $line = isset($frame['line']) ? ":" . $frame['line'] : "";
            $call = (isset($frame['class']) ? $frame['class'] . $frame['type'] : "") . $frame['function'];

            $trace .= ($useColor ? "\e[38;5;239m" : "") . "   {$num} " . ($useColor ? "\e[38;5;110m" : "") . "{$call}()" . ($useColor ? "\e[0m" : "") . "\n";
            $trace .= ($useColor ? "\e[38;5;239m" : "") . "      {$file}{$line}" . ($useColor ? "\e[0m" : "") . "\n";
        }

        return $trace;
    }
}
