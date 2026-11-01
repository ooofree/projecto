<?php
declare(strict_types=1);

namespace ProjectO\Modules\MaterialManagement;

use Aws\S3\S3Client;
use ProjectO\HttpException;
use ProjectO\Storage\ObjectStorageInterface;
use RuntimeException;

final class R2SampleImageStorage implements ObjectStorageInterface
{
    private ?S3Client $client = null;
    private string $accountId;
    private string $bucket;
    private string $accessKey;
    private string $secretKey;

    public function __construct()
    {
        $this->accountId = trim((string) getenv('APP_R2_ACCOUNT_ID'));
        $this->bucket = trim((string) getenv('APP_R2_BUCKET'));
        $this->accessKey = trim((string) getenv('APP_R2_ACCESS_KEY_ID'));
        $this->secretKey = (string) getenv('APP_R2_SECRET_ACCESS_KEY');
    }

    public function isConfigured(): bool
    {
        return preg_match('/^[a-f0-9]{32}$/i', $this->accountId) === 1
            && preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/', $this->bucket) === 1
            && $this->accessKey !== ''
            && $this->secretKey !== ''
            && class_exists(S3Client::class);
    }

    public function upload(string $objectKey, string $filePath, string $contentType): void
    {
        $body = fopen($filePath, 'rb');
        if ($body === false) {
            throw new RuntimeException('Unable to read the uploaded sample image.');
        }

        try {
            $this->client()->putObject([
                'Bucket' => $this->bucket,
                'Key' => $objectKey,
                'Body' => $body,
                'ContentType' => $contentType,
            ]);
        } finally {
            fclose($body);
        }
    }

    public function signedReadUrl(string $objectKey, string $contentType): string
    {
        $command = $this->client()->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key' => $objectKey,
            'ResponseContentType' => $contentType,
            'ResponseContentDisposition' => 'inline',
        ]);
        return (string) $this->client()->createPresignedRequest($command, '+5 minutes')->getUri();
    }

    public function delete(string $objectKey): void
    {
        $this->client()->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $objectKey,
        ]);
    }

    private function client(): S3Client
    {
        if (!$this->isConfigured()) {
            throw new HttpException('尚未配置 R2 图片存储，请联系部署管理员。', 503);
        }
        if ($this->client === null) {
            $this->client = new S3Client([
                'version' => 'latest',
                'region' => 'auto',
                'endpoint' => 'https://' . $this->accountId . '.r2.cloudflarestorage.com',
                'use_path_style_endpoint' => true,
                'credentials' => [
                    'key' => $this->accessKey,
                    'secret' => $this->secretKey,
                ],
            ]);
        }
        return $this->client;
    }
}
