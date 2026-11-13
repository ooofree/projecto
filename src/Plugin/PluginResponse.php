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

    public static function download(string $filename, string $contentType, string $body): self
    {
        return new self('download', [
            'filename' => $filename,
            'content_type' => $contentType,
            'body' => $body,
        ]);
    }

    /**
     * Inline binary response for private images served through the application.
     */
    public static function inline(string $filename, string $contentType, string $body): self
    {
        return new self('inline', [
            'filename' => $filename,
            'content_type' => $contentType,
            'body' => $body,
        ]);
    }

    /**
     * Pre-rendered body served verbatim (e.g. the static public share page).
     * Extra headers (ETag, Cache-Control, Last-Modified) travel in data.
     */
    public static function raw(string $body, string $contentType, array $headers = []): self
    {
        return new self('raw', [
            'body' => $body,
            'content_type' => $contentType,
            'headers' => $headers,
        ]);
    }

    /** 304 Not Modified for conditional requests against a static resource. */
    public static function notModified(array $headers = []): self
    {
        return new self('not_modified', ['headers' => $headers]);
    }
}
