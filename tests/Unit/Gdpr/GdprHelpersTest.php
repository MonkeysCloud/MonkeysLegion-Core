<?php
declare(strict_types=1);

namespace Tests\Unit\Gdpr;

use MonkeysLegion\Core\Gdpr\ConsentManager;
use MonkeysLegion\Core\Gdpr\DataExporter;
use MonkeysLegion\Core\Gdpr\RightToBeForgotten;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GdprHelpersTest extends TestCase
{
    // ── ConsentManager ──────────────────────────────────────────

    #[Test]
    public function consent_manager_grant_and_check(): void
    {
        $manager = new ConsentManager();
        $manager->grant(1, 'marketing_emails');

        self::assertTrue($manager->hasConsent(1, 'marketing_emails'));
    }

    #[Test]
    public function consent_manager_revoke(): void
    {
        $manager = new ConsentManager();
        $manager->grant(1, 'analytics');
        $manager->revoke(1, 'analytics');

        self::assertFalse($manager->hasConsent(1, 'analytics'));
    }

    #[Test]
    public function consent_manager_revoke_all(): void
    {
        $manager = new ConsentManager();
        $manager->grant(1, 'analytics');
        $manager->grant(1, 'marketing');
        $manager->revokeAll(1);

        self::assertFalse($manager->hasConsent(1, 'analytics'));
        self::assertFalse($manager->hasConsent(1, 'marketing'));
    }

    #[Test]
    public function consent_manager_unknown_purpose_returns_false(): void
    {
        $manager = new ConsentManager();

        self::assertFalse($manager->hasConsent(1, 'unknown'));
    }

    // ── DataExporter ────────────────────────────────────────────

    #[Test]
    public function data_exporter_collects_from_providers(): void
    {
        $exporter = new DataExporter();
        $exporter->addProvider('profile', fn($userId) => [
            'name'  => 'John Doe',
            'email' => 'john@example.com',
            'id'    => $userId,
        ]);
        $exporter->addProvider('orders', fn($userId) => [
            ['order_id' => 1, 'total' => 99.99],
        ]);

        $data = $exporter->export(1);

        self::assertSame(1, $data['_meta']['user_id']);
        self::assertSame('John Doe', $data['profile']['name']);
        self::assertCount(1, $data['orders']);
    }

    #[Test]
    public function data_exporter_exports_json(): void
    {
        $exporter = new DataExporter();
        $exporter->addProvider('profile', fn($userId) => ['name' => 'Test']);

        $json = $exporter->exportJson(1);

        $data = json_decode($json, true);
        self::assertIsArray($data);
        self::assertSame('Test', $data['profile']['name']);
    }

    #[Test]
    public function data_exporter_lists_sections(): void
    {
        $exporter = new DataExporter();
        $exporter->addProvider('a', fn() => []);
        $exporter->addProvider('b', fn() => []);

        self::assertSame(['a', 'b'], $exporter->getSections());
    }

    // ── RightToBeForgotten ──────────────────────────────────────

    #[Test]
    public function right_to_be_forgotten_dry_run_anonymize(): void
    {
        $rtbf = new RightToBeForgotten();
        $rtbf->addHandler(
            'users',
            fn($userId) => ['email' => 'anonymized@example.com', 'name' => 'Anonymous'],
            fn($userId) => 1,
        );

        $report = $rtbf->dryRun(42, 'anonymize');

        self::assertSame('anonymize', $report['strategy']);
        self::assertSame(42, $report['user_id']);
        self::assertSame('anonymize', $report['entities']['users']['action']);
        self::assertSame('anonymized@example.com', $report['entities']['users']['fields']['email']);
    }

    #[Test]
    public function right_to_be_forgotten_anonymize_calls_handlers(): void
    {
        $called = false;
        $rtbf = new RightToBeForgotten();
        $rtbf->addHandler(
            'users',
            function ($userId) use (&$called) {
                $called = true;
                return ['email' => 'anon@anon.com'];
            },
            fn($userId) => 1,
        );

        $result = $rtbf->anonymize(1);

        self::assertTrue($called);
        self::assertSame(['email' => 'anon@anon.com'], $result['users']);
    }

    #[Test]
    public function right_to_be_forgotten_delete_calls_handlers(): void
    {
        $deletedCount = 0;
        $rtbf = new RightToBeForgotten();
        $rtbf->addHandler(
            'users',
            fn($userId) => ['email' => 'anon'],
            function ($userId) use (&$deletedCount) {
                $deletedCount = 5;
                return $deletedCount;
            },
        );

        $result = $rtbf->delete(1);

        self::assertSame(5, $result['users']);
    }

    #[Test]
    public function right_to_be_forgotten_lists_entities(): void
    {
        $rtbf = new RightToBeForgotten();
        $rtbf->addHandler('a', fn() => [], fn() => 0);
        $rtbf->addHandler('b', fn() => [], fn() => 0);

        self::assertSame(['a', 'b'], $rtbf->getEntities());
    }
}
