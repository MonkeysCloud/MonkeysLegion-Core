<?php
declare(strict_types=1);

namespace MonkeysLegion\Core\Tests\Unit\Error;

use MonkeysLegion\Core\Error\Renderer\HtmlErrorRenderer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HtmlErrorRendererTest extends TestCase
{
    private HtmlErrorRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new HtmlErrorRenderer();
    }

    #[Test]
    public function content_type_is_html(): void
    {
        self::assertSame('text/html; charset=utf-8', $this->renderer->getContentType());
    }

    #[Test]
    public function debug_mode_shows_exception_class_and_message(): void
    {
        $exception = new \RuntimeException('Test error message');

        $html = $this->renderer->render($exception, true);

        self::assertStringContainsString('RuntimeException', $html);
        self::assertStringContainsString('Test error message', $html);
        self::assertStringContainsString('<!DOCTYPE html>', $html);
    }

    #[Test]
    public function debug_mode_shows_file_and_line(): void
    {
        $exception = new \RuntimeException('Oops');

        $html = $this->renderer->render($exception, true);

        self::assertStringContainsString('HtmlErrorRendererTest.php', $html);
        // Line number should appear in the location section
        self::assertMatchesRegularExpression('/:\d+/', $html);
    }

    #[Test]
    public function debug_mode_shows_stack_trace(): void
    {
        $exception = new \RuntimeException('Trace test');

        $html = $this->renderer->render($exception, true);

        self::assertStringContainsString('Stack Trace', $html);
    }

    #[Test]
    public function production_mode_hides_exception_details(): void
    {
        $exception = new \RuntimeException('Secret internal error');

        $html = $this->renderer->render($exception, false);

        self::assertStringNotContainsString('Secret internal error', $html);
        self::assertStringNotContainsString('RuntimeException', $html);
        self::assertStringContainsString('500', $html);
    }

    #[Test]
    public function production_mode_shows_generic_message(): void
    {
        $exception = new \RuntimeException('Internal details');

        $html = $this->renderer->render($exception, false);

        self::assertStringContainsString('Something went wrong', $html);
    }

    #[Test]
    public function html_escapes_message_to_prevent_xss(): void
    {
        $exception = new \RuntimeException('<script>alert("xss")</script>');

        $html = $this->renderer->render($exception, true);

        self::assertStringNotContainsString('<script>alert', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    #[Test]
    public function redacts_sensitive_environment_variables(): void
    {
        // Use values that won't appear in the source code snippet of this test file
        $secretValue = 's' . 'e' . 'c' . 'r' . 'e' . 't' . '-' . 'val';
        $passwordValue = 'p' . 'a' . 's' . 's' . '-' . 'val';
        $_SERVER['APP_KEY'] = $secretValue;
        $_SERVER['DATABASE_PASSWORD'] = $passwordValue;
        $_SERVER['NORMAL_VAR'] = 'visible-value';

        $html = $this->renderer->render(new \RuntimeException('Redact test'), true);

        // The env table should redact sensitive keys
        self::assertStringNotContainsString($secretValue, $html);
        self::assertStringNotContainsString($passwordValue, $html);
        self::assertStringContainsString('redacted', $html);
        self::assertStringContainsString('visible-value', $html);

        // Cleanup
        unset($_SERVER['APP_KEY'], $_SERVER['DATABASE_PASSWORD'], $_SERVER['NORMAL_VAR']);
    }

    #[Test]
    public function shows_previous_exceptions_chain(): void
    {
        $previous  = new \InvalidArgumentException('Root cause');
        $exception = new \RuntimeException('Wrapper', 0, $previous);

        $html = $this->renderer->render($exception, true);

        self::assertStringContainsString('Caused By', $html);
        self::assertStringContainsString('InvalidArgumentException', $html);
        self::assertStringContainsString('Root cause', $html);
    }

    #[Test]
    public function source_snippet_shows_surrounding_lines(): void
    {
        $exception = new \RuntimeException('Source test');

        $html = $this->renderer->render($exception, true);

        // Should contain a Source section with source code
        self::assertStringContainsString('Source', $html);
        self::assertStringContainsString('source', $html);
    }
}
