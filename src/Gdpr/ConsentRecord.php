<?php
declare(strict_types=1);

namespace MonkeysLegion\Core\Gdpr;

/**
 * MonKeysLegion Framework — Core Package
 *
 * Immutable record of a user's consent for data processing.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final readonly class ConsentRecord
{
    public function __construct(
        public int|string $userId,
        public string $purpose,
        public bool $granted,
        public float $timestamp,
        public string $policyVersion = '1.0',
    ) {}
}
