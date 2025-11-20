# MonkeysLegion Core

[![PHP Version](https://img.shields.io/badge/php-%5E8.4-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

Core runtime package for the **MonkeysLegion PHP Framework**, providing essential components including routing, middleware, dependency injection integration, logging, and helper utilities.

## Overview

MonkeysLegion-Core is a foundational library that provides:

- **Route Loading**: Automatic controller discovery and route registration
- **CORS Middleware**: Advanced PSR-15 CORS handling with flexible origin matching
- **Smart Logging**: Environment-aware logging with PSR-3 compliance
- **Helper Functions**: Common utilities for path resolution and debugging
- **Service Provider Interface**: Standardized component registration

## Requirements

- PHP 8.4 or higher
- PSR-7 (HTTP Message)
- PSR-11 (Container)
- PSR-15 (HTTP Server Middleware)
- PSR-17 (HTTP Factories)

## Installation

```bash
composer require monkeyscloud/monkeyslegion-core
```

## Components

### 1. Route Loader

The [`RouteLoader`](src/Routing/RouteLoader.php) automatically scans your controller directory and registers routes defined via attributes.

#### Usage

```php
use MonkeysLegion\Core\Routing\RouteLoader;

$loader = new RouteLoader(
    router: $router,
    container: $container,
    controllerDir: base_path('app/Controller'),
    controllerNS: 'App\\Controller'
);

$loader->loadControllers();
```

**Features:**
- Recursive directory scanning
- Automatic class instantiation via DI container
- Skips abstract classes
- Integrates with `monkeyscloud/monkeyslegion-router`

---

### 2. CORS Middleware

The [`CorsMiddleware`](src/Middleware/CorsMiddleware.php) is a fully-featured PSR-15 middleware for handling Cross-Origin Resource Sharing.

#### Usage

```php
use MonkeysLegion\Core\Middleware\CorsMiddleware;

$cors = new CorsMiddleware(
    allowOrigin: ['https://example.com', '/^https:\/\/.*\.example\.com$/'],
    allowMethods: ['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS'],
    allowHeaders: ['Content-Type', 'Authorization', 'X-Requested-With'],
    exposeHeaders: ['X-Total-Count'],
    allowCredentials: true,
    maxAge: 86400,
    responseFactory: $responseFactory
);
```

**Features:**
- **Origin Matching**: Supports wildcards (`*`), exact strings, or PCRE patterns
- **Pre-flight Handling**: Automatic OPTIONS request handling
- **Credentials Support**: Configurable `Access-Control-Allow-Credentials`
- **Cache Safety**: Adds `Vary: Origin` header
- **Error Handling**: Catches exceptions and returns JSON error responses

---

### 3. Helper Functions

Defined in [`src/Support/helpers.php`](src/Support/helpers.php):

#### `base_path(string $path = ''): string`

Returns an absolute path relative to the project root.

```php
base_path();                    // → /full/path/to/project
base_path('var/migrations');    // → /full/path/to/project/var/migrations
base_path('config/app.php');    // → /full/path/to/project/config/app.php
```

**Configuration:**
- Define `ML_BASE_PATH` constant to set the project root
- Falls back to `dirname(__DIR__, 4)` for testing environments

#### `dd(mixed ...$args): void`

Dump variables and terminate script execution.

```php
dd($user, $order);  // Dumps both variables and exits
```

**Features:**
- CLI-aware output (plain text vs HTML)
- XSS-safe HTML output for web contexts
- Handles arrays, objects, scalars, and null values
- Exits with status code 1

---

### 4. Provider Interface

The [`ProviderInterface`](src/Provider/ProviderInterface.php) standardizes how components register themselves with the framework.

```php
interface ProviderInterface
{
    public static function register(string $root, ContainerBuilder $c): void;
    public static function setLogger(FrameworkLoggerInterface $logger): void;
}
```

Implement this interface in your service providers for consistent component registration.

---

## Architecture

### PSR Compliance

This package strictly adheres to PHP-FIG standards:

- **PSR-7**: HTTP Message Interface (used in [`CorsMiddleware`](src/Middleware/CorsMiddleware.php))
- **PSR-11**: Container Interface (used in [`RouteLoader`](src/Routing/RouteLoader.php))
- **PSR-15**: HTTP Server Request Handlers ([`CorsMiddleware`](src/Middleware/CorsMiddleware.php))
- **PSR-17**: HTTP Factories ([`CorsMiddleware`](src/Middleware/CorsMiddleware.php))

### Type Safety

- Strict type declarations (`declare(strict_types=1)`)
- Full PHP 8.4 type hints
- PHPStan level max compliance

---

## Development

### Static Analysis

```bash
vendor/bin/phpstan analyse
```

Configuration: [phpstan.neon](phpstan.neon)

### Code Quality Standards

- **Strict Types**: All files use `declare(strict_types=1)`
- **Final Classes**: Components use `final` to prevent inheritance where appropriate
- **Type Hints**: Full parameter and return type declarations
- **PHPDoc**: Comprehensive documentation blocks

---

## Integration Example

```php
use MonkeysLegion\Core\Routing\RouteLoader;
use MonkeysLegion\Core\Middleware\CorsMiddleware;
use MonkeysLegion\Core\Logger\MonkeyLogger;

// Set up logger
$logger = new MonkeyLogger($psrLogger, $_ENV['APP_ENV']);

// Configure CORS
$cors = new CorsMiddleware(
    allowOrigin: ['https://app.example.com'],
    allowMethods: ['GET', 'POST', 'PATCH', 'DELETE'],
    allowCredentials: true
);

// Load routes
$routeLoader = new RouteLoader(
    $router,
    $container,
    base_path('app/Controller'),
    'App\\Controller'
);
$routeLoader->loadControllers();

// Add middleware to pipeline
$middleware->add($cors);
```

---

## Dependencies

### Required

- `php`: ^8.4
- `psr/container`: ^2.0
- `psr/log`: ^3.0
- `psr/http-message`: ^2.0
- `psr/http-server-handler`: ^1.0
- `psr/http-server-middleware`: ^1.0
- `psr/http-factory`: ^1.1
- `monkeyscloud/monkeyslegion-http`: ^1.0
- `monkeyscloud/monkeyslegion-router`: ^1.0
- `monkeyscloud/monkeyslegion-di`: ^1.0

### Development

- `phpstan/phpstan`: ^2.1

---

## License

MIT License. See LICENSE file for details.

## Contributing

Contributions are welcome! Please ensure:

1. Code follows PSR-12 coding standards
2. All code passes PHPStan level max
3. New features include appropriate documentation
4. Type hints are comprehensive

## Support

For issues, questions, or contributions, please visit the project repository.