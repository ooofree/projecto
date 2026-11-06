<?php
declare(strict_types=1);

namespace ProjectO\Storage;

use finfo;
use ProjectO\HttpException;

/**
 * Validates uploaded image files for the private object storage. This is the
 * single source of truth for the allowed image types and the size limit,
 * shared by document issue/RFI images and material sample images.
 */
final class ImageUpload
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/bmp' => 'bmp',
        'image/webp' => 'webp',
    ];

    /**
     * @return array{mime: string, extension: string, size: int}
     */
    public static function inspect(string $temporaryPath): array
    {
        $size = filesize($temporaryPath);
        if ($size === false || $size < 1 || $size > self::MAX_BYTES) {
            throw new HttpException('图片大小必须大于 0 且不超过 5 MB。', 422);
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        if (!is_string($mime) || !isset(self::EXTENSIONS[$mime])) {
            throw new HttpException('图片仅支持 PNG、JPEG、GIF、BMP 和 WebP 格式。', 422);
        }

        return [
            'mime' => $mime,
            'extension' => self::EXTENSIONS[$mime],
            'size' => $size,
        ];
    }

    public static function originalName(string $originalName, string $extension): string
    {
        $originalName = trim(
            preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $originalName))) ?? ''
        );
        if ($originalName === '') {
            $originalName = 'image.' . $extension;
        }

        return mb_substr($originalName, 0, 255);
    }
}
