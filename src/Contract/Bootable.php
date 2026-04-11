<?php

declare(strict_types=1);

namespace MonkeysLegion\Core\Contract;

/**
 * Interface for providers that need a boot phase after all providers are registered.
 */
interface Bootable
{
    /**
     * Boot the provider — called after all providers have been registered.
     */
    public function boot(): void;
}
