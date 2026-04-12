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

namespace MonkeysLegion\Core\Clock;

use Psr\Clock\ClockInterface;

/**
 * PSR-20 compliant system clock.
 *
 * PERFORMANCE: Zero overhead — simply wraps DateTimeImmutable.
 * Testable: Inject FrozenClock in tests.
 */
final class SystemClock implements ClockInterface
{
    public function __construct(
        private readonly \DateTimeZone $timezone = new \DateTimeZone('UTC'),
    ) {}

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', $this->timezone);
    }
}
