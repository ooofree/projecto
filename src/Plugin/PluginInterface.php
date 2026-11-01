<?php
declare(strict_types=1);

namespace ProjectO\Plugin;

interface PluginInterface
{
    public function dispatch(string $handler, array $input): PluginResponse;
}
