<?php
declare(strict_types=1);

namespace ProjectO\Plugin;

/**
 * Resolves the public service contracts declared by other plugins.
 *
 * The core owns only the lookup. A plugin may reach another plugin's service
 * exclusively through a core-owned interface that the provider published in its
 * manifest and the consumer declared in `uses`; internal classes and tables stay
 * private.
 */
interface ServiceLocator
{
    /**
     * @template T of object
     * @param class-string<T> $contract
     * @return T
     * @throws PluginContractException when undeclared, unpublished or unavailable
     */
    public function service(string $contract): object;
}
