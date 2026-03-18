<?php

namespace MonkeysLegion\Core\Provider;

use MonkeysLegion\DI\ContainerBuilder;
use MonkeysLegion\Logger\Contracts\MonkeysLoggerInterface;

/**
 * Defines the contract for a provider of services or resources.
 * This interface does not serve the same purpose as the Provider attribute, 
 * but rather defines the methods that a provider class must implement.
 * In other words, while the Provider attribute is used to mark a class as a provider for the main application
 * the ProviderInterface is meant for the core internal providers that are used by the framework itself, and not meant to be implemented by the end-users (You my dear developer).
 */
interface ProviderInterface
{
    public static function register(string $root, ContainerBuilder $c): void;

    public static function setLogger(MonkeysLoggerInterface $logger): void;
}
