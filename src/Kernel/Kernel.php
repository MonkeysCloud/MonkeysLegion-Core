<?php

declare(strict_types=1);

/**
 * MonkeysLegion Core v2
 *
 * @package   MonkeysLegion\Core
 * @author    MonkeysCloud <jorge@monkeys.cloud>
 * @license   MIT
 *
 * @requires  PHP 8.4
 */

namespace MonkeysLegion\Core\Kernel;

use MonkeysLegion\Core\Attribute\BootAfter;
use MonkeysLegion\Core\Attribute\Provider as ProviderAttribute;
use MonkeysLegion\Core\Contract\Bootable;
use MonkeysLegion\Core\Contract\Deferrable;
use MonkeysLegion\Core\Environment\Environment;
use MonkeysLegion\Core\Environment\EnvironmentDetector;
use MonkeysLegion\Core\Provider\AbstractProvider;
use MonkeysLegion\Core\Provider\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * Application kernel — registers providers, boots in order, lifecycle hooks.
 *
 * PERFORMANCE: Providers are registered lazily when $defer=true. Boot ordering
 * is topologically sorted once and cached.
 *
 * SECURITY: Never exposes internal state in production. Terminate callbacks
 * run in a safe try/catch envelope.
 *
 * Uses PHP 8.4: property hooks, asymmetric visibility.
 */
final class Kernel
{
    /** @var list<ServiceProviderInterface> Registered providers */
    private array $providers = [];

    /** @var list<callable> Lifecycle callbacks */
    private array $bootingCallbacks     = [];
    private array $bootedCallbacks      = [];
    private array $terminatingCallbacks = [];

    /** @var array<string, list<string>> Deferred provider map: service → provider FQCN */
    private array $deferredMap = [];

    /** @var array<string, ServiceProviderInterface> Deferred providers keyed by FQCN */
    private array $deferredProviders = [];

    private bool $_booted   = false;
    private float $_startTime;

    /**
     * Whether the kernel has booted.
     */
    public bool $isBooted {
        get => $this->_booted;
    }

    /**
     * Time the kernel was created (hrtime for precision).
     */
    public float $startTime {
        get => $this->_startTime;
    }

    /**
     * Number of registered providers.
     */
    public int $providerCount {
        get => count($this->providers);
    }

    private Environment $_environment;

    /**
     * Current application environment.
     */
    public Environment $environment {
        get => $this->_environment;
    }

    public function __construct(
        private readonly ?ContainerInterface $container = null,
        ?Environment $environment = null,
    ) {
        $this->_startTime   = hrtime(true) / 1e6; // ms
        $this->_environment = $environment ?? EnvironmentDetector::detect();
    }

    // ── Provider Registration ──────────────────────────────────

    /**
     * Register a service provider.
     */
    public function register(ServiceProviderInterface $provider): void
    {
        if ($provider instanceof AbstractProvider && $this->container !== null) {
            $provider->setContainer($this->container);
        }

        // Check for deferral
        if ($provider instanceof Deferrable) {
            foreach ($provider->provides() as $service) {
                $this->deferredMap[$service]      = $provider::class;
                $this->deferredProviders[$provider::class] = $provider;
            }
            return;
        }

        $provider->register();
        $this->providers[] = $provider;
    }

    /**
     * Register a deferred provider when its service is needed.
     */
    public function registerDeferredFor(string $service): void
    {
        if (!isset($this->deferredMap[$service])) {
            return;
        }

        $providerClass = $this->deferredMap[$service];

        if (!is_string($providerClass) || !isset($this->deferredProviders[$providerClass])) {
            return;
        }

        $provider = $this->deferredProviders[$providerClass];
        $provider->register();
        $this->providers[] = $provider;

        // If kernel already booted, boot the deferred provider immediately
        if ($this->_booted && $provider instanceof Bootable) {
            $provider->boot();
        }

        // Remove from deferred map
        unset($this->deferredProviders[$providerClass]);
        $this->deferredMap = array_filter(
            $this->deferredMap,
            fn($class) => $class !== $providerClass,
        );
    }

    // ── Boot ────────────────────────────────────────────────────

    /**
     * Boot all registered providers in topological order.
     */
    public function boot(): void
    {
        if ($this->_booted) {
            return;
        }

        // Fire booting callbacks
        foreach ($this->bootingCallbacks as $callback) {
            $callback($this);
        }

        // Sort providers by #[BootAfter] dependencies
        $sorted = $this->topologicalSort();

        // Boot each bootable provider
        foreach ($sorted as $provider) {
            if ($provider instanceof Bootable) {
                $provider->boot();
            }
        }

        $this->_booted = true;

        // Fire booted callbacks
        foreach ($this->bootedCallbacks as $callback) {
            $callback($this);
        }
    }

    /**
     * Terminate the kernel — run cleanup callbacks.
     *
     * SECURITY: Exceptions during termination are caught and silenced
     * to prevent information leaks.
     */
    public function terminate(): void
    {
        foreach ($this->terminatingCallbacks as $callback) {
            try {
                $callback($this);
            } catch (\Throwable) {
                // Silent — never let termination callbacks crash
            }
        }
    }

    // ── Lifecycle Hooks ─────────────────────────────────────────

    /**
     * Register a callback to fire during booting.
     */
    public function booting(callable $callback): void
    {
        $this->bootingCallbacks[] = $callback;
    }

    /**
     * Register a callback to fire after booting.
     */
    public function booted(callable $callback): void
    {
        $this->bootedCallbacks[] = $callback;
    }

    /**
     * Register a callback to fire during termination.
     */
    public function terminating(callable $callback): void
    {
        $this->terminatingCallbacks[] = $callback;
    }

    // ── Metrics ──────────────────────────────────────────────────

    /**
     * Get the time elapsed since kernel creation in milliseconds.
     */
    public function uptime(): float
    {
        return (hrtime(true) / 1e6) - $this->_startTime;
    }

    /**
     * Get all registered provider class names.
     *
     * @return list<string>
     */
    public function getProviderClasses(): array
    {
        return array_map(fn($p) => $p::class, $this->providers);
    }

    // ── Topological Sort ────────────────────────────────────────

    /**
     * Sort providers respecting #[BootAfter] attributes.
     *
     * PERFORMANCE: Runs once during boot — O(V + E) Kahn's algorithm
     * using SplQueue for O(1) dequeue instead of O(N) array_shift.
     *
     * SECURITY: Throws on cyclic dependencies to prevent undefined boot order.
     *
     * @return list<ServiceProviderInterface>
     * @throws \RuntimeException If a dependency cycle is detected.
     */
    private function topologicalSort(): array
    {
        // Use class name as key but append object ID to handle
        // multiple instances of the same provider class.
        /** @var array<string, ServiceProviderInterface> */
        $byClass = [];
        /** @var array<string, string> Map class FQCN → canonical key for BootAfter resolution */
        $classToKey = [];
        foreach ($this->providers as $p) {
            $key = $p::class . '#' . spl_object_id($p);
            $byClass[$key] = $p;
            // First instance wins for BootAfter dependency resolution
            $classToKey[$p::class] ??= $key;
        }

        // Build adjacency list from #[BootAfter]
        /** @var array<string, list<string>> $edges "after" → [dependsOn...] */
        $edges   = [];
        /** @var array<string, int> $inDegree */
        $inDegree = [];

        foreach ($byClass as $key => $_) {
            $edges[$key]    ??= [];
            $inDegree[$key] ??= 0;
        }

        foreach ($byClass as $key => $provider) {
            $ref   = new \ReflectionClass($provider);
            $attrs = $ref->getAttributes(BootAfter::class);

            foreach ($attrs as $attr) {
                /** @var BootAfter $bootAfter */
                $bootAfter = $attr->newInstance();
                $depClass  = $bootAfter->provider;

                // Resolve class FQCN to canonical key
                $depKey = $classToKey[$depClass] ?? null;
                if ($depKey !== null && isset($byClass[$depKey])) {
                    $edges[$depKey][] = $key;
                    $inDegree[$key]++;
                }
            }
        }

        // Kahn's algorithm with SplQueue for O(1) dequeue
        $queue  = new \SplQueue();
        $result = [];

        foreach ($inDegree as $class => $degree) {
            if ($degree === 0) {
                $queue->enqueue($class);
            }
        }

        while (!$queue->isEmpty()) {
            $current  = $queue->dequeue();
            $result[] = $byClass[$current];

            foreach ($edges[$current] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue->enqueue($neighbor);
                }
            }
        }

        // Detect cycles: if not all providers were sorted, there's a cycle
        if (count($result) < count($byClass)) {
            $sorted = [];
            foreach ($result as $p) {
                $sorted[$p::class] = true;
            }
            $unsorted = array_diff_key($byClass, $sorted);
            throw new \RuntimeException(
                'Circular boot dependency detected among providers: ' . implode(', ', array_keys($unsorted)),
            );
        }

        return $result;
    }
}
