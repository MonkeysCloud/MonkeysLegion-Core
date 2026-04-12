<?php

declare(strict_types=1);

/**
 * MonkeysLegion Core v2
 *
 * @package   MonkeysLegion\Core
 * @author    MonkeysCloud <jorge@monkeyscloud.com>
 * @license   MIT
 *
 * @requires  PHP 8.4
 */

namespace MonkeysLegion\Core\Pipeline;

/**
 * Generic pipeline for processing data through a series of stages.
 *
 * PERFORMANCE: Zero overhead beyond the stage callables themselves.
 * Uses a simple reduce with no reflection, no container resolution.
 *
 * Usage:
 *   $result = (new Pipeline())
 *       ->send($request)
 *       ->through([TrimStrings::class, ValidateInput::class])
 *       ->via('handle')  // method name on stage objects
 *       ->then(fn($req) => $handler->handle($req));
 */
final class Pipeline
{
    private mixed $passable = null;

    /** @var list<callable|object|string> */
    private array $pipes = [];

    private string $method = 'handle';

    /**
     * Set the object being sent through the pipeline.
     */
    public function send(mixed $passable): self
    {
        $clone = clone $this;
        $clone->passable = $passable;
        return $clone;
    }

    /**
     * Set the stages (pipes) to process through.
     *
     * @param list<callable|object|string> $pipes
     */
    public function through(array $pipes): self
    {
        $clone = clone $this;
        $clone->pipes = $pipes;
        return $clone;
    }

    /**
     * Add a single pipe to the pipeline.
     */
    public function pipe(callable|object|string $pipe): self
    {
        $clone = clone $this;
        $clone->pipes[] = $pipe;
        return $clone;
    }

    /**
     * Set the method to call on pipe objects (default: 'handle').
     */
    public function via(string $method): self
    {
        $clone = clone $this;
        $clone->method = $method;
        return $clone;
    }

    /**
     * Run the pipeline and return the result.
     *
     * @param callable $destination Final handler.
     */
    public function then(callable $destination): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            $destination,
        );

        return $pipeline($this->passable);
    }

    /**
     * Run the pipeline and return the passable (no destination).
     */
    public function thenReturn(): mixed
    {
        return $this->then(fn(mixed $passable) => $passable);
    }

    /**
     * Build the closure that wraps each pipe around the next.
     *
     * PERFORMANCE: The carry function resolves pipe type once per pipe,
     * not per invocation.
     */
    private function carry(): \Closure
    {
        return function (callable $next, callable|object|string $pipe): \Closure {
            return function (mixed $passable) use ($next, $pipe): mixed {
                // Callable (closure, invokable)
                if (is_callable($pipe)) {
                    return $pipe($passable, $next);
                }

                // Object with the configured method
                if (is_object($pipe) && method_exists($pipe, $this->method)) {
                    return $pipe->{$this->method}($passable, $next);
                }

                // Class string — instantiate
                if (is_string($pipe) && class_exists($pipe)) {
                    $instance = new $pipe();
                    if (method_exists($instance, $this->method)) {
                        return $instance->{$this->method}($passable, $next);
                    }
                }

                throw new \InvalidArgumentException(
                    sprintf(
                        'Pipeline pipe must be callable or have a %s() method, got %s.',
                        $this->method,
                        get_debug_type($pipe),
                    ),
                );
            };
        };
    }
}
