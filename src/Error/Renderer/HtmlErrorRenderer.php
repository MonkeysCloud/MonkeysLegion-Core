<?php
declare(strict_types=1);

namespace MonkeysLegion\Core\Error\Renderer;

use Throwable;

/**
 * MonKeysLegion Framework — Core Package
 *
 * Rich HTML error renderer for debug-mode error pages.
 *
 * Shows: exception class, message, file:line, source snippet,
 * full stack trace, request info, and redacted environment variables.
 *
 * SECURITY: In production (debug=false), shows only a generic message.
 * All user-supplied data is HTML-escaped to prevent XSS.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class HtmlErrorRenderer implements ErrorRendererInterface
{
    /** @var list<string> Env var keys to redact (matched case-insensitively). */
    private const array REDACT_KEYS = [
        'password', 'secret', 'key', 'token', 'api_key', 'app_key',
        'database_password', 'redis_password', 'mail_password',
        'aws_secret', 'stripe_secret', 'private',
    ];

    public function render(Throwable $exception, bool $debug = false): string
    {
        if (!$debug) {
            return $this->renderProduction();
        }

        return $this->renderDebug($exception);
    }

    public function getContentType(): string
    {
        return 'text/html; charset=utf-8';
    }

    // ── Debug page ──────────────────────────────────────────────

    private function renderDebug(Throwable $exception): string
    {
        $class    = htmlspecialchars(get_class($exception));
        $message  = htmlspecialchars($exception->getMessage());
        $file     = htmlspecialchars($exception->getFile());
        $line     = $exception->getLine();
        $snippet  = $this->renderSourceSnippet($exception->getFile(), $exception->getLine());
        $trace    = $this->renderStackTrace($exception);
        $previous = $this->renderPreviousExceptions($exception);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$class} — MonKeysLegion Error</title>
<style>
  :root { --bg:#1e1e2e; --surface:#313244; --text:#cdd6f4; --red:#f38ba8; --yellow:#f9e2af; --blue:#89b4fa; --green:#a6e3a1; --dim:#6c7086; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family:'SF Mono',Menlo,Consolas,monospace; background:var(--bg); color:var(--text); line-height:1.6; font-size:14px; }
  .container { max-width:1100px; margin:0 auto; padding:24px; }
  .header { background:var(--red); color:#1e1e2e; padding:20px 24px; border-radius:8px; margin-bottom:20px; }
  .header h1 { font-size:18px; font-weight:700; word-break:break-word; }
  .header .class { font-size:13px; opacity:0.8; margin-top:4px; }
  .location { background:var(--surface); padding:16px 20px; border-radius:8px; margin-bottom:20px; font-size:13px; }
  .location .file { color:var(--blue); }
  .location .line { color:var(--yellow); }
  .section { background:var(--surface); border-radius:8px; margin-bottom:20px; overflow:hidden; }
  .section h2 { background:rgba(0,0,0,0.2); padding:12px 20px; font-size:13px; text-transform:uppercase; letter-spacing:0.5px; color:var(--dim); }
  .section .body { padding:16px 20px; }
  .source { background:#181825; padding:0; font-size:13px; overflow-x:auto; }
  .source pre { padding:12px 0; counter-reset:line; }
  .source .line { display:flex; }
  .source .lineno { width:50px; text-align:right; padding:0 12px; color:var(--dim); user-select:none; }
  .source .code { white-space:pre; padding-right:20px; }
  .source .error-line { background:rgba(243,139,168,0.1); }
  .source .error-line .lineno { color:var(--red); }
  .trace { font-size:13px; }
  .trace .frame { padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.05); }
  .trace .frame:last-child { border-bottom:none; }
  .trace .num { color:var(--dim); margin-right:8px; }
  .trace .call { color:var(--blue); }
  .trace .file { color:var(--green); font-size:12px; margin-left:24px; }
  .env table { width:100%; border-collapse:collapse; font-size:13px; }
  .env td { padding:6px 12px; border-bottom:1px solid rgba(255,255,255,0.05); }
  .env .key { color:var(--yellow); white-space:nowrap; }
  .env .val { color:var(--green); word-break:break-all; }
  .env .redacted { color:var(--red); }
  .previous { border-left:3px solid var(--yellow); margin-top:12px; padding-left:16px; }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>✗ {$message}</h1>
    <div class="class">{$class}</div>
  </div>
  <div class="location">
    <span class="file">{$file}</span>:<span class="line">{$line}</span>
  </div>
  {$snippet}
  <div class="section">
    <h2>Stack Trace</h2>
    <div class="body trace">{$trace}</div>
  </div>
  {$previous}
  <div class="section">
    <h2>Environment</h2>
    <div class="body env">{$this->renderEnvironment()}</div>
  </div>
</div>
</body>
</html>
HTML;
    }

    // ── Production page ─────────────────────────────────────────

    private function renderProduction(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Server Error — MonKeysLegion</title>
<style>
  body { font-family:system-ui,sans-serif; background:#f8f9fa; color:#333; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
  .card { text-align:center; padding:40px; max-width:500px; }
  .card h1 { font-size:48px; color:#e74c3c; margin-bottom:10px; }
  .card p { color:#666; font-size:16px; line-height:1.5; }
</style>
</head>
<body>
<div class="card">
  <h1>500</h1>
  <p>Something went wrong on our end. We're working on it — please try again later.</p>
</div>
</body>
</html>
HTML;
    }

    // ── Source snippet ──────────────────────────────────────────

    private function renderSourceSnippet(string $file, int $errorLine): string
    {
        if (!is_file($file)) {
            return '';
        }

        $lines    = file($file);
        if ($lines === false) {
            return '';
        }

        $start    = max(0, $errorLine - 6);
        $end      = min(count($lines) - 1, $errorLine + 5);
        $html     = '<div class="section"><h2>Source</h2><div class="source"><pre>';

        for ($i = $start; $i <= $end; $i++) {
            $num      = $i + 1;
            $content  = htmlspecialchars(rtrim($lines[$i] ?? ''));
            $cls      = $num === $errorLine ? ' error-line' : '';
            $html    .= "<div class=\"line{$cls}\"><span class=\"lineno\">{$num}</span><span class=\"code\">{$content}</span></div>";
        }

        $html .= '</pre></div></div>';
        return $html;
    }

    // ── Stack trace ─────────────────────────────────────────────

    private function renderStackTrace(Throwable $exception): string
    {
        $frames = $exception->getTrace();
        if ($frames === []) {
            return '<em>No stack trace available.</em>';
        }

        $html = '';
        foreach ($frames as $i => $frame) {
            $num   = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $call  = htmlspecialchars(($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '{closure}'));
            $file  = htmlspecialchars($frame['file'] ?? '[internal function]');
            $line  = $frame['line'] ?? 0;

            $html .= "<div class=\"frame\">";
            $html .= "<span class=\"num\">{$num}</span><span class=\"call\">{$call}()</span>";
            $html .= "<div class=\"file\">{$file}:{$line}</div>";
            $html .= "</div>";
        }

        return $html;
    }

    // ── Previous exceptions ─────────────────────────────────────

    private function renderPreviousExceptions(Throwable $exception): string
    {
        $html     = '';
        $current  = $exception->getPrevious();
        $depth    = 0;

        while ($current !== null && $depth < 10) {
            $depth++;
            $class   = htmlspecialchars(get_class($current));
            $msg     = htmlspecialchars($current->getMessage());
            $file    = htmlspecialchars($current->getFile());
            $line    = $current->getLine();

            $html .= "<div class=\"section\"><h2>Caused By ({$depth})</h2><div class=\"body\">";
            $html .= "<div class=\"previous\">";
            $html .= "<strong>{$class}</strong>: {$msg}<br>";
            $html .= "<span style=\"color:var(--blue)\">{$file}</span>:<span style=\"color:var(--yellow)\">{$line}</span>";
            $html .= "</div></div></div>";

            $current = $current->getPrevious();
        }

        return $html;
    }

    // ── Environment variables (redacted) ───────────────────────

    private function renderEnvironment(): string
    {
        if (empty($_SERVER)) {
            return '<em>No environment data.</em>';
        }

        $html   = '<table>';
        $sorted = $_SERVER;
        ksort($sorted);

        foreach ($sorted as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $key    = htmlspecialchars((string) $key);
            $redact = $this->shouldRedact($key);

            if ($redact) {
                $html .= "<tr><td class=\"key\">{$key}</td><td class=\"val redacted\">•••••••• (redacted)</td></tr>";
            } else {
                $val = htmlspecialchars((string) $value);
                if (strlen($val) > 200) {
                    $val = substr($val, 0, 200) . '...';
                }
                $html .= "<tr><td class=\"key\">{$key}</td><td class=\"val\">{$val}</td></tr>";
            }
        }

        $html .= '</table>';
        return $html;
    }

    private function shouldRedact(string $key): bool
    {
        $lower = strtolower($key);
        foreach (self::REDACT_KEYS as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }
        return false;
    }
}
