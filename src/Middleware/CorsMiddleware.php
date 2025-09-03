<?php

declare(strict_types=1);

namespace MonkeysLegion\Core\Middleware;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Advanced CORS middleware (PSR-15).
 *
 * Features
 * --------
 * • Origin matching: wildcard “*”, exact strings, or PCRE patterns.
 * • Pre-flight handling (OPTIONS) with configurable max-age.
 * • Allows credentials & exposes headers when enabled.
 * • Adds “Vary: Origin” so caches stay safe.
 *
 * Typical wiring
 * --------------
 * $cors = new CorsMiddleware(
 *     allowOrigin: ['https://foo.com', '/^https:\/\/bar\.(dev|net)$/'],
 *     allowMethods: ['GET','POST','PATCH','DELETE','OPTIONS'],
 *     allowHeaders: ['Content-Type','Authorization','X-Requested-With'],
 *     exposeHeaders: ['X-Total-Count'],
 *     allowCredentials: true,
 *     maxAge: 86400,
 *     responseFactory: $container->get(ResponseFactoryInterface::class)
 * );
 */
final class CorsMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    private array $allowOrigin;

    /** @var string[] */
    private array $allowMethods;

    /** @var string[] */
    private array $allowHeaders;

    /** @var string[]|null */
    private ?array $exposeHeaders;

    /**
     * @param array<string>|string $allowOrigin
     * @param array<string>|string $allowMethods
     * @param array<string>|string $allowHeaders
     * @param array<string>|null $exposeHeaders
     */
    public function __construct(
        array|string                 $allowOrigin      = '*',
        array|string                 $allowMethods     = ['GET', 'POST', 'OPTIONS', 'PATCH', 'DELETE'],
        array|string                 $allowHeaders     = ['Content-Type', 'Authorization'],
        array|string|null            $exposeHeaders    = null,
        private bool                 $allowCredentials = false,
        private int                  $maxAge           = 0,
        private ?ResponseFactoryInterface $responseFactory = null
    ) {
        // Normalise scalar → array for internal use
        $this->allowOrigin   = is_array($allowOrigin)   ? $allowOrigin   : [$allowOrigin];
        $this->allowMethods  = is_array($allowMethods)  ? $allowMethods  : [$allowMethods];
        $this->allowHeaders  = is_array($allowHeaders)  ? $allowHeaders  : [$allowHeaders];
        $this->exposeHeaders = is_array($exposeHeaders) ? $exposeHeaders : ($exposeHeaders ? [$exposeHeaders] : null);
    }

    /* ─────────────────────────────────────────────────────────────── */

    public function process(
        ServerRequestInterface  $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $origin = $request->getHeaderLine('Origin');

        // If no Origin header → not a CORS request
        if ($origin === '') {
            return $handler->handle($request);
        }

        // Is the requesting origin allowed?
        if (! $this->isOriginAllowed($origin)) {
            return $handler->handle($request);
        }

        try {
            // Pre-flight (OPTIONS + Access-Control-Request-Method)
            if (
                $request->getMethod() === 'OPTIONS' &&
                $request->hasHeader('Access-Control-Request-Method')
            ) {
                $response = $this->emptyResponse(204)
                    ->withHeader(
                        'Access-Control-Allow-Methods',
                        implode(',', $this->allowMethods)
                    )
                    ->withHeader(
                        'Access-Control-Allow-Headers',
                        implode(',', $this->allowHeaders)
                    );

                if ($this->maxAge > 0) {
                    $response = $response->withHeader('Access-Control-Max-Age', (string) $this->maxAge);
                }
            } else {
                // Actual request — may throw
                $response = $handler->handle($request);
            }
        } catch (\Throwable $e) {
            $response = $this->emptyResponse(500)
                ->withHeader('Content-Type', 'application/json');

            $response->getBody()->write((string) json_encode([
                'error'   => true,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        // Now always append the common CORS headers
        $response = $this->withCommonHeaders($response, $origin);

        if ($this->exposeHeaders) {
            $response = $response->withHeader(
                'Access-Control-Expose-Headers',
                implode(',', $this->exposeHeaders)
            );
        }

        return $response;
    }

    /* ─────────────── helpers ─────────────── */

    private function isOriginAllowed(string $origin): bool
    {
        foreach ($this->allowOrigin as $rule) {
            if ($rule === '*') {
                return true;
            }
            // PCRE rule
            if ($rule !== '' && $rule[0] === '/' && preg_match($rule, $origin)) {
                return true;
            }
            // exact string match (case-sensitive, spec compliant)
            if ($rule === $origin) {
                return true;
            }
        }
        return false;
    }

    private function withCommonHeaders(ResponseInterface $resp, string $origin): ResponseInterface
    {
        $resp = $resp
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Vary', 'Origin'); // ensure caches differentiate per origin

        if ($this->allowCredentials) {
            $resp = $resp->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $resp;
    }

    private function emptyResponse(int $status = 204): ResponseInterface
    {
        if ($this->responseFactory) {
            return $this->responseFactory->createResponse($status);
        }

        // Fallback to a tiny built-in response (if your project lacks PSR-17)
        $handle = fopen('php://memory', 'r+');
        if (!$handle) throw new \RuntimeException('Failed to create temporary memory stream');
        $emptyBody = new Stream($handle);

        return new Response($emptyBody, $status);
    }
}
