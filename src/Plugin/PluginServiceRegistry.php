<?php
declare(strict_types=1);

namespace ProjectO\Plugin;

/**
 * In-process registry of published plugin service contracts.
 *
 * Providers register an instance against a core-owned interface; consumers may
 * resolve it only when their manifest declares that interface in `uses`.
 */
final class PluginServiceRegistry
{
    /** @var array<string, array{plugin: string, service: object}> */
    private array $providers = [];

    /** @var array<string, array<int, string>> */
    private array $declared = [];

    /** @param array<int, string> $contracts */
    public function declare(string $pluginId, array $contracts): void
    {
        foreach ($contracts as $contract) {
            $this->declared[$pluginId][] = $contract;
        }
    }

    public function publish(string $pluginId, string $contract, object $service): void
    {
        if (isset($this->providers[$contract])) {
            throw new PluginContractException('Duplicate provider for contract ' . $contract . '.');
        }
        if (!$service instanceof $contract) {
            throw new PluginContractException('Service for ' . $contract . ' must implement that interface.');
        }
        $this->providers[$contract] = ['plugin' => $pluginId, 'service' => $service];
    }

    public function forPlugin(string $pluginId): ServiceLocator
    {
        return new class ($this, $pluginId) implements ServiceLocator {
            public function __construct(
                private readonly PluginServiceRegistry $registry,
                private readonly string $pluginId
            ) {
            }

            public function service(string $contract): object
            {
                return $this->registry->resolve($this->pluginId, $contract);
            }
        };
    }

    public function resolve(string $consumerId, string $contract): object
    {
        if (!in_array($contract, $this->declared[$consumerId] ?? [], true)) {
            throw new PluginContractException(
                'Plugin ' . $consumerId . ' must declare ' . $contract . ' in its manifest "uses" list.'
            );
        }
        if (!isset($this->providers[$contract])) {
            throw new PluginContractException(
                'No enabled plugin provides ' . $contract . '; check plugin dependencies and enabled state.'
            );
        }

        return $this->providers[$contract]['service'];
    }
}
