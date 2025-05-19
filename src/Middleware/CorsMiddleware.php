<?php
declare(strict_types=1);

namespace MonkeysLegion\Core\Middleware;

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
    /** @var string[]|string[]|string */
    private array|string $allowOrigin;
    /** @var string[]|string */
    private array|string $allowMethods;
    /** @var string[]|string */
    private array|string $allowHeaders;
    /** @var string[]|string|null */
    private array|string|null $exposeHeaders;

    public function __construct(
        array|string                 $allowOrigin      = '*',
        array|string                 $allowMethods     = ['GET','POST','OPTIONS'],
        array|string                 $allowHeaders     = ['Content-Type','Authorization'],
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

        // If no Origin header  →  not a CORS request – simply continue.
        if ($origin === '') {
            return $handler->handle($request);
        }

        /* -----------------------------------------------------------
         * Is the requesting origin allowed?
         * ----------------------------------------------------------- */
        if (!$this->isOriginAllowed($origin)) {
            // Spec diverges on what to do; safest is to proceed w/out CORS hdrs.
            return $handler->handle($request);
        }

        /* -----------------------------------------------------------
         * Pre-flight (OPTIONS + Access-Control-Request-Method)
         * ----------------------------------------------------------- */
        if (
            $request->getMethod() === 'OPTIONS' &&
            $request->hasHeader('Access-Control-Request-Method')
        ) {
            $response = $this->emptyResponse(204);

            $response = $this->withCommonHeaders($response, $origin)
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

            return $response;
        }

        /* -----------------------------------------------------------
         * Actual request – call next handler then append headers
         * ----------------------------------------------------------- */
        $response = $handler->handle($request);
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
        return new \MonkeysLegion\Http\Message\Response($status);
    }
}