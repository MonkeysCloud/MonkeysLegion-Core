<?php
declare(strict_types=1);

namespace MonkeysLegion\Core\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Hybrid fixed-window rate limiter.
 *
 *  • Authenticated   → keyed by user-id attribute 'uid'.
 *  • Anonymous       → keyed by client IP.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    private const CACHE_KEY = 'rate_%s';

    public function __construct(
        private ResponseFactoryInterface $responses,
        private CacheInterface           $cache,
        int                              $maxRequests = 200,
        int                              $windowSecs  = 60
    ) {
        // Defensive: never allow zero/negative limits.
        $this->maxRequests = max(1, $maxRequests);
        $this->windowSecs  = max(1, $windowSecs);
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {

        [$key, $bucket] = $this->bucket($request);

        // Reset if the window has elapsed
        if ($bucket['reset'] <= time()) {
            $bucket = ['count' => 0, 'reset' => time() + $this->windowSecs];
        }

        // Increment & persist
        $bucket['count']++;
        $this->cache->set($key, $bucket, $bucket['reset'] - time());

        // Exceeded?
        if ($bucket['count'] > $this->maxRequests) {
            $retry = $bucket['reset'] - time();
            return $this->limitExceeded($bucket, $retry);
        }

        // Pass downstream
        $response = $handler->handle($request);
        return $this->addHeaders($response, $bucket);
    }

    /* ----------------------------------------------------------------- */

    /**
     * @return array{0:string,1:array{count:int,reset:int}}
     */
    private function bucket(ServerRequestInterface $request): array
    {
        $uid = $request->getAttribute('uid');

        $key = sprintf(
            self::CACHE_KEY,
            $uid
                ? 'uid_' . $uid
                : 'ip_' . ($request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0')
        );

        /** @var array{count:int,reset:int} $bucket */
        $bucket = $this->cache->get($key, [
            'count' => 0,
            'reset' => time() + $this->windowSecs,
        ]);

        return [$key, $bucket];
    }

    private function limitExceeded(array $bucket, int $retry): ResponseInterface
    {
        return $this->responses
            ->createResponse(429, 'Too Many Requests')
            ->withHeader('Retry-After',           (string) $retry)
            ->withHeader('X-RateLimit-Limit',     (string) $this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', '0')
            ->withHeader('X-RateLimit-Reset',     (string) $bucket['reset']);
    }

    private function addHeaders(ResponseInterface $res, array $bucket): ResponseInterface
    {
        return $res
            ->withHeader('X-RateLimit-Limit',     (string) $this->maxRequests)
            ->withHeader(
                'X-RateLimit-Remaining',
                (string) max(0, $this->maxRequests - $bucket['count'])
            )
            ->withHeader('X-RateLimit-Reset',     (string) $bucket['reset']);
    }
}