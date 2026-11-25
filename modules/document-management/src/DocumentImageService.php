<?php
declare(strict_types=1);

namespace ProjectO\Modules\DocumentManagement;

use PDO;
use ProjectO\HttpException;
use ProjectO\Storage\ImageUpload;
use ProjectO\Storage\ObjectStorageInterface;
use Throwable;

/**
 * Private image attachments for document issues and RFIs.
 *
 * Two sources share one table: files in the platform object storage (referenced
 * by object_key) and https external links (referenced by external_url only, no
 * download and no storage usage). Callers resolve a display URL through
 * imageDisplayUrl() so a page never has to know which source produced the row.
 */
final class DocumentImageService
{
    public const EXTERNAL_CONTENT_TYPE = 'image/external';

    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return array<int, list<array<string, mixed>>> keyed by issue id
     */
    public function issueImagesForProject(int $projectId): array
    {
        return $this->imagesForProject('document_issue_images', 'issue_id', $projectId);
    }

    /**
     * @return array<int, list<array<string, mixed>>> keyed by RFI id
     */
    public function rfiImagesForProject(int $projectId): array
    {
        return $this->imagesForProject('document_rfi_images', 'rfi_id', $projectId);
    }

    public function addIssueImage(
        int $projectId,
        int $issueId,
        int $userId,
        string $temporaryPath,
        string $originalName,
        ObjectStorageInterface $storage
    ): int {
        $this->requireIssue($projectId, $issueId);

        return $this->addImage(
            'document_issue_images',
            'issue_id',
            'issues',
            $projectId,
            $issueId,
            $userId,
            $temporaryPath,
            $originalName,
            $storage
        );
    }

    public function addRfiImage(
        int $projectId,
        int $rfiId,
        int $userId,
        string $temporaryPath,
        string $originalName,
        ObjectStorageInterface $storage
    ): int {
        $this->requireRfi($projectId, $rfiId);

        return $this->addImage(
            'document_rfi_images',
            'rfi_id',
            'rfis',
            $projectId,
            $rfiId,
            $userId,
            $temporaryPath,
            $originalName,
            $storage
        );
    }

    /**
     * Records an https image link without fetching or storing the file.
     */
    public function addIssueImageLink(
        int $projectId,
        int $issueId,
        int $userId,
        string $url,
        string $name = ''
    ): int {
        $this->requireIssue($projectId, $issueId);

        return $this->addImageLink('document_issue_images', 'issue_id', $projectId, $issueId, $userId, $url, $name);
    }

    public function addRfiImageLink(
        int $projectId,
        int $rfiId,
        int $userId,
        string $url,
        string $name = ''
    ): int {
        $this->requireRfi($projectId, $rfiId);

        return $this->addImageLink('document_rfi_images', 'rfi_id', $projectId, $rfiId, $userId, $url, $name);
    }

    /**
     * Display URL for an already-authorized image row: the stored external link
     * wins, otherwise the caller builds the app's private image proxy URL.
     */
    public static function imageDisplayUrl(array $image, ?string $proxyUrl = null): ?string
    {
        $external = $image['external_url'] ?? null;
        if (is_string($external) && $external !== '') {
            return $external;
        }

        return $proxyUrl;
    }

    public static function isExternal(array $image): bool
    {
        $external = $image['external_url'] ?? null;

        return is_string($external) && $external !== '';
    }

    /**
     * Accepts only public https image addresses.
     *
     * http is refused on purpose: the application is deployed over https and a
     * browser blocks mixed-content images, so accepting one would store a link
     * that can never render. Loopback, private and reserved hosts are refused
     * to avoid turning the field into an SSRF pivot for the export routine.
     */
    public static function normalizeExternalUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new HttpException('请填写图片地址。', 422);
        }
        if (mb_strlen($value) > 1024) {
            throw new HttpException('图片地址过长（最多 1024 个字符）。', 422);
        }
        if (preg_match('/[\x00-\x1F\x7F"\'<>]/u', $value) === 1) {
            throw new HttpException('图片地址包含无效字符。', 422);
        }

        $parts = parse_url($value);
        if ($parts === false || !isset($parts['scheme'], $parts['host'], $parts['path'])) {
            throw new HttpException('图片地址格式无效，请填写完整的 https 地址。', 422);
        }
        if (strtolower((string) $parts['scheme']) !== 'https') {
            throw new HttpException('只支持 https 图片地址；http 地址在 https 页面中会被浏览器拦截。', 422);
        }
        $host = strtolower((string) $parts['host']);
        if ($host === '' || !empty($parts['user']) || !empty($parts['pass'])) {
            throw new HttpException('图片地址不允许携带账号信息。', 422);
        }
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            throw new HttpException('图片地址必须是公网地址，不能使用本机或内网域名。', 422);
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
        ) {
            throw new HttpException('图片地址必须是公网地址，不能指向内网或保留网段。', 422);
        }

        return $value;
    }

    public function deleteIssueImage(
        int $projectId,
        int $issueId,
        int $imageId,
        ObjectStorageInterface $storage
    ): void {
        $image = $this->imageRow('document_issue_images', 'issue_id', $projectId, $issueId, $imageId);
        $objectKey = $image['object_key'] ?? null;
        if (is_string($objectKey) && $objectKey !== '' && $storage->isConfigured()) {
            $storage->delete($objectKey);
        }
        $delete = $this->db->prepare(
            'DELETE FROM document_issue_images WHERE image_id = :image_id AND project_id = :project_id'
        );
        $delete->execute(['image_id' => $imageId, 'project_id' => $projectId]);
    }

    public function deleteRfiImage(
        int $projectId,
        int $rfiId,
        int $imageId,
        ObjectStorageInterface $storage
    ): void {
        $image = $this->imageRow('document_rfi_images', 'rfi_id', $projectId, $rfiId, $imageId);
        $objectKey = $image['object_key'] ?? null;
        if (is_string($objectKey) && $objectKey !== '' && $storage->isConfigured()) {
            $storage->delete($objectKey);
        }
        $delete = $this->db->prepare(
            'DELETE FROM document_rfi_images WHERE image_id = :image_id AND project_id = :project_id'
        );
        $delete->execute(['image_id' => $imageId, 'project_id' => $projectId]);
    }

    public function requireIssue(int $projectId, int $issueId): void
    {
        $check = $this->db->prepare(
            'SELECT 1 FROM document_issues WHERE issue_id = :issue_id AND project_id = :project_id'
        );
        $check->execute(['issue_id' => $issueId, 'project_id' => $projectId]);
        if ($check->fetchColumn() === false) {
            throw new HttpException('问题不存在或不属于当前项目。', 404);
        }
    }

    /**
     * Loads one image of an issue or RFI scoped to the current project.
     *
     * @return array<string, mixed>
     */
    public function requireImage(string $type, int $projectId, int $ownerId, int $imageId): array
    {
        [$table, $ownerColumn] = $type === 'issue'
            ? ['document_issue_images', 'issue_id']
            : ['document_rfi_images', 'rfi_id'];
        $statement = $this->db->prepare(
            'SELECT image_id, object_key, external_url, original_name, content_type FROM ' . $table . '
             WHERE image_id = :image_id AND project_id = :project_id AND ' . $ownerColumn . ' = :owner_id'
        );
        $statement->execute([
            'image_id' => $imageId,
            'project_id' => $projectId,
            'owner_id' => $ownerId,
        ]);
        $image = $statement->fetch();
        if ($image === false) {
            throw new HttpException('图片记录不存在。', 404);
        }

        return $image;
    }

    public function requireRfi(int $projectId, int $rfiId): void
    {
        $check = $this->db->prepare(
            'SELECT 1 FROM document_rfis WHERE rfi_id = :rfi_id AND project_id = :project_id'
        );
        $check->execute(['rfi_id' => $rfiId, 'project_id' => $projectId]);
        if ($check->fetchColumn() === false) {
            throw new HttpException('RFI 不存在或不属于当前项目。', 404);
        }
    }

    private function addImage(
        string $table,
        string $ownerColumn,
        string $keyPrefix,
        int $projectId,
        int $ownerId,
        int $userId,
        string $temporaryPath,
        string $originalName,
        ObjectStorageInterface $storage
    ): int {
        $image = ImageUpload::inspect($temporaryPath);
        $originalName = ImageUpload::originalName($originalName, $image['extension']);
        $objectKey = $keyPrefix . '/' . $projectId . '/' . $ownerId . '/'
            . bin2hex(random_bytes(16)) . '.' . $image['extension'];

        $storage->upload($objectKey, $temporaryPath, $image['mime']);
        try {
            $insert = $this->db->prepare(
                'INSERT INTO ' . $table . ' (' . $ownerColumn . ', project_id, object_key, original_name, content_type, file_size, uploaded_by)
                 VALUES (:owner_id, :project_id, :object_key, :original_name, :content_type, :file_size, :uploaded_by)'
            );
            $insert->execute([
                'owner_id' => $ownerId,
                'project_id' => $projectId,
                'object_key' => $objectKey,
                'original_name' => $originalName,
                'content_type' => $image['mime'],
                'file_size' => $image['size'],
                'uploaded_by' => $userId,
            ]);

            return (int) $this->db->lastInsertId();
        } catch (Throwable $exception) {
            try {
                $storage->delete($objectKey);
            } catch (Throwable $cleanupException) {
                error_log('Unable to remove an unreferenced document image: ' . get_class($cleanupException));
            }
            throw $exception;
        }
    }

    private function addImageLink(
        string $table,
        string $ownerColumn,
        int $projectId,
        int $ownerId,
        int $userId,
        string $url,
        string $name
    ): int {
        $url = self::normalizeExternalUrl($url);
        $name = trim($name);
        if ($name === '') {
            $path = (string) parse_url($url, PHP_URL_PATH);
            $name = rawurldecode(basename($path));
        }
        if (mb_strlen($name) > 255) {
            $name = mb_substr($name, 0, 255);
        }
        if ($name === '') {
            $name = '外链图片';
        }

        $insert = $this->db->prepare(
            'INSERT INTO ' . $table . ' (' . $ownerColumn . ', project_id, object_key, external_url, original_name, content_type, file_size, uploaded_by)
             VALUES (:owner_id, :project_id, NULL, :external_url, :original_name, :content_type, 0, :uploaded_by)'
        );
        $insert->execute([
            'owner_id' => $ownerId,
            'project_id' => $projectId,
            'external_url' => $url,
            'original_name' => $name,
            'content_type' => self::EXTERNAL_CONTENT_TYPE,
            'uploaded_by' => $userId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    private function imagesForProject(string $table, string $ownerColumn, int $projectId): array    {
        $statement = $this->db->prepare(
            'SELECT image_id, ' . $ownerColumn . ' AS owner_id, object_key, external_url, original_name, content_type, file_size, created_at
             FROM ' . $table . ' WHERE project_id = :project_id ORDER BY image_id'
        );
        $statement->execute(['project_id' => $projectId]);
        $map = [];
        foreach ($statement->fetchAll() as $row) {
            $map[(int) $row['owner_id']][] = $row;
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    private function imageRow(
        string $table,
        string $ownerColumn,
        int $projectId,
        int $ownerId,
        int $imageId
    ): array {
        $statement = $this->db->prepare(
            'SELECT image_id, object_key FROM ' . $table . '
             WHERE image_id = :image_id AND project_id = :project_id AND ' . $ownerColumn . ' = :owner_id'
        );
        $statement->execute([
            'image_id' => $imageId,
            'project_id' => $projectId,
            'owner_id' => $ownerId,
        ]);
        $row = $statement->fetch();
        if ($row === false) {
            throw new HttpException('图片记录不存在。', 404);
        }

        return $row;
    }
}
