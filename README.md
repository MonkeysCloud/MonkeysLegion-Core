# MonkeysLegion Core v2

High-performance framework kernel with typed config, service providers, pipeline, and PHP 8.4 primitives for the MonkeysLegion framework.

## Features

| Feature | Status |
|---|---|
| **PHP 8.4 Native** | Property hooks, backed enums, `readonly` classes, `new` in initializers |
| **3 Attributes** | `#[Provider]`, `#[BootAfter]`, `#[Config]` |
| **Application Kernel** | Provider registration, topological boot ordering, lifecycle hooks |
| **Generic Pipeline** | Immutable, zero-reflection, multi-pipe-type support |
| **Exception Handler** | Environment-aware: full debug local, sanitized in production |
| **Typed Config** | Dot-notation, O(1) cached lookups, type-safe getters |
| **Arr Utilities** | dot/undot, flatten, pluck, groupBy, sortBy, first/last |
| **Str Utilities** | camel/snake/kebab/studly, slug, UUID/ULID, mask, random (CSPRNG) |
| **Benchmark** | hrtime precision, memory measurement, comparison |
| **Once (Memoization)** | Rust-inspired call-once with keyed caching |
| **PSR-20 Clock** | Testable system clock |
| **20+ Helper Functions** | env, paths, retry, rescue, tap, value, blank/filled |

## Requirements

- **PHP 8.4** or higher
- `psr/container` ^2.0
- `psr/log` ^3.0
- `psr/clock` ^1.0

## Installation

```bash
composer require monkeyscloud/monkeyslegion-core:dev-2.0.0
```

## Architecture

```
src/
├── Attribute/          # #[Provider], #[BootAfter], #[Config]
├── Clock/              # PSR-20 SystemClock
├── Config/             # Typed ConfigRepository with dot-notation
├── Contract/           # Bootable, Deferrable, ExceptionRendererInterface
├── Environment/        # Backed enum + detector
├── Exception/          # Handler + HttpException with factories
├── Kernel/             # Application kernel with lifecycle hooks
├── Pipeline/           # Generic pipeline (Laravel parity)
├── Provider/           # ServiceProviderInterface + AbstractProvider
└── Support/            # Arr, Str, Benchmark, Once, helpers.php
```

## Quick Start

### Kernel Boot

```php
use MonkeysLegion\Core\Kernel\Kernel;
use MonkeysLegion\Core\Environment\Environment;

$kernel = new Kernel(
    container: $container,
    environment: Environment::Production,
);

$kernel->register(new DatabaseProvider());
$kernel->register(new AuthProvider());
$kernel->boot();

// ... handle request ...

$kernel->terminate();
```

### Service Providers

```php
use MonkeysLegion\Core\Attribute\Provider;
use MonkeysLegion\Core\Attribute\BootAfter;
use MonkeysLegion\Core\Contract\Bootable;
use MonkeysLegion\Core\Provider\AbstractProvider;

#[Provider(priority: 10)]
#[BootAfter(DatabaseProvider::class)]
final class AuthProvider extends AbstractProvider implements Bootable
{
    public function register(): void
    {
        // Register bindings
    }

    public function boot(): void
    {
        // Boot after DatabaseProvider
    }
}
```

### Pipeline

```php
use MonkeysLegion\Core\Pipeline\Pipeline;

$result = (new Pipeline())
    ->send($request)
    ->through([
        TrimStrings::class,
        ValidateInput::class,
        AuthenticateUser::class,
    ])
    ->then(fn($req) => $handler->handle($req));
```

### Config

```php
use MonkeysLegion\Core\Config\ConfigRepository;

$config = new ConfigRepository([
    'database' => [
        'host' => 'localhost',
        'port' => 5432,
    ],
]);

$host = $config->string('database.host');       // 'localhost'
$port = $config->int('database.port');           // 5432
$ssl  = $config->bool('database.ssl', false);    // false
```

### Exception Handling

```php
use MonkeysLegion\Core\Exception\Handler;
use MonkeysLegion\Core\Exception\HttpException;

// In production: generic messages, no stack traces
$handler = new Handler(Environment::Production, $logger);

try {
    throw HttpException::notFound('User not found');
} catch (\Throwable $e) {
    $handler->report($e);
    $response = $handler->render($e);
    // { error: true, status: 404, message: "User not found" }
}
```

### Utilities

```php
use MonkeysLegion\Core\Support\{Arr, Str, Benchmark, Once};

// Arrays
Arr::get($data, 'user.address.city', 'Unknown');
Arr::dot(['a' => ['b' => 1]]);  // ['a.b' => 1]

// Strings
Str::uuid();                    // 'f47ac10b-58cc-...'
Str::slug('Hello World!');      // 'hello-world'
Str::mask('secret123', '*', 3); // 'sec******'
Str::random(32);                // CSPRNG-backed

// Benchmark
$ms = Benchmark::measure(fn() => expensiveQuery(), iterations: 100);

// Memoization
$value = Once::callKeyed('config', fn() => loadConfig());
```

## Performance & Security

### Performance
- **ConfigRepository**: O(1) cached dot-notation lookups after first access
- **Pipeline**: Zero reflection, no container resolution overhead
- **Kernel**: Topological sort (Kahn's algorithm) runs once during boot
- **Benchmark**: hrtime() for nanosecond precision
- **Arr/Str**: Static methods with zero state, minimal allocations

### Security
- **Exception Handler**: Never exposes stack traces, file paths, or internal details in production
- **Str::random()**: CSPRNG-backed via random_int()
- **Str::uuid()/ulid()**: CSPRNG-backed via random_bytes()
- **env()**: Only reads from server environment, never from user input
- **HttpException**: Client-safe messages; internal details via $previous
- **Kernel::terminate()**: Catches all exceptions to prevent information leaks
- **Str::mask()**: Safely hides sensitive data (tokens, API keys)

## Testing

```bash
vendor/bin/phpunit --testdox
```

**114 tests, 238 assertions** — 100% passing.

## License

MIT License. See LICENSE file for details.