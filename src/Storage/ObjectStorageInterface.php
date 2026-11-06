<?php
declare(strict_types=1);

namespace ProjectO\Storage;

interface ObjectStorageInterface
{
    public function isConfigured(): bool;

    public function upload(string $objectKey, string $filePath, string $contentType): void;

    public function signedReadUrl(string $objectKey, string $contentType): string;

    /**
     * Reads the object bytes server-side. Used by the application image
     * endpoints so browsers never need direct access to the private bucket.
     */
    public function read(string $objectKey): string;

    public function delete(string $objectKey): void;
}
