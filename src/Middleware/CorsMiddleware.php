<?php

declare(strict_types=1);

namespace MonkeysLegion\Core\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;

final class CorsMiddleware
{
    public function __construct(
        private string $allowOrigin  = '*',
        private string $allowMethods = 'GET,POST,PUT,DELETE,OPTIONS',
        private string $allowHeaders = 'Content-Type,Authorization'
    ) {}

    /**
     * @param ServerRequestInterface $req
     * @param callable               $next  next($req): ResponseInterface
     */
    public function __invoke(
        ServerRequestInterface $req,
        callable $next
    ): ResponseInterface {
        // Preflight
        if ($req->getMethod() === 'OPTIONS') {
            return new Response(
                204,
                [
                    'Access-Control-Allow-Origin'  => $this->allowOrigin,
                    'Access-Control-Allow-Methods' => $this->allowMethods,
                    'Access-Control-Allow-Headers' => $this->allowHeaders,
                    'Access-Control-Max-Age'       => '86400',
                ],
                Stream::createFromString('')
            );
        }

        // Call next handler
        $resp = $next($req);

        // Inject CORS headers on the way out
        return $resp
            ->withHeader('Access-Control-Allow-Origin',  $this->allowOrigin)
            ->withHeader('Access-Control-Allow-Methods', $this->allowMethods)
            ->withHeader('Access-Control-Allow-Headers', $this->allowHeaders);
    }
}