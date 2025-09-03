<?php

declare(strict_types=1);

namespace MonkeysLegion\Core\Routing;

use MonkeysLegion\Router\Router;
use Psr\Container\ContainerInterface;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use ReflectionClass;

final class RouteLoader
{
    public function __construct(
        private Router             $router,
        private ContainerInterface $container,
        private string             $controllerDir = '',       // e.g. base_path('app/Controller')
        private string             $controllerNS  = ''        // e.g. 'App\\Controller'
    ) {}

    /**
     * Scan the controllers directory for PHP classes, instantiate them
     * via the container (or `new`), and register any #[Route] methods.
     */
    public function loadControllers(): void
    {
        $dir = $this->controllerDir
            ?: \base_path('app/Controller');
        $ns  = $this->controllerNS
            ?: 'App\\Controller';

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir)
        );

        /** @var \SplFileInfo $file */
        foreach ($it as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            // Build the fully-qualified class name
            $relative = substr($file->getRealPath(), strlen($dir) + 1);
            $class    = $ns . '\\' . strtr($relative, ['/' => '\\', '.php' => '']);

            if (! class_exists($class)) {
                continue;
            }
            $ref = new ReflectionClass($class);
            if ($ref->isAbstract()) {
                continue;
            }
            // Resolve via container if possible, else new
            $instance = $this->container->has($class)
                ? $this->container->get($class)
                : $ref->newInstance();

            // Register any #[Route] methods on that instance
            if(!is_object($instance)) throw new \RuntimeException('Failed to create controller instance');
            $this->router->registerController($instance);
        }
    }
}