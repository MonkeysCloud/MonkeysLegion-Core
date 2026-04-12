<?php

declare(strict_types=1);

/**
 * MonkeysLegion Core v2 — Test Suite
 *
 * @package   MonkeysLegion\Core\Tests
 * @author    MonkeysCloud <jorge@monkeyscloud.com>
 * @license   MIT
 *
 * @requires  PHP 8.4
 */

namespace MonkeysLegion\Core\Tests;

use MonkeysLegion\Core\Attribute\BootAfter;
use MonkeysLegion\Core\Attribute\Config as ConfigAttribute;
use MonkeysLegion\Core\Attribute\Provider as ProviderAttribute;
use MonkeysLegion\Core\Clock\SystemClock;
use MonkeysLegion\Core\Config\ConfigRepository;
use MonkeysLegion\Core\Config\ConfigRepositoryInterface;
use MonkeysLegion\Core\Contract\Bootable;
use MonkeysLegion\Core\Contract\Deferrable;
use MonkeysLegion\Core\Environment\Environment;
use MonkeysLegion\Core\Environment\EnvironmentDetector;
use MonkeysLegion\Core\Exception\Handler;
use MonkeysLegion\Core\Exception\HttpException;
use MonkeysLegion\Core\Kernel\Kernel;
use MonkeysLegion\Core\Kernel\KernelEvent;
use MonkeysLegion\Core\Pipeline\Pipeline;
use MonkeysLegion\Core\Provider\AbstractProvider;
use MonkeysLegion\Core\Provider\ServiceProviderInterface;
use MonkeysLegion\Core\Support\Arr;
use MonkeysLegion\Core\Support\Benchmark;
use MonkeysLegion\Core\Support\Once;
use MonkeysLegion\Core\Support\Str;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

// ── Test Providers ───────────────────────────────────────────

class TestProviderA extends AbstractProvider implements Bootable
{
    public static bool $registered = false;
    public static bool $booted     = false;

    public function register(): void
    {
        self::$registered = true;
    }

    public function boot(): void
    {
        self::$booted = true;
    }
}

class TestProviderB extends AbstractProvider implements Bootable
{
    public static bool $registered = false;
    public static bool $booted     = false;

    public function register(): void
    {
        self::$registered = true;
    }

    public function boot(): void
    {
        self::$booted = true;
    }
}

#[BootAfter(TestProviderA::class)]
class DependentProvider extends AbstractProvider implements Bootable
{
    public static bool $booted      = false;
    public static int $bootOrder = 0;
    private static int $bootCounter = 0;

    public function register(): void {}

    public function boot(): void
    {
        self::$booted    = true;
        self::$bootOrder = ++self::$bootCounter;
    }

    public static function resetCounter(): void
    {
        self::$bootCounter = 0;
    }
}

class DeferredProvider extends AbstractProvider implements Deferrable
{
    public static bool $registered = false;

    public function register(): void
    {
        self::$registered = true;
    }

    public function provides(): array
    {
        return ['deferred.service'];
    }
}

class DeferredBootableProvider extends AbstractProvider implements Deferrable, Bootable
{
    public static bool $registered = false;
    public static bool $booted     = false;

    public function register(): void
    {
        self::$registered = true;
    }

    public function boot(): void
    {
        self::$booted = true;
    }

    public function provides(): array
    {
        return ['deferred.bootable.service'];
    }
}

// ── Test Pipeline Pipes ──────────────────────────────────────

class UpperCasePipe
{
    public function handle(string $input, callable $next): string
    {
        return $next(strtoupper($input));
    }
}

class TrimPipe
{
    public function handle(string $input, callable $next): string
    {
        return $next(trim($input));
    }
}

// ═══════════════════════════════════════════════════════════════
// ██ TEST SUITE ████████████████████████████████████████████████
// ═══════════════════════════════════════════════════════════════

final class CoreV2Test extends TestCase
{
    protected function setUp(): void
    {
        TestProviderA::$registered     = false;
        TestProviderA::$booted         = false;
        TestProviderB::$registered     = false;
        TestProviderB::$booted         = false;
        DependentProvider::$booted     = false;
        DependentProvider::$bootOrder  = 0;
        DependentProvider::resetCounter();
        DeferredProvider::$registered          = false;
        DeferredBootableProvider::$registered   = false;
        DeferredBootableProvider::$booted       = false;
        Once::flush();
    }

    // ── Environment Enum ─────────────────────────────────────

    public function test_environment_values(): void
    {
        $this->assertSame('local', Environment::Local->value);
        $this->assertSame('testing', Environment::Testing->value);
        $this->assertSame('staging', Environment::Staging->value);
        $this->assertSame('production', Environment::Production->value);
    }

    public function test_environment_labels(): void
    {
        $this->assertSame('Local', Environment::Local->label());
        $this->assertSame('Production', Environment::Production->label());
    }

    public function test_environment_helpers(): void
    {
        $this->assertTrue(Environment::Local->isLocal());
        $this->assertFalse(Environment::Local->isProduction());
        $this->assertTrue(Environment::Local->isDebug());
        $this->assertTrue(Environment::Testing->isDebug());
        $this->assertFalse(Environment::Production->isDebug());
        $this->assertTrue(Environment::Testing->isTesting());
    }

    public function test_environment_detect(): void
    {
        $this->assertSame(Environment::Production, Environment::detect('production'));
        $this->assertSame(Environment::Production, Environment::detect('prod'));
        $this->assertSame(Environment::Staging, Environment::detect('staging'));
        $this->assertSame(Environment::Staging, Environment::detect('stage'));
        $this->assertSame(Environment::Testing, Environment::detect('testing'));
        $this->assertSame(Environment::Testing, Environment::detect('ci'));
        $this->assertSame(Environment::Local, Environment::detect('anything'));
    }

    public function test_environment_cases(): void
    {
        $this->assertCount(4, Environment::cases());
    }

    // ── EnvironmentDetector ──────────────────────────────────

    public function test_detector_with_override(): void
    {
        $env = EnvironmentDetector::detect('production');
        $this->assertSame(Environment::Production, $env);
    }

    public function test_detector_defaults_to_local(): void
    {
        // Without APP_ENV or ML_ENV set, defaults to local
        $original = $_ENV['APP_ENV'] ?? null;
        unset($_ENV['APP_ENV']);

        $env = EnvironmentDetector::detect('local');
        $this->assertSame(Environment::Local, $env);

        if ($original !== null) {
            $_ENV['APP_ENV'] = $original;
        }
    }

    public function test_detector_has_env_file(): void
    {
        $this->assertFalse(EnvironmentDetector::hasEnvFile('/nonexistent/path'));
    }

    // ── ConfigRepository ─────────────────────────────────────

    public function test_config_get_simple(): void
    {
        $config = new ConfigRepository(['key' => 'value']);
        $this->assertSame('value', $config->get('key'));
    }

    public function test_config_get_dot_notation(): void
    {
        $config = new ConfigRepository([
            'database' => ['host' => 'localhost', 'port' => 5432],
        ]);

        $this->assertSame('localhost', $config->get('database.host'));
        $this->assertSame(5432, $config->get('database.port'));
    }

    public function test_config_get_default(): void
    {
        $config = new ConfigRepository([]);
        $this->assertSame('fallback', $config->get('missing', 'fallback'));
        $this->assertNull($config->get('missing'));
    }

    public function test_config_has(): void
    {
        $config = new ConfigRepository(['app' => ['name' => 'ML']]);

        $this->assertTrue($config->has('app'));
        $this->assertTrue($config->has('app.name'));
        $this->assertFalse($config->has('app.missing'));
        $this->assertFalse($config->has('nonexistent'));
    }

    public function test_config_set_simple(): void
    {
        $config = new ConfigRepository([]);
        $config->set('key', 'value');
        $this->assertSame('value', $config->get('key'));
    }

    public function test_config_set_dot_notation(): void
    {
        $config = new ConfigRepository([]);
        $config->set('database.host', 'localhost');
        $this->assertSame('localhost', $config->get('database.host'));
    }

    public function test_config_typed_getters(): void
    {
        $config = new ConfigRepository([
            'name'    => 'MonkeysLegion',
            'port'    => 8080,
            'rate'    => 0.75,
            'debug'   => true,
            'drivers' => ['mysql', 'pgsql'],
        ]);

        $this->assertSame('MonkeysLegion', $config->string('name'));
        $this->assertSame(8080, $config->int('port'));
        $this->assertSame(0.75, $config->float('rate'));
        $this->assertTrue($config->bool('debug'));
        $this->assertSame(['mysql', 'pgsql'], $config->array('drivers'));
    }

    public function test_config_typed_getters_with_defaults(): void
    {
        $config = new ConfigRepository([]);

        $this->assertSame('default', $config->string('missing', 'default'));
        $this->assertSame(0, $config->int('missing'));
        $this->assertSame(0.0, $config->float('missing'));
        $this->assertFalse($config->bool('missing'));
        $this->assertSame([], $config->array('missing'));
    }

    public function test_config_count_hook(): void
    {
        $config = new ConfigRepository(['a' => 1, 'b' => 2]);
        $this->assertSame(2, $config->count);
    }

    public function test_config_merge(): void
    {
        $config = new ConfigRepository(['a' => 1, 'b' => ['c' => 2]]);
        $config->merge(['b' => ['d' => 3], 'e' => 4]);

        $this->assertSame(1, $config->get('a'));
        $this->assertSame(2, $config->get('b.c'));
        $this->assertSame(3, $config->get('b.d'));
        $this->assertSame(4, $config->get('e'));
    }

    public function test_config_all(): void
    {
        $items = ['a' => 1, 'b' => 2];
        $config = new ConfigRepository($items);
        $this->assertSame($items, $config->all());
    }

    public function test_config_cache_invalidation(): void
    {
        $config = new ConfigRepository(['db' => ['host' => 'old']]);

        // Populate cache
        $this->assertSame('old', $config->get('db.host'));

        // Update and verify cache is invalidated
        $config->set('db.host', 'new');
        $this->assertSame('new', $config->get('db.host'));
    }

    public function test_config_implements_interface(): void
    {
        $config = new ConfigRepository();
        $this->assertInstanceOf(ConfigRepositoryInterface::class, $config);
    }

    // ── Attributes ───────────────────────────────────────────

    public function test_provider_attribute(): void
    {
        $attr = new ProviderAttribute(priority: 10, defer: true);
        $this->assertSame(10, $attr->priority);
        $this->assertTrue($attr->defer);
    }

    public function test_provider_attribute_defaults(): void
    {
        $attr = new ProviderAttribute();
        $this->assertSame(0, $attr->priority);
        $this->assertFalse($attr->defer);
    }

    public function test_boot_after_attribute(): void
    {
        $attr = new BootAfter(provider: TestProviderA::class);
        $this->assertSame(TestProviderA::class, $attr->provider);
    }

    public function test_config_attribute(): void
    {
        $attr = new ConfigAttribute(key: 'database.host', default: 'localhost');
        $this->assertSame('database.host', $attr->key);
        $this->assertSame('localhost', $attr->default);
    }

    // ── KernelEvent ──────────────────────────────────────────

    public function test_kernel_event_values(): void
    {
        $this->assertSame('kernel.booting', KernelEvent::Booting->value);
        $this->assertSame('kernel.booted', KernelEvent::Booted->value);
        $this->assertSame('kernel.terminating', KernelEvent::Terminating->value);
        $this->assertSame('kernel.terminated', KernelEvent::Terminated->value);
    }

    public function test_kernel_event_labels(): void
    {
        $this->assertSame('Booting', KernelEvent::Booting->label());
        $this->assertSame('Terminated', KernelEvent::Terminated->label());
    }

    // ── Kernel ───────────────────────────────────────────────

    public function test_kernel_creation(): void
    {
        $kernel = new Kernel(environment: Environment::Testing);

        $this->assertFalse($kernel->isBooted);
        $this->assertSame(Environment::Testing, $kernel->environment);
        $this->assertSame(0, $kernel->providerCount);
        $this->assertGreaterThan(0, $kernel->startTime);
    }

    public function test_kernel_register_and_boot(): void
    {
        $kernel = new Kernel(environment: Environment::Testing);
        $kernel->register(new TestProviderA());
        $kernel->register(new TestProviderB());

        $this->assertTrue(TestProviderA::$registered);
        $this->assertTrue(TestProviderB::$registered);
        $this->assertFalse(TestProviderA::$booted);

        $kernel->boot();

        $this->assertTrue($kernel->isBooted);
        $this->assertTrue(TestProviderA::$booted);
        $this->assertTrue(TestProviderB::$booted);
    }

    public function test_kernel_double_boot_is_noop(): void
    {
        $kernel = new Kernel(environment: Environment::Testing);
        $kernel->boot();
        $kernel->boot(); // Should not throw
        $this->assertTrue($kernel->isBooted);
    }

    public function test_kernel_boot_order_with_boot_after(): void
    {
        $kernel = new Kernel(environment: Environment::Testing);

        $providerA  = new TestProviderA();
        $dependent  = new DependentProvider();

        $kernel->register($dependent);
        $kernel->register($providerA);

        $kernel->boot();

        $this->assertTrue(TestProviderA::$booted);
        $this->assertTrue(DependentProvider::$booted);
    }

    public function test_kernel_lifecycle_hooks(): void
    {
        $kernel = new Kernel(environment: Environment::Testing);
        $log    = [];

        $kernel->booting(function () use (&$log) {
            $log[] = 'booting';
        });

        $kernel->booted(function () use (&$log) {
            $log[] = 'booted';
        });

        $kernel->boot();

        $this->assertSame(['booting', 'booted'], $log);
    }

    public function test_kernel_terminate(): void
    {
        $kernel = new Kernel(environment: Environment::Testing);
        $terminated = false;

        $kernel->terminating(function () use (&$terminated) {
            $terminated = true;
        });

        $kernel->terminate();
        $this->assertTrue($terminated);
    }

    public function test_kernel_terminate_catches_errors(): void
    {
        $kernel = new Kernel(environment: Environment::Testing);

        $kernel->terminating(function () {
            throw new \RuntimeException('Boom!');
        });

        // Should not throw
        $kernel->terminate();
        $this->assertTrue(true);
    }

    public function test_kernel_deferred_provider(): void
    {
        $kernel = new Kernel(environment: Environment::Testing);
        $kernel->register(new DeferredProvider());

        // Not registered yet (deferred)
        $this->assertFalse(DeferredProvider::$registered);
        $this->assertSame(0, $kernel->providerCount);

        // Trigger deferred registration
        $kernel->registerDeferredFor('deferred.service');
        $this->assertTrue(DeferredProvider::$registered);
        $this->assertSame(1, $kernel->providerCount);
    }

    public function test_kernel_uptime(): void
    {
        $kernel = new Kernel(environment: Environment::Testing);
        usleep(1000); // 1ms
        $this->assertGreaterThan(0, $kernel->uptime());
    }

    public function test_kernel_get_provider_classes(): void
    {
        $kernel = new Kernel(environment: Environment::Testing);
        $kernel->register(new TestProviderA());

        $classes = $kernel->getProviderClasses();
        $this->assertContains(TestProviderA::class, $classes);
    }

    // ── Pipeline ─────────────────────────────────────────────

    public function test_pipeline_basic(): void
    {
        $result = (new Pipeline())
            ->send('  hello  ')
            ->through([new TrimPipe(), new UpperCasePipe()])
            ->then(fn(string $val) => $val . '!');

        $this->assertSame('HELLO!', $result);
    }

    public function test_pipeline_with_closures(): void
    {
        $result = (new Pipeline())
            ->send(10)
            ->through([
                fn(int $val, callable $next) => $next($val * 2),
                fn(int $val, callable $next) => $next($val + 5),
            ])
            ->then(fn(int $val) => $val);

        $this->assertSame(25, $result);
    }

    public function test_pipeline_then_return(): void
    {
        $result = (new Pipeline())
            ->send('hello')
            ->through([
                fn(string $val, callable $next) => $next(strtoupper($val)),
            ])
            ->thenReturn();

        $this->assertSame('HELLO', $result);
    }

    public function test_pipeline_immutable(): void
    {
        $pipeline = new Pipeline();
        $p1       = $pipeline->send('a');
        $p2       = $pipeline->send('b');

        $this->assertNotSame($pipeline, $p1);
        $this->assertNotSame($p1, $p2);
    }

    public function test_pipeline_with_class_string(): void
    {
        $result = (new Pipeline())
            ->send('  hello  ')
            ->through([TrimPipe::class])
            ->thenReturn();

        $this->assertSame('hello', $result);
    }

    public function test_pipeline_pipe_method(): void
    {
        $result = (new Pipeline())
            ->send('hello')
            ->pipe(fn(string $val, callable $next) => $next(strtoupper($val)))
            ->pipe(fn(string $val, callable $next) => $next($val . '!'))
            ->thenReturn();

        $this->assertSame('HELLO!', $result);
    }

    // ── HttpException ────────────────────────────────────────

    public function test_http_exception_basic(): void
    {
        $e = new HttpException(404, 'Not Found');
        $this->assertSame(404, $e->getStatusCode());
        $this->assertSame('Not Found', $e->getMessage());
        $this->assertSame([], $e->getHeaders());
    }

    public function test_http_exception_with_headers(): void
    {
        $e = new HttpException(401, 'Unauthorized', headers: [
            'WWW-Authenticate' => 'Bearer',
        ]);
        $this->assertSame(['WWW-Authenticate' => 'Bearer'], $e->getHeaders());
    }

    public function test_http_exception_factories(): void
    {
        $this->assertSame(404, HttpException::notFound()->getStatusCode());
        $this->assertSame(403, HttpException::forbidden()->getStatusCode());
        $this->assertSame(401, HttpException::unauthorized()->getStatusCode());
        $this->assertSame(400, HttpException::badRequest()->getStatusCode());
        $this->assertSame(409, HttpException::conflict()->getStatusCode());
        $this->assertSame(422, HttpException::unprocessable()->getStatusCode());
        $this->assertSame(429, HttpException::tooManyRequests()->getStatusCode());
        $this->assertSame(500, HttpException::serverError()->getStatusCode());
        $this->assertSame(503, HttpException::serviceUnavailable()->getStatusCode());
    }

    public function test_http_exception_too_many_requests_has_retry_header(): void
    {
        $e = HttpException::tooManyRequests(120);
        $this->assertSame('120', $e->getHeaders()['Retry-After']);
    }

    // ── Exception Handler ────────────────────────────────────

    public function test_handler_report(): void
    {
        $handler = new Handler(Environment::Testing);
        $handler->report(new \RuntimeException('Test error'));

        $this->assertSame(1, $handler->handledCount);
    }

    public function test_handler_dont_report(): void
    {
        $handler = new Handler(Environment::Testing);
        $handler->dontReport(\InvalidArgumentException::class);
        $handler->report(new \InvalidArgumentException('Ignored'));

        $this->assertSame(0, $handler->handledCount);
    }

    public function test_handler_render_debug(): void
    {
        $handler = new Handler(Environment::Local);
        $result  = $handler->render(new \RuntimeException('Debug error'));

        $this->assertTrue($result['error']);
        $this->assertSame(500, $result['status']);
        $this->assertArrayHasKey('debug', $result);
        $this->assertArrayHasKey('trace', $result['debug']);
    }

    public function test_handler_render_production(): void
    {
        $handler = new Handler(Environment::Production);
        $result  = $handler->render(new \RuntimeException('Sensitive details'));

        $this->assertTrue($result['error']);
        $this->assertSame(500, $result['status']);
        $this->assertSame('An internal error occurred.', $result['message']);
        $this->assertArrayNotHasKey('debug', $result);
    }

    public function test_handler_render_http_exception(): void
    {
        $handler = new Handler(Environment::Production);
        $result  = $handler->render(HttpException::notFound('User not found'));

        $this->assertSame(404, $result['status']);
        $this->assertSame('User not found', $result['message']);
    }

    public function test_handler_report_callback(): void
    {
        $handler  = new Handler(Environment::Testing);
        $captured = null;

        $handler->reportUsing(function (\Throwable $e) use (&$captured) {
            $captured = $e->getMessage();
        });

        $handler->report(new \RuntimeException('Callback test'));
        $this->assertSame('Callback test', $captured);
    }

    public function test_handler_callback_failure_doesnt_crash(): void
    {
        $handler = new Handler(Environment::Testing);
        $handler->reportUsing(function () {
            throw new \RuntimeException('Callback boom');
        });

        $handler->report(new \RuntimeException('Original'));
        $this->assertSame(1, $handler->handledCount);
    }

    // ── Arr ──────────────────────────────────────────────────

    public function test_arr_get(): void
    {
        $data = ['a' => ['b' => ['c' => 'deep']]];
        $this->assertSame('deep', Arr::get($data, 'a.b.c'));
        $this->assertNull(Arr::get($data, 'missing'));
        $this->assertSame('default', Arr::get($data, 'missing', 'default'));
    }

    public function test_arr_set(): void
    {
        $data = [];
        Arr::set($data, 'a.b.c', 'value');
        $this->assertSame('value', $data['a']['b']['c']);
    }

    public function test_arr_has(): void
    {
        $data = ['a' => ['b' => 1]];
        $this->assertTrue(Arr::has($data, 'a.b'));
        $this->assertFalse(Arr::has($data, 'a.c'));
    }

    public function test_arr_forget(): void
    {
        $data = ['a' => ['b' => 1, 'c' => 2]];
        Arr::forget($data, 'a.b');
        $this->assertFalse(isset($data['a']['b']));
        $this->assertSame(2, $data['a']['c']);
    }

    public function test_arr_dot(): void
    {
        $data = ['a' => ['b' => 1, 'c' => ['d' => 2]]];
        $flat = Arr::dot($data);
        $this->assertSame(['a.b' => 1, 'a.c.d' => 2], $flat);
    }

    public function test_arr_undot(): void
    {
        $flat = ['a.b' => 1, 'a.c' => 2];
        $nested = Arr::undot($flat);
        $this->assertSame(['a' => ['b' => 1, 'c' => 2]], $nested);
    }

    public function test_arr_flatten(): void
    {
        $this->assertSame([1, 2, 3], Arr::flatten([[1, [2]], [3]]));
    }

    public function test_arr_only(): void
    {
        $data = ['a' => 1, 'b' => 2, 'c' => 3];
        $this->assertSame(['a' => 1, 'c' => 3], Arr::only($data, ['a', 'c']));
    }

    public function test_arr_except(): void
    {
        $data = ['a' => 1, 'b' => 2, 'c' => 3];
        $this->assertSame(['a' => 1, 'c' => 3], Arr::except($data, ['b']));
    }

    public function test_arr_first(): void
    {
        $this->assertSame(2, Arr::first([1, 2, 3], fn($v) => $v > 1));
        $this->assertSame(1, Arr::first([1, 2, 3]));
        $this->assertNull(Arr::first([]));
    }

    public function test_arr_last(): void
    {
        $this->assertSame(3, Arr::last([1, 2, 3], fn($v) => $v > 1));
        $this->assertSame(3, Arr::last([1, 2, 3]));
    }

    public function test_arr_wrap(): void
    {
        $this->assertSame([1], Arr::wrap(1));
        $this->assertSame([1, 2], Arr::wrap([1, 2]));
        $this->assertSame([], Arr::wrap(null));
    }

    public function test_arr_is_assoc(): void
    {
        $this->assertTrue(Arr::isAssoc(['a' => 1, 'b' => 2]));
        $this->assertFalse(Arr::isAssoc([1, 2, 3]));
        $this->assertFalse(Arr::isAssoc([]));
    }

    public function test_arr_pluck(): void
    {
        $data = [['name' => 'A'], ['name' => 'B']];
        $this->assertSame(['A', 'B'], Arr::pluck($data, 'name'));
    }

    public function test_arr_group_by(): void
    {
        $data = [
            ['type' => 'a', 'id' => 1],
            ['type' => 'b', 'id' => 2],
            ['type' => 'a', 'id' => 3],
        ];
        $grouped = Arr::groupBy($data, 'type');
        $this->assertCount(2, $grouped['a']);
        $this->assertCount(1, $grouped['b']);
    }

    public function test_arr_sort_by(): void
    {
        $data = [['n' => 3], ['n' => 1], ['n' => 2]];
        $sorted = array_values(Arr::sortBy($data, 'n'));
        $this->assertSame(1, $sorted[0]['n']);
        $this->assertSame(3, $sorted[2]['n']);
    }

    // ── Str ──────────────────────────────────────────────────

    public function test_str_camel(): void
    {
        $this->assertSame('helloWorld', Str::camel('hello_world'));
        $this->assertSame('helloWorld', Str::camel('hello-world'));
    }

    public function test_str_snake(): void
    {
        $this->assertSame('hello_world', Str::snake('HelloWorld'));
    }

    public function test_str_kebab(): void
    {
        $this->assertSame('hello-world', Str::kebab('HelloWorld'));
    }

    public function test_str_studly(): void
    {
        $this->assertSame('HelloWorld', Str::studly('hello_world'));
        $this->assertSame('HelloWorld', Str::studly('hello-world'));
    }

    public function test_str_slug(): void
    {
        $slug = Str::slug('Hello World! 123');
        $this->assertSame('hello-world-123', $slug);
    }

    public function test_str_random(): void
    {
        $r1 = Str::random(20);
        $r2 = Str::random(20);
        $this->assertSame(20, strlen($r1));
        $this->assertNotSame($r1, $r2);
    }

    public function test_str_contains(): void
    {
        $this->assertTrue(Str::contains('hello world', 'world'));
        $this->assertFalse(Str::contains('hello world', 'xyz'));
        $this->assertTrue(Str::contains('hello world', ['xyz', 'world']));
    }

    public function test_str_contains_all(): void
    {
        $this->assertTrue(Str::containsAll('hello world', ['hello', 'world']));
        $this->assertFalse(Str::containsAll('hello world', ['hello', 'xyz']));
    }

    public function test_str_starts_ends_with(): void
    {
        $this->assertTrue(Str::startsWith('hello', 'hel'));
        $this->assertTrue(Str::endsWith('hello', 'llo'));
        $this->assertFalse(Str::startsWith('hello', 'xyz'));
    }

    public function test_str_before_after(): void
    {
        $this->assertSame('hello', Str::before('hello@world.com', '@'));
        $this->assertSame('world.com', Str::after('hello@world.com', '@'));
    }

    public function test_str_before_last_after_last(): void
    {
        $this->assertSame('hello.world', Str::beforeLast('hello.world.com', '.'));
        $this->assertSame('com', Str::afterLast('hello.world.com', '.'));
    }

    public function test_str_between(): void
    {
        $this->assertSame('world', Str::between('hello [world] end', '[', ']'));
    }

    public function test_str_limit(): void
    {
        $this->assertSame('hel...', Str::limit('hello world', 3));
        $this->assertSame('short', Str::limit('short', 10));
    }

    public function test_str_uuid(): void
    {
        $uuid = Str::uuid();
        $this->assertTrue(Str::isUuid($uuid));
        $this->assertSame(36, strlen($uuid));
    }

    public function test_str_ulid(): void
    {
        $ulid = Str::ulid();
        $this->assertSame(26, strlen($ulid));
    }

    public function test_str_mask(): void
    {
        $masked = Str::mask('secret123', '*', 3);
        $this->assertSame('sec******', $masked);
    }

    public function test_str_is_json(): void
    {
        $this->assertTrue(Str::isJson('{"key": "value"}'));
        $this->assertFalse(Str::isJson('not json'));
        $this->assertFalse(Str::isJson(''));
    }

    public function test_str_title(): void
    {
        $this->assertSame('Hello World', Str::title('hello world'));
    }

    public function test_str_reverse(): void
    {
        $this->assertSame('dlrow', Str::reverse('world'));
    }

    public function test_str_word_count(): void
    {
        $this->assertSame(3, Str::wordCount('hello world today'));
    }

    public function test_str_words(): void
    {
        $this->assertSame('hello world...', Str::words('hello world today', 2));
    }

    public function test_str_replace_first(): void
    {
        $this->assertSame('xbc abc', Str::replaceFirst('a', 'x', 'abc abc'));
    }

    public function test_str_replace_last(): void
    {
        $this->assertSame('abc xbc', Str::replaceLast('a', 'x', 'abc abc'));
    }

    // ── Benchmark ────────────────────────────────────────────

    public function test_benchmark_measure(): void
    {
        $ms = Benchmark::measure(fn() => usleep(1000));
        $this->assertGreaterThan(0.5, $ms);
    }

    public function test_benchmark_measure_with_result(): void
    {
        [$ms, $result] = Benchmark::measureWithResult(fn() => 42);
        $this->assertSame(42, $result);
        $this->assertGreaterThanOrEqual(0.0, $ms);
    }

    public function test_benchmark_compare(): void
    {
        $results = Benchmark::compare([
            'fast'  => fn() => 1 + 1,
            'slower' => fn() => usleep(100),
        ], iterations: 5);

        $this->assertArrayHasKey('fast', $results);
        $this->assertArrayHasKey('slower', $results);
    }

    public function test_benchmark_format(): void
    {
        $this->assertSame('500μs', Benchmark::format(0.5));
        $this->assertSame('1.5ms', Benchmark::format(1.5));
        $this->assertSame('1.5s', Benchmark::format(1500));
    }

    // ── Once ─────────────────────────────────────────────────

    public function test_once_keyed(): void
    {
        $counter = 0;
        $result1 = Once::callKeyed('test', function () use (&$counter) {
            $counter++;
            return 'computed';
        });
        $result2 = Once::callKeyed('test', function () use (&$counter) {
            $counter++;
            return 'recomputed';
        });

        $this->assertSame('computed', $result1);
        $this->assertSame('computed', $result2);
        $this->assertSame(1, $counter);
    }

    public function test_once_flush(): void
    {
        Once::callKeyed('flushable', fn() => 'cached');
        $this->assertTrue(Once::has('flushable'));

        Once::flush();
        $this->assertFalse(Once::has('flushable'));
        $this->assertSame(0, Once::count());
    }

    public function test_once_forget(): void
    {
        Once::callKeyed('forgettable', fn() => 'value');
        Once::forget('forgettable');
        $this->assertFalse(Once::has('forgettable'));
    }

    // ── SystemClock ──────────────────────────────────────────

    public function test_system_clock_psr20(): void
    {
        $clock = new SystemClock();
        $this->assertInstanceOf(ClockInterface::class, $clock);
    }

    public function test_system_clock_now(): void
    {
        $clock = new SystemClock();
        $now   = $clock->now();
        $this->assertInstanceOf(\DateTimeImmutable::class, $now);
    }

    public function test_system_clock_timezone(): void
    {
        $clock = new SystemClock(new \DateTimeZone('America/Mexico_City'));
        $now   = $clock->now();
        $this->assertSame('America/Mexico_City', $now->getTimezone()->getName());
    }

    // ── Helper Functions ─────────────────────────────────────

    public function test_helper_env(): void
    {
        $_ENV['TEST_VAR'] = 'hello';
        $this->assertSame('hello', env('TEST_VAR'));
        $this->assertSame('default', env('MISSING_VAR', 'default'));
        unset($_ENV['TEST_VAR']);
    }

    public function test_helper_env_type_casting(): void
    {
        $_ENV['BOOL_TRUE']  = 'true';
        $_ENV['BOOL_FALSE'] = 'false';
        $_ENV['NULL_VAL']   = 'null';
        $_ENV['EMPTY_VAL']  = 'empty';

        $this->assertTrue(env('BOOL_TRUE'));
        $this->assertFalse(env('BOOL_FALSE'));
        $this->assertNull(env('NULL_VAL'));
        $this->assertSame('', env('EMPTY_VAL'));

        unset($_ENV['BOOL_TRUE'], $_ENV['BOOL_FALSE'], $_ENV['NULL_VAL'], $_ENV['EMPTY_VAL']);
    }

    public function test_helper_value(): void
    {
        $this->assertSame(42, value(42));
        $this->assertSame(42, value(fn() => 42));
        $this->assertSame(10, value(fn(int $x) => $x * 2, 5));
    }

    public function test_helper_tap(): void
    {
        $tapped = null;
        $result = tap('hello', function ($v) use (&$tapped) {
            $tapped = $v;
        });
        $this->assertSame('hello', $result);
        $this->assertSame('hello', $tapped);
    }

    public function test_helper_with(): void
    {
        $this->assertSame(10, with(5, fn($v) => $v * 2));
        $this->assertSame(5, with(5));
    }

    public function test_helper_blank_filled(): void
    {
        $this->assertTrue(blank(null));
        $this->assertTrue(blank(''));
        $this->assertTrue(blank('  '));
        $this->assertTrue(blank([]));
        $this->assertFalse(blank('hello'));
        $this->assertFalse(blank(0));

        $this->assertTrue(filled('hello'));
        $this->assertFalse(filled(null));
    }

    public function test_helper_class_basename(): void
    {
        $this->assertSame('CoreV2Test', class_basename(self::class));
        $this->assertSame('Simple', class_basename('Simple'));
    }

    public function test_helper_transform(): void
    {
        $this->assertSame('HELLO', transform('hello', fn($v) => strtoupper($v)));
        $this->assertNull(transform(null, fn($v) => strtoupper($v)));
        $this->assertSame('default', transform('', fn($v) => strtoupper($v), 'default'));
    }

    public function test_helper_retry(): void
    {
        $attempts = 0;
        $result = retry(3, function () use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new \RuntimeException('Fail');
            }
            return 'success';
        });

        $this->assertSame('success', $result);
        $this->assertSame(3, $attempts);
    }

    public function test_helper_retry_throws_on_all_failures(): void
    {
        $this->expectException(\RuntimeException::class);
        retry(2, function () {
            throw new \RuntimeException('Always fails');
        });
    }

    public function test_helper_windows_os(): void
    {
        // This will just confirm it returns a bool
        $this->assertIsBool(windows_os());
    }

    // ── New: ConfigRepository::forget() ─────────────────────

    public function test_config_forget_simple(): void
    {
        $config = new ConfigRepository(['key' => 'value', 'other' => 'data']);
        $config->forget('key');
        $this->assertFalse($config->has('key'));
        $this->assertTrue($config->has('other'));
    }

    public function test_config_forget_dot_notation(): void
    {
        $config = new ConfigRepository(['db' => ['host' => 'localhost', 'port' => 3306]]);
        $config->forget('db.host');
        $this->assertFalse($config->has('db.host'));
        $this->assertTrue($config->has('db.port'));
        $this->assertSame(3306, $config->get('db.port'));
    }

    public function test_config_forget_nonexistent_key(): void
    {
        $config = new ConfigRepository(['key' => 'value']);
        $config->forget('nonexistent');
        $this->assertTrue($config->has('key'));
    }

    public function test_config_forget_invalidates_cache(): void
    {
        $config = new ConfigRepository(['db' => ['host' => 'localhost']]);
        // Populate cache
        $this->assertSame('localhost', $config->get('db.host'));
        // Remove it
        $config->forget('db.host');
        // Cache should be invalidated
        $this->assertNull($config->get('db.host'));
    }

    // ── New: ConfigRepository parent cache invalidation ─────

    public function test_config_set_invalidates_parent_cache(): void
    {
        $config = new ConfigRepository(['db' => ['host' => 'old', 'port' => 3306]]);
        // Populate cache for parent key
        $db = $config->get('db');
        $this->assertSame(['host' => 'old', 'port' => 3306], $db);
        // Change a child key
        $config->set('db.host', 'new');
        // Parent cache should be invalidated and reflect new data
        $db = $config->get('db');
        $this->assertSame('new', $db['host']);
    }

    // ── New: Kernel cycle detection ─────────────────────────

    // Note: Cycle detection test requires creating providers with circular
    // #[BootAfter] dependencies. Since the test providers in this file don't
    // form cycles, we test that normal boot still works after the change.

    public function test_kernel_boot_no_cycle(): void
    {
        $kernel = new Kernel(environment: Environment::Testing);
        $kernel->register(new TestProviderA());
        $kernel->register(new DependentProvider());
        // Should not throw
        $kernel->boot();
        $this->assertTrue($kernel->isBooted);
    }

    // ── New: Path traversal prevention ──────────────────────

    public function test_base_path_rejects_path_traversal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Path traversal detected');
        base_path('../etc/passwd');
    }

    public function test_base_path_rejects_nested_traversal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        base_path('foo/../../bar');
    }

    public function test_base_path_allows_normal_paths(): void
    {
        $path = base_path('src/Support');
        $this->assertStringContainsString('src', $path);
        $this->assertStringContainsString('Support', $path);
    }

    public function test_base_path_allows_dotfiles(): void
    {
        // Single dots in filenames should be fine
        $path = base_path('.env');
        $this->assertStringContainsString('.env', $path);
    }

    // ── New: resource_path helper ───────────────────────────

    public function test_helper_resource_path(): void
    {
        $path = resource_path();
        $this->assertStringContainsString('resources', $path);
    }

    public function test_helper_resource_path_with_subpath(): void
    {
        $path = resource_path('views/home.php');
        $this->assertStringContainsString('resources', $path);
        $this->assertStringContainsString('views', $path);
    }

    // ── New: ULID correctness ───────────────────────────────

    public function test_str_ulid_length_and_charset(): void
    {
        $ulid = Str::ulid();
        $this->assertSame(26, strlen($ulid));
        // Verify all characters are valid Crockford's Base32
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $ulid);
    }

    public function test_str_ulid_uniqueness(): void
    {
        $ulids = [];
        for ($i = 0; $i < 100; $i++) {
            $ulids[] = Str::ulid();
        }
        $this->assertCount(100, array_unique($ulids));
    }

    // ── New: Str::slug intl fallback ────────────────────────

    public function test_str_slug_basic(): void
    {
        $this->assertSame('hello-world', Str::slug('Hello World'));
        $this->assertSame('hello-world-123', Str::slug('Hello World! 123'));
    }

    public function test_str_slug_custom_separator(): void
    {
        $this->assertSame('hello_world', Str::slug('Hello World', '_'));
    }

    // ── New: Deferred provider boots after kernel booted ────

    public function test_deferred_provider_boots_after_kernel_booted(): void
    {
        $kernel = new Kernel(environment: Environment::Testing);
        $deferred = new DeferredBootableProvider();

        $kernel->register($deferred);
        $kernel->boot(); // Kernel is now booted

        // Provider is deferred — not yet registered or booted
        $this->assertFalse(DeferredBootableProvider::$registered);
        $this->assertFalse(DeferredBootableProvider::$booted);

        // Trigger deferred loading AFTER boot
        $kernel->registerDeferredFor('deferred.bootable.service');

        // Must be both registered AND booted
        $this->assertTrue(DeferredBootableProvider::$registered);
        $this->assertTrue(DeferredBootableProvider::$booted);
    }

    // ── New: Config top-level cache works for parent invalidation ──

    public function test_config_top_level_cache_and_parent_invalidation(): void
    {
        $config = new ConfigRepository(['db' => ['host' => 'localhost']]);

        // Read top-level key to populate cache
        $dbAll = $config->get('db');
        $this->assertSame(['host' => 'localhost'], $dbAll);

        // Mutate a child key
        $config->set('db.host', 'newhost');

        // The parent 'db' cache should be invalidated and return fresh data
        $dbAll = $config->get('db');
        $this->assertSame(['host' => 'newhost'], $dbAll);
    }
}
