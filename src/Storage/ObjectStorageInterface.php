<?php
declare(strict_types=1);

namespace ProjectO\Storage;

interface ObjectStorageInterface
{
    public function isConfigured(): bool;

    public function upload(string $objectKey, string $filePath, string $contentType): void;

    public function signedReadUrl(string $objectKey, string $contentType): string;

    public function delete(string $objectKey): void;
}
