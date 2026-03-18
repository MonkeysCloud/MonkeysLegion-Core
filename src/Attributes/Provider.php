<?php

declare(strict_types=1);

namespace MonkeysLegion\Core\Attributes;

use Attribute;

/**
 * Indicates that the class is a provider of services or resources.
 * This attribute can be used to mark classes that are responsible for providing
 * services, resources, or functionality to other parts of the application. It can be used for
 * service initialization, dependency injection, or any other purpose where a class is meant to act as a provider.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Provider
{
}
