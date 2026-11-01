<?php
declare(strict_types=1);

namespace ProjectO\Plugin;

use PDO;
use RuntimeException;

final class PluginRegistry
{
    private array $routes = [];
    private array $permissions = [];
    private array $permissionNames = [];

    public function __construct(PDO $db, string $modulesDirectory)
    {
        $manifests = [];
        foreach (glob($modulesDirectory . '/*/plugin.json') ?: [] as $manifestPath) {
            $contents = file_get_contents($manifestPath);
            if ($contents === false) {
                throw new RuntimeException('Unable to read plugin manifest: ' . $manifestPath);
            }

            $manifest = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($manifest) || !isset($manifest['id'], $manifest['class'])) {
                throw new RuntimeException('Invalid plugin manifest: ' . $manifestPath);
            }
            if (isset($manifests[$manifest['id']])) {
                throw new RuntimeException('Duplicate plugin ID: ' . $manifest['id'] . '.');
            }

            $manifest['directory'] = dirname($manifestPath);
            $manifests[$manifest['id']] = $manifest;
        }

        foreach ($manifests as $id => $manifest) {
            if (($manifest['enabled'] ?? true) !== true) {
                continue;
            }

            foreach ($manifest['requires'] ?? [] as $dependency) {
                if ($dependency !== 'platform.core' && !isset($manifests[$dependency])) {
                    throw new RuntimeException("Plugin {$id} requires missing plugin {$dependency}.");
                }
                if (
                    $dependency !== 'platform.core'
                    && ($manifests[$dependency]['enabled'] ?? true) !== true
                ) {
                    throw new RuntimeException("Plugin {$id} requires disabled plugin {$dependency}.");
                }
            }

            $class = $manifest['class'];
            if (!class_exists($class)) {
                throw new RuntimeException("Plugin class {$class} could not be loaded.");
            }

            $plugin = new $class(new PluginContext($db, $manifest['directory']));
            if (!$plugin instanceof PluginInterface) {
                throw new RuntimeException("Plugin {$id} must implement PluginInterface.");
            }

            foreach ($manifest['routes'] ?? [] as $route => $handler) {
                if (isset($this->routes[$route])) {
                    throw new RuntimeException("Duplicate plugin route: {$route}.");
                }
                $this->routes[$route] = [$plugin, $handler];
            }

            foreach ($manifest['permissions'] ?? [] as $permission) {
                if (!isset($permission['name'], $permission['display_name'])) {
                    throw new RuntimeException("Plugin {$id} declares an invalid permission.");
                }
                if (isset($this->permissionNames[$permission['name']])) {
                    throw new RuntimeException(
                        "Duplicate plugin permission: {$permission['name']}."
                    );
                }
                $this->permissionNames[$permission['name']] = true;
                $permission['module'] = $manifest['name'] ?? $id;
                $this->permissions[] = $permission;
            }
        }
    }

    public function dispatch(string $method, string $route, array $input): ?PluginResponse
    {
        $key = strtoupper($method) . ' ' . $route;
        if (!isset($this->routes[$key])) {
            return null;
        }

        [$plugin, $handler] = $this->routes[$key];
        return $plugin->dispatch($handler, $input);
    }

    public function permissions(): array
    {
        return $this->permissions;
    }
}
