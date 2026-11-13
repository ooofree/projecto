<?php
declare(strict_types=1);

namespace ProjectO\Plugin;

use PDO;
use ProjectO\Storage\ObjectStorageInterface;
use RuntimeException;

final class PluginRegistry
{
    private array $routes = [];
    private array $publicRoutes = [];
    private array $permissions = [];
    private array $permissionNames = [];
    private array $migrations = [];
    private array $manifests = [];
    private array $enabledStates = [];

    public function __construct(
        private readonly PDO $db,
        string $modulesDirectory,
        private readonly ?ObjectStorageInterface $objectStorage = null
    )
    {
        $this->permissionNames['manage_platform'] = true;
        $manifests = [];
        $states = $db->query('SELECT plugin_id, is_enabled FROM Plugin_States')->fetchAll();
        foreach ($states as $state) {
            $this->enabledStates[(string) $state['plugin_id']] = (bool) $state['is_enabled'];
        }

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

        $this->manifests = $manifests;
        foreach ($this->orderedPluginIds($manifests) as $id) {
            $manifest = $manifests[$id];
            $enabled = $this->isEnabled($id);
            if (!$enabled) {
                continue;
            }

            foreach ($manifest['requires'] ?? [] as $dependency) {
                if ($dependency !== 'platform.core' && !isset($manifests[$dependency])) {
                    throw new RuntimeException("Plugin {$id} requires missing plugin {$dependency}.");
                }
                if (
                    $dependency !== 'platform.core'
                    && !$this->isEnabled($dependency)
                ) {
                    throw new RuntimeException("Plugin {$id} requires disabled plugin {$dependency}.");
                }
            }

            $class = $manifest['class'];
            if (!class_exists($class)) {
                throw new RuntimeException("Plugin class {$class} could not be loaded.");
            }

            $plugin = new $class(new PluginContext($db, $manifest['directory'], $this->objectStorage));
            if (!$plugin instanceof PluginInterface) {
                throw new RuntimeException("Plugin {$id} must implement PluginInterface.");
            }

            foreach ($manifest['routes'] ?? [] as $route => $handler) {
                if (isset($this->routes[$route])) {
                    throw new RuntimeException("Duplicate plugin route: {$route}.");
                }
                $this->routes[$route] = [$plugin, $handler];
            }

            // Opt-in public routes: only these GET pages may be served without
            // a signed-in session. Disabled plugins contribute no routes at
            // all, so their public pages disappear with the plugin.
            foreach ($manifest['public_routes'] ?? [] as $route) {
                if (!is_string($route) || preg_match('/^GET [a-z0-9._-]+$/', $route) !== 1) {
                    throw new RuntimeException("Plugin {$id} declares an invalid public route.");
                }
                if (!isset($this->routes[$route])) {
                    throw new RuntimeException("Plugin {$id} public route {$route} is not a registered GET route.");
                }
                $this->publicRoutes[$route] = true;
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

            $moduleDirectory = realpath($manifest['directory']);
            if ($moduleDirectory === false) {
                throw new RuntimeException("Plugin {$id} directory could not be resolved.");
            }
            foreach ($manifest['migrations'] ?? [] as $migrationDirectory) {
                if (!is_string($migrationDirectory) || $migrationDirectory === '') {
                    throw new RuntimeException("Plugin {$id} declares an invalid migration directory.");
                }

                $resolvedDirectory = realpath($moduleDirectory . DIRECTORY_SEPARATOR . $migrationDirectory);
                if (
                    $resolvedDirectory === false
                    || !str_starts_with($resolvedDirectory, $moduleDirectory . DIRECTORY_SEPARATOR)
                    || !is_dir($resolvedDirectory)
                ) {
                    throw new RuntimeException("Plugin {$id} migration directory is invalid.");
                }

                foreach (glob($resolvedDirectory . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $migrationPath) {
                    $migrationKey = $id . '/' . basename($migrationPath);
                    if (isset($this->migrations[$migrationKey])) {
                        throw new RuntimeException("Duplicate plugin migration: {$migrationKey}.");
                    }
                    $this->migrations[$migrationKey] = $migrationPath;
                }
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

    /** Whether the manifest explicitly exposes this GET route without login. */
    public function isPublicRoute(string $method, string $route): bool
    {
        return strtoupper($method) === 'GET'
            && isset($this->publicRoutes['GET ' . $route]);
    }

    public function permissions(): array
    {
        return $this->permissions;
    }

    public function migrations(): array
    {
        return $this->migrations;
    }

    public function catalog(): array
    {
        $plugins = [];
        foreach ($this->manifests as $id => $manifest) {
            $plugins[] = [
                'id' => $id,
                'name' => (string) ($manifest['name'] ?? $id),
                'version' => (string) ($manifest['version'] ?? 'unknown'),
                'enabled' => $this->isEnabled($id),
                'required' => ($manifest['required'] ?? false) === true,
                'requires' => $manifest['requires'] ?? [],
            ];
        }

        return $plugins;
    }

    private function orderedPluginIds(array $manifests): array
    {
        $ordered = [];
        $visited = [];
        $visiting = [];
        $visit = function (string $pluginId) use (&$visit, &$ordered, &$visited, &$visiting, $manifests): void {
            if (isset($visited[$pluginId])) {
                return;
            }
            if (isset($visiting[$pluginId])) {
                throw new RuntimeException('Plugin dependency cycle detected at ' . $pluginId . '.');
            }
            $visiting[$pluginId] = true;
            foreach ($manifests[$pluginId]['requires'] ?? [] as $dependency) {
                if ($dependency !== 'platform.core' && isset($manifests[$dependency])) {
                    $visit($dependency);
                }
            }
            unset($visiting[$pluginId]);
            $visited[$pluginId] = true;
            $ordered[] = $pluginId;
        };

        foreach (array_keys($manifests) as $pluginId) {
            $visit($pluginId);
        }
        return $ordered;
    }

    public function setEnabled(string $pluginId, bool $enabled): void
    {
        if (!isset($this->manifests[$pluginId])) {
            throw new \ProjectO\HttpException('插件不存在。', 404);
        }

        $manifest = $this->manifests[$pluginId];
        if (!$enabled && ($manifest['required'] ?? false) === true) {
            throw new \ProjectO\HttpException('必需插件不可停用。', 422);
        }

        if ($enabled) {
            foreach ($manifest['requires'] ?? [] as $dependency) {
                if ($dependency !== 'platform.core' && !isset($this->manifests[$dependency])) {
                    throw new \ProjectO\HttpException('缺少必需插件：' . $dependency, 422);
                }
                if ($dependency !== 'platform.core' && !$this->isEnabled($dependency)) {
                    throw new \ProjectO\HttpException('请先启用依赖插件：' . $dependency, 422);
                }
            }
        } else {
            foreach ($this->manifests as $dependentId => $dependent) {
                if (
                    $dependentId !== $pluginId
                    && $this->isEnabled($dependentId)
                    && in_array($pluginId, $dependent['requires'] ?? [], true)
                ) {
                    throw new \ProjectO\HttpException(
                        '仍有已启用插件依赖此插件：' . $dependentId,
                        422
                    );
                }
            }
        }

        $select = $this->db->prepare('SELECT 1 FROM Plugin_States WHERE plugin_id = :plugin_id');
        $select->execute(['plugin_id' => $pluginId]);
        if ($select->fetchColumn() === false) {
            $write = $this->db->prepare(
                'INSERT INTO Plugin_States (plugin_id, is_enabled, updated_by)
                 VALUES (:plugin_id, :is_enabled, :updated_by)'
            );
        } else {
            $write = $this->db->prepare(
                'UPDATE Plugin_States
                 SET is_enabled = :is_enabled, updated_by = :updated_by
                 WHERE plugin_id = :plugin_id'
            );
        }
        $write->execute([
            'plugin_id' => $pluginId,
            'is_enabled' => $enabled ? 1 : 0,
            'updated_by' => (int) ($_SESSION['user_id'] ?? 0),
        ]);
        $this->enabledStates[$pluginId] = $enabled;
    }

    private function isEnabled(string $pluginId): bool
    {
        if (array_key_exists($pluginId, $this->enabledStates)) {
            return $this->enabledStates[$pluginId];
        }

        return ($this->manifests[$pluginId]['enabled'] ?? true) === true;
    }
}
