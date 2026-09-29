<?php
declare(strict_types=1);

namespace MonkeysLegion\Core\Health;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;
use MonkeysLegion\Router\Attributes\Route;

/**
 * MonKeysLegion Framework — Core Package
 *
 * Controller that serves health check endpoints.
 *
 * GET /health          — Simple 200/503 status (for load balancers)
 * GET /health/detailed — Full JSON breakdown of all checks
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class HealthCheckController
{
    public function __construct(
        private readonly HealthCheckService $service,
    ) {}

    #[Route('GET', '/health', name: 'health.simple')]
    public function simple(): Response
    {
        $result = $this->service->check();
        $isHealthy = $result['status'] === 'healthy';
        $status = $isHealthy ? 200 : 503;

        $body = json_encode([
            'status' => $result['status'],
        ]);

        return new Response(
            Stream::createFromString($body),
            $status,
            ['Content-Type' => 'application/json'],
        );
    }

    #[Route('GET', '/health/detailed', name: 'health.detailed')]
    public function detailed(): Response
    {
        $result = $this->service->check();
        $isHealthy = $result['status'] === 'healthy';
        $status = $isHealthy ? 200 : 503;

        $body = json_encode($result, JSON_PRETTY_PRINT);

        return new Response(
            Stream::createFromString($body),
            $status,
            ['Content-Type' => 'application/json'],
        );
    }
}
