<?php

namespace MonkeysLegion\Core\Provider;

use MonkeysLegion\Core\Contracts\FrameworkLoggerInterface;
use MonkeysLegion\DI\ContainerBuilder;

interface ProviderInterface
{
    public static function register(string $root, ContainerBuilder $c): void;

    public static function setLogger(FrameworkLoggerInterface $logger): void;
}
