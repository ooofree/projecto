<?php
declare(strict_types=1);

namespace ProjectO\Plugin;

/**
 * A plugin that publishes implementations of core-owned service contracts.
 *
 * The manifest "provides" list is the public declaration; this method returns
 * the instance behind it. Only core contract interfaces may be published, so no
 * plugin exposes its internal classes to another plugin.
 */
interface ServicePublisher
{
    /** @param class-string $contract */
    public function publishedService(string $contract): object;
}
