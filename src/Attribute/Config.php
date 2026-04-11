<?php

declare(strict_types=1);

/**
 * MonkeysLegion Core v2
 *
 * @package   MonkeysLegion\Core
 * @author    MonkeysCloud <jorge@monkeyscloud.com>
 * @license   MIT
 *
 * @requires  PHP 8.4
 */

namespace MonkeysLegion\Core\Attribute;

/**
 * Typed config injection via attribute.
 *
 * NOVEL: Injects configuration values directly into properties
 * without manual container wiring.
 *
 * Usage:
 *   class PaymentService {
 *       #[Config(key: 'services.stripe.secret')]
 *       private string $stripeSecret;
 *   }
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class Config
{
    /**
     * @param string $key     Dot-notation config key.
     * @param mixed  $default Fallback value if key is not found.
     */
    public function __construct(
        public string $key,
        public mixed $default = null,
    ) {}
}
