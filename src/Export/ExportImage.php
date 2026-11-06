<?php
declare(strict_types=1);

namespace ProjectO\Export;

use Throwable;

/**
 * Fetches a private document image for embedding into an exported workbook.
 * Excel cannot display WebP inside a workbook, so WebP payloads are converted
 * to PNG when GD is available.
 */
final class ExportImage
{
    private const MAX_BYTES = 4 * 1024 * 1024;
    private const EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/bmp' => 'bmp',
    ];

    /**
     * @return array{bytes: string, extension: string}|null
     */
    public static function fetch(string $url, string $contentType): ?array
    {
        $bytes = self::download($url);
        if ($bytes === null) {
            return null;
        }
        if (isset(self::EXTENSIONS[$contentType])) {
            return ['bytes' => $bytes, 'extension' => self::EXTENSIONS[$contentType]];
        }
        if ($contentType === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $image = @imagecreatefromwebp('data://image/webp;base64,' . base64_encode($bytes));
            if ($image === false) {
                return null;
            }
            $temporaryPath = tempnam(sys_get_temp_dir(), 'export-webp-');
            if ($temporaryPath === false) {
                imagedestroy($image);
                return null;
            }
            $converted = imagepng($image, $temporaryPath);
            imagedestroy($image);
            if (!$converted) {
                @unlink($temporaryPath);
                return null;
            }
            $convertedBytes = file_get_contents($temporaryPath);
            @unlink($temporaryPath);
            if (is_string($convertedBytes) && $convertedBytes !== '') {
                return ['bytes' => $convertedBytes, 'extension' => 'png'];
            }
        }

        return null;
    }

    private static function download(string $url): ?string
    {
        try {
            if (function_exists('curl_init')) {
                $handle = curl_init($url);
                if ($handle === false) {
                    return null;
                }
                curl_setopt_array($handle, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_TIMEOUT => 20,
                ]);
                $bytes = curl_exec($handle);
                $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
                curl_close($handle);
                if (!is_string($bytes) || $status >= 400) {
                    return null;
                }
            } else {
                $context = stream_context_create([
                    'http' => ['timeout' => 20],
                    'https' => ['timeout' => 20],
                ]);
                $bytes = @file_get_contents($url, false, $context);
                if (!is_string($bytes)) {
                    return null;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return $bytes === '' || strlen($bytes) > self::MAX_BYTES ? null : $bytes;
    }
}
