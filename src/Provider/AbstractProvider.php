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

namespace MonkeysLegion\Core\Provider;

use MonkeysLegion\Core\Contract\Bootable;
use Psr\Container\ContainerInterface;

/**
 * Base class for service providers with container access and helpers.
 */
abstract class AbstractProvider implements ServiceProviderInterface
{
    protected ?ContainerInterface $container = null;

    /**
     * Set the container instance.
     */
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * Get a service from the container.
     *
     * @template T
     * @param class-string<T> $id
     * @return T
     */
    protected function resolve(string $id): mixed
    {
        if ($this->container === null) {
            throw new \RuntimeException('Container not set on provider ' . static::class);
        }

        return $this->container->get($id);
    }

    /**
     * Check if a service exists in the container.
     */
    protected function bound(string $id): bool
    {
        return $this->container?->has($id) ?? false;
    }
}
