<?php
declare(strict_types=1);

namespace MonkeysLegion\Core\Gdpr;



/**
 * MonKeysLegion Framework — Core Package
 *
 * Collects all personal data associated with a user for GDPR data export
 * (Article 15 — Right of access).
 *
 * Register entity providers via addProvider() to collect data from
 * multiple sources. Each provider returns an associative array of
 * user-related data.
 *
 * @copyright 2026 MonKeysCloud Team
 * @license   MIT
 */
final class DataExporter
{
    /** @var array<string, callable(int|string): array<string, mixed>> */
    private array $providers = [];

    /**
     * Register a data provider for a named section.
     *
     * @param string $sectionName Section key in the export (e.g. 'profile', 'orders').
     * @param callable(int|string): array<string, mixed> $provider
     */
    public function addProvider(string $sectionName, callable $provider): void
    {
        $this->providers[$sectionName] = $provider;
    }

    /**
     * Export all personal data for a user.
     *
     * @param int|string $userId The user identifier.
     *
     * @return array<string, array<string, mixed>>
     */
    public function export(int|string $userId): array
    {
        $data = [
            '_meta' => [
                'user_id'   => $userId,
                'exported_at' => date('c'),
                'framework' => 'MonKeysLegion',
            ],
        ];

        foreach ($this->providers as $section => $provider) {
            $data[$section] = $provider($userId);
        }

        return $data;
    }

    /**
     * Export as JSON string.
     */
    public function exportJson(int|string $userId): string
    {
        return json_encode($this->export($userId), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get the list of registered section names.
     *
     * @return list<string>
     */
    public function getSections(): array
    {
        return array_keys($this->providers);
    }
}
