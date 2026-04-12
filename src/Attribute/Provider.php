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

namespace MonkeysLegion\Core\Attribute;

/**
 * Marks a class as a service provider discoverable by the kernel.
 *
 * Usage:
 *   #[Provider(priority: 10)]
 *   final class DatabaseProvider extends AbstractProvider { ... }
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Provider
{
    /**
     * @param int  $priority Higher = registered first.
     * @param bool $defer    Whether to defer registration until needed.
     */
    public function __construct(
        public int $priority = 0,
        public bool $defer = false,
    ) {}
}
