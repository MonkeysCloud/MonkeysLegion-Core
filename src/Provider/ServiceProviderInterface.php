<?php

declare(strict_types=1);

namespace MonkeysLegion\Core\Provider;

/**
 * Modern service provider interface.
 *
 * Providers register services into a PSR-11 container and optionally
 * boot after all providers have been registered.
 */
interface ServiceProviderInterface
{
    /**
     * Register bindings into the container.
     */
    public function register(): void;
}
