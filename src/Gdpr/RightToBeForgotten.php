<?php
declare(strict_types=1);

namespace MonkeysLegion\Core\Gdpr;



/**
 * MonKeysLegion Framework — Core Package
 *
 * Implements the GDPR right to be forgotten (Article 17).
 *
 * Two strategies:
 *  • anonymize — Replace PII fields with anonymized values (keeps row for referential integrity).
 *  • delete    — Remove the user and cascaded related data entirely.
 *
 * Register entity handlers via addHandler() to define what data to
 * anonymize or delete for each entity type.
 *
 * SECURITY: Always use dryRun() first to preview the changes.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class RightToBeForgotten
{
    /** @var array<string, array{anonymize: callable, delete: callable}> */
    private array $handlers = [];

    /**
     * Register a handler for an entity type.
     *
     * @param string $entityName Human-readable entity name for the report.
     * @param callable(int|string): array<string, mixed> $anonymize Returns anonymized field map.
     * @param callable(int|string): int $delete Deletes related data, returns affected row count.
     */
    public function addHandler(string $entityName, callable $anonymize, callable $delete): void
    {
        $this->handlers[$entityName] = ['anonymize' => $anonymize, 'delete' => $delete];
    }

    /**
     * Preview what would be affected without making changes.
     *
     * @return array{strategy: string, user_id: int|string, entities: array<string, array<string, mixed>>}
     */
    public function dryRun(int|string $userId, string $strategy = 'anonymize'): array
    {
        $report = [
            'strategy'  => $strategy,
            'user_id'   => $userId,
            'entities'  => [],
        ];

        foreach ($this->handlers as $entityName => $handler) {
            if ($strategy === 'anonymize') {
                $result = $handler['anonymize']($userId);
                $report['entities'][$entityName] = [
                    'action'    => 'anonymize',
                    'fields'    => $result,
                ];
            } else {
                $report['entities'][$entityName] = [
                    'action'    => 'delete',
                    'preview'   => true,
                ];
            }
        }

        return $report;
    }

    /**
     * Anonymize PII for a user (keeps records for referential integrity).
     *
     * @return array<string, array<string, mixed>> Anonymized fields per entity.
     */
    public function anonymize(int|string $userId): array
    {
        $results = [];

        foreach ($this->handlers as $entityName => $handler) {
            $results[$entityName] = $handler['anonymize']($userId);
        }

        return $results;
    }

    /**
     * Delete a user and all related data.
     *
     * @return array<string, int> Deleted row count per entity.
     */
    public function delete(int|string $userId): array
    {
        $results = [];

        foreach ($this->handlers as $entityName => $handler) {
            $results[$entityName] = $handler['delete']($userId);
        }

        return $results;
    }

    /**
     * Get registered entity handler names.
     *
     * @return list<string>
     */
    public function getEntities(): array
    {
        return array_keys($this->handlers);
    }
}
