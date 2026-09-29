<?php
declare(strict_types=1);

namespace MonkeysLegion\Core\Tests\Unit\Opcache;

use MonkeysLegion\Core\Opcache\PreloadGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the PreloadGenerator.
 */
final class PreloadGeneratorTest extends TestCase
{
    private string $tempDir;
    private string $outputFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/ml-preload-test-' . uniqid();
        @mkdir($this->tempDir, 0755, true);

        // Create a fake vendor/composer/autoload_classmap.php
        $classmapDir = $this->tempDir . '/vendor/composer';
        @mkdir($classmapDir, 0755, true);

        $classmap = <<<PHP
<?php
return [
    'MonkeysLegion\\Core\\Support\\Once' => '{$this->tempDir}/vendor/monkeyslegion/core/src/Support/Once.php',
    'MonkeysLegion\\Http\\Message\\Response' => '{$this->tempDir}/vendor/monkeyslegion/http/src/Message/Response.php',
    'MonkeysLegion\\Router\\RouteCollection' => '{$this->tempDir}/vendor/monkeyslegion/router/src/RouteCollection.php',
    'App\\Controller\\HomeController' => '{$this->tempDir}/app/Controller/HomeController.php',
    'Some\\Other\\Library\\Class' => '{$this->tempDir}/vendor/some/other/src/Class.php',
];
PHP;

        file_put_contents($classmapDir . '/autoload_classmap.php', $classmap);

        // Create the framework class files (empty PHP files)
        $coreDir = $this->tempDir . '/vendor/monkeyslegion/core/src/Support';
        @mkdir($coreDir, 0755, true);
        file_put_contents($coreDir . '/Once.php', '<?php // stub');

        $httpDir = $this->tempDir . '/vendor/monkeyslegion/http/src/Message';
        @mkdir($httpDir, 0755, true);
        file_put_contents($httpDir . '/Response.php', '<?php // stub');

        $routerDir = $this->tempDir . '/vendor/monkeyslegion/router/src';
        @mkdir($routerDir, 0755, true);
        file_put_contents($routerDir . '/RouteCollection.php', '<?php // stub');

        $this->outputFile = $this->tempDir . '/preload.php';
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function generates_valid_php_file(): void
    {
        $generator = new PreloadGenerator($this->tempDir);
        $result = $generator->generate($this->outputFile);

        self::assertFileExists($this->outputFile);

        $content = file_get_contents($this->outputFile);
        self::assertStringStartsWith('<?php', $content);
        self::assertStringContainsString('declare(strict_types=1)', $content);
        self::assertStringContainsString('opcache_compile_file', $content);
    }

    #[Test]
    public function includes_only_framework_classes(): void
    {
        $generator = new PreloadGenerator($this->tempDir);
        $result = $generator->generate($this->outputFile);

        $content = file_get_contents($this->outputFile);

        // Should include MonKeysLegion classes
        self::assertStringContainsString('Once.php', $content);
        self::assertStringContainsString('Response.php', $content);
        self::assertStringContainsString('RouteCollection.php', $content);

        // Should NOT include non-framework classes
        self::assertStringNotContainsString('Some\Other', $content);
    }

    #[Test]
    public function handles_missing_classmap(): void
    {
        // Remove the classmap
        @unlink($this->tempDir . '/vendor/composer/autoload_classmap.php');

        $generator = new PreloadGenerator($this->tempDir);
        $result = $generator->generate($this->outputFile);

        self::assertFileExists($this->outputFile);
        self::assertSame(0, $result['written']); // No classes found
    }

    #[Test]
    public function wraps_calls_in_try_catch(): void
    {
        $generator = new PreloadGenerator($this->tempDir);
        $generator->generate($this->outputFile);

        $content = file_get_contents($this->outputFile);

        self::assertStringContainsString('try {', $content);
        self::assertStringContainsString('} catch (\Throwable', $content);
    }

    #[Test]
    public function includes_header_with_instructions(): void
    {
        $generator = new PreloadGenerator($this->tempDir);
        $generator->generate($this->outputFile);

        $content = file_get_contents($this->outputFile);

        self::assertStringContainsString('OPcache Preload Script', $content);
        self::assertStringContainsString('opcache.preload', $content);
        self::assertStringContainsString('opcache.preload_user', $content);
    }

    #[Test]
    public function returns_stats(): void
    {
        $generator = new PreloadGenerator($this->tempDir);
        $result = $generator->generate($this->outputFile);

        self::assertArrayHasKey('written', $result);
        self::assertArrayHasKey('file', $result);
        self::assertSame($this->outputFile, $result['file']);
        self::assertSame(3, $result['written']); // 3 framework classes
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;

        $items = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
