<?php
declare(strict_types=1);

namespace ProjectO\Storage;

use RuntimeException;

final class StorageFactory
{
    public static function fromEnvironment(): ?ObjectStorageInterface
    {
        $adapterClass = trim((string) getenv('APP_OBJECT_STORAGE_ADAPTER'));
        if ($adapterClass === '') {
            return null;
        }
        if (!class_exists($adapterClass)) {
            throw new RuntimeException('Configured object storage adapter could not be loaded.');
        }

        $adapter = new $adapterClass();
        if (!$adapter instanceof ObjectStorageInterface) {
            throw new RuntimeException('Configured object storage adapter must implement ObjectStorageInterface.');
        }
        return $adapter;
    }
}
