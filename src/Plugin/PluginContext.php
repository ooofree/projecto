<?php
declare(strict_types=1);

namespace ProjectO\Plugin;

use PDO;
use ProjectO\ProjectContext;
use RuntimeException;

final class PluginContext
{
    private ProjectContext $projectContext;

    public function __construct(
        public readonly PDO $db,
        private readonly string $moduleDirectory
    ) {
        $this->projectContext = new ProjectContext($db);
    }

    public function currentProject(): ?array
    {
        return $this->projectContext->current();
    }

    public function requireCurrentProject(): array
    {
        return $this->projectContext->requireCurrent();
    }

    public function view(string $relativePath, array $data = []): PluginResponse
    {
        $viewDirectory = realpath($this->moduleDirectory . '/views');
        $viewPath = realpath($this->moduleDirectory . '/views/' . $relativePath);
        if (
            $viewDirectory === false
            || $viewPath === false
            || !str_starts_with($viewPath, $viewDirectory . DIRECTORY_SEPARATOR)
            || !is_file($viewPath)
        ) {
            throw new RuntimeException('Plugin view not found.');
        }

        return PluginResponse::view($viewPath, $data);
    }
}
