<?php

namespace MonkeysLegion\Core\Provider;

use MonkeysLegion\DI\ContainerBuilder;
use MonkeysLegion\Logger\Contracts\MonkeysLoggerInterface;

interface ProviderInterface
{
    public static function register(string $root, ContainerBuilder $c): void;

    public static function setLogger(MonkeysLoggerInterface $logger): void;
}
