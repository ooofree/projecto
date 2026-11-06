<?php
declare(strict_types=1);

namespace ProjectO\Storage;

use PDO;
use RuntimeException;

final class StorageFactory
{
    /**
     * Instantiates the adapter named by APP_OBJECT_STORAGE_ADAPTER.
     *
     * Adapters receive the platform database connection as an optional
     * constructor argument so they can resolve their configuration at runtime
     * (the R2 sample-image adapter reads it from System_Settings); they must
     * accept `?PDO $db = null`.
     */
    public static function fromEnvironment(?PDO $db = null): ?ObjectStorageInterface
    {
        $adapterClass = trim((string) getenv('APP_OBJECT_STORAGE_ADAPTER'));
        if ($adapterClass === '') {
            return null;
        }
        if (!class_exists($adapterClass)) {
            throw new RuntimeException('Configured object storage adapter could not be loaded.');
        }

        $adapter = new $adapterClass($db);
        if (!$adapter instanceof ObjectStorageInterface) {
            throw new RuntimeException('Configured object storage adapter must implement ObjectStorageInterface.');
        }
        return $adapter;
    }
}
