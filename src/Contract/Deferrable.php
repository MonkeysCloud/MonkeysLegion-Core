<?php

declare(strict_types=1);

namespace MonkeysLegion\Core\Contract;

/**
 * Interface for providers that should be lazily loaded.
 *
 * Deferred providers are not registered until one of their
 * provided services is actually requested from the container.
 */
interface Deferrable
{
    /**
     * Get the services provided by this provider.
     *
     * @return list<string> List of service FQCNs or identifiers.
     */
    public function provides(): array;
}
