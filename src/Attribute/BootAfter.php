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
 * Declares boot dependency ordering between providers.
 *
 * NOVEL: Not available in Laravel or Symfony.
 *
 * Usage:
 *   #[BootAfter(DatabaseProvider::class)]
 *   #[Provider]
 *   final class AuthProvider extends AbstractProvider { ... }
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class BootAfter
{
    /**
     * @param string $provider FQCN of the provider that must boot first.
     */
    public function __construct(
        public string $provider,
    ) {}
}
