<?php
declare(strict_types=1);

namespace MonkeysLegion\Core\Gdpr;

/**
 * MonKeysLegion Framework — Core Package
 *
 * Tracks user consent for data processing (GDPR Article 7).
 *
 * Uses an in-memory store by default. For production, subclass and
 * override the storage methods to persist to a database.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
class ConsentManager
{
    /** @var array<int|string, array<string, ConsentRecord>> */
    private array $records = [];

    public function grant(int|string $userId, string $purpose, string $policyVersion = '1.0'): void
    {
        $this->records[$userId][$purpose] = new ConsentRecord(
            userId: $userId,
            purpose: $purpose,
            granted: true,
            timestamp: microtime(true),
            policyVersion: $policyVersion,
        );
    }

    public function revoke(int|string $userId, string $purpose): void
    {
        $this->records[$userId][$purpose] = new ConsentRecord(
            userId: $userId,
            purpose: $purpose,
            granted: false,
            timestamp: microtime(true),
        );
    }

    public function hasConsent(int|string $userId, string $purpose): bool
    {
        $record = $this->records[$userId][$purpose] ?? null;
        return $record !== null && $record->granted;
    }

    /**
     * Get all consent records for a user.
     *
     * @return list<ConsentRecord>
     */
    public function getRecords(int|string $userId): array
    {
        return array_values($this->records[$userId] ?? []);
    }

    /**
     * Revoke all consent for a user (right to withdraw all consent).
     */
    public function revokeAll(int|string $userId): void
    {
        foreach ($this->records[$userId] ?? [] as $purpose => $_) {
            $this->revoke($userId, $purpose);
        }
    }
}
