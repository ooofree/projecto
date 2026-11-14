<?php
declare(strict_types=1);

namespace ProjectO\Plugin;

use PDO;
use ProjectO\ProjectContext;
use ProjectO\Storage\ObjectStorageInterface;
use RuntimeException;

final class PluginContext
{
    private ProjectContext $projectContext;

    public function __construct(
        public readonly PDO $db,
        private readonly string $moduleDirectory,
        private readonly ?ObjectStorageInterface $objectStorage = null,
        private readonly ?PluginEventBus $events = null
    ) {
        $this->projectContext = new ProjectContext($db);
    }

    /**
     * Announce a domain event to whichever plugins subscribed to it. Silent
     * no-op when the context was built without a bus (e.g. isolated tests).
     *
     * @param array<string, mixed> $payload
     */
    public function emit(string $event, array $payload = []): void
    {
        $this->events?->emit($event, $payload);
    }

    /** Subscribe to another plugin's event; no-op without a bus. */
    public function on(string $event, callable $listener): void
    {
        $this->events?->subscribe($event, $listener);
    }

    public function currentProject(): ?array
    {
        return $this->projectContext->current();
    }

    public function requireCurrentProject(): array
    {
        return $this->projectContext->requireCurrent();
    }

    public function objectStorage(): ?ObjectStorageInterface
    {
        return $this->objectStorage;
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
