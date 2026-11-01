<?php
declare(strict_types=1);

namespace ProjectO\Plugin;

final class PluginResponse
{
    private function __construct(
        public readonly string $type,
        public readonly array $data = [],
        public readonly ?string $viewPath = null,
        public readonly ?string $location = null
    ) {
    }

    public static function view(string $viewPath, array $data = []): self
    {
        return new self('view', $data, $viewPath);
    }

    public static function redirect(string $location): self
    {
        return new self('redirect', location: $location);
    }
}
