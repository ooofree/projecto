<?php
declare(strict_types=1);

namespace ProjectO\Modules\LaborCrew;

use PDO;
use ProjectO\HttpException;
use ProjectO\Plugin\PluginResponse;
use ProjectO\Support;

/**
 * Pre-rendered static public contact pages.
 *
 * The share page is generated as a standalone HTML file (inline CSS/JS, zero
 * external requests) whenever the underlying data changes, and stored OUTSIDE
 * the web root under storage/labor-crew-shares/<token>.html. A public request
 * only validates the token, then streams the file — no crew queries, no
 * rendering, no session. ETag/304 makes repeat visits nearly free.
 *
 * Freshness contract:
 *  - every crew/type mutation inside this plugin regenerates the file;
 *  - enabling a share generates it, revoking deletes it;
 *  - cross-plugin events we cannot hook (project archived/deleted, plugin
 *    disabled) are caught by a single indexed validation query at serve time;
 *    a stale file is then removed and the link 404s. While the plugin is
 *    disabled no crew data can change, so re-enabling never serves stale rows.
 *  - a missing file for a still-valid share is regenerated lazily (this also
 *    upgrades shares created before static pages existed).
 *
 * If the storage directory is not writable the page still works: the render
 * fallback serves the same HTML from memory and the write failure is logged.
 */
final class CrewSharePage
{
    /**
     * The static page is a trusted pre-rendered document with inline CSS/JS
     * and zero external loads: allow inline code, deny every network source.
     */
    private const CSP = "default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; img-src data:; form-action 'none'; base-uri 'none'; frame-ancestors 'none'";

    public function __construct(
        private readonly PDO $db,
        private readonly CrewDirectoryService $directory,
        private readonly CrewShareService $shares
    ) {
    }

    /**
     * Serve the public page for a token. Authorization = active share + project
     * not archived (one indexed query); content = the pre-rendered file.
     */
    public function serve(string $token, ?string $ifNoneMatch): PluginResponse
    {
        if (preg_match('/^[0-9a-f]{32}$/', $token) !== 1) {
            throw new HttpException('分享链接无效或已停用，请联系分享者获取新链接。', 404);
        }

        $project = $this->shares->projectForToken($token);
        if ($project === null) {
            // Revoked, archived or unknown: drop any stale file so it can never
            // be served again, even if a later check were bypassed.
            $this->removeFile($token);
            throw new HttpException('分享链接无效或已停用，请联系分享者获取新链接。', 404);
        }

        $path = $this->pathForToken($token);
        if ($this->needsRebuild($path)) {
            $this->writePage($token, $project);
        }

        if (!is_file($path)) {
            // Storage unwritable: serve the same HTML from memory so sharing
            // keeps working (the write failure was logged in writePage()).
            $html = $this->renderFallback($project);
            $etag = '"cs-mem-' . sha1($html) . '"';
            $headers = ['ETag' => $etag, 'Cache-Control' => 'public, no-cache', 'Content-Security-Policy' => self::CSP];
            if ($ifNoneMatch !== null && trim($ifNoneMatch) === $etag) {
                return PluginResponse::notModified($headers);
            }

            return PluginResponse::raw($html, 'text/html; charset=utf-8', $headers);
        }

        $mtime = (int) filemtime($path);
        $size = (int) filesize($path);
        $etag = '"cs-' . sha1($token . ':' . $mtime . ':' . $size . ':' . $this->layoutVersion()) . '"';
        $headers = [
            'ETag' => $etag,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $mtime) . ' GMT',
            // Revalidate on every use so edits/revocations propagate at once;
            // the ETag keeps that revalidation to a few hundred bytes.
            'Cache-Control' => 'public, no-cache',
            // MUST travel on 304 responses too: the browser applies the
            // revalidation response's headers to the cached document, and the
            // entry script's restrictive CSP would otherwise replace this one
            // and block the page's inline CSS/JS on every repeat visit.
            'Content-Security-Policy' => self::CSP,
        ];
        if ($ifNoneMatch !== null && trim($ifNoneMatch) === $etag) {
            return PluginResponse::notModified($headers);
        }

        $html = file_get_contents($path);
        if ($html === false) {
            throw new HttpException('页面暂时无法读取，请稍后重试。', 500);
        }

        return PluginResponse::raw($html, 'text/html; charset=utf-8', $headers);
    }

    /** Regenerate the static page after any data change in this project. */
    public function refresh(int $projectId): void
    {
        $share = $this->shares->activeShare($projectId);
        if ($share === null) {
            return;
        }
        $token = (string) $share['token'];
        $project = $this->shares->projectForToken($token);
        if ($project === null) {
            $this->removeFile($token);
            return;
        }
        $this->writePage($token, $project);
    }

    /** Craft-type names are global reference data: refresh every active share. */
    public function refreshAll(): void
    {
        foreach ($this->shares->allActiveShares() as $share) {
            $token = (string) $share['token'];
            $project = $this->shares->projectForToken($token);
            if ($project === null) {
                $this->removeFile($token);
                continue;
            }
            $this->writePage($token, $project);
        }
    }

    /** Delete the static page (share revoked). The DB row is kept for audit. */
    public function removeForProject(int $projectId): void
    {
        $statement = $this->db->prepare('SELECT token FROM Labor_Crew_Shares WHERE project_id = :project_id');
        $statement->execute(['project_id' => $projectId]);
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $token) {
            if (is_string($token)) {
                $this->removeFile($token);
            }
        }
    }

    private function writePage(string $token, array $project): void
    {
        $html = $this->render($project, $this->directory->exportRows((int) $project['project_id']));
        $path = $this->pathForToken($token);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            error_log('labor.crew: unable to create static share directory ' . $directory);
            return; // serve() falls back to the freshly written file check below
        }
        // Deny direct HTTP access in deployments where storage/ sits under the
        // document root; the application reads the file from disk regardless.
        $htaccess = $directory . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\n");
        }

        // Atomic publish: readers never observe a half-written page.
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $html) === false || !@rename($tmp, $path)) {
            @unlink($tmp);
            error_log('labor.crew: unable to write static share page for project ' . (int) $project['project_id']);
        }
    }

    /**
     * Render the standalone page. If the file could not be written, serve()
     * still needs content: render on demand as a resilient fallback.
     */
    public function renderFallback(array $project): string
    {
        return $this->render($project, $this->directory->exportRows((int) $project['project_id']));
    }

    private function pathForToken(string $token): string
    {
        // Token format is validated before this is called: 32 hex chars only,
        // so the path can never escape the share directory.
        return dirname(__DIR__, 3) . '/storage/labor-crew-shares/' . $token . '.html';
    }

    private function removeFile(string $token): void
    {
        if (preg_match('/^[0-9a-f]{32}$/', $token) !== 1) {
            return;
        }
        $path = $this->pathForToken($token);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** @param array<int, array<string, string|null>> $rows */
    private function render(array $project, array $rows): string
    {
        // Public visitors get the system default timezone, never the personal
        // zone of whichever admin happened to trigger the regeneration.
        $timezone = 'Asia/Shanghai';
        $statement = $this->db->prepare("SELECT setting_value FROM System_Settings WHERE setting_key = 'timezone'");
        $statement->execute();
        $stored = $statement->fetchColumn();
        if (is_string($stored) && Support::isValidTimezone($stored)) {
            $timezone = $stored;
        }
        $generatedAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone($timezone))
            ->format('Y-m-d H:i');

        $escape = [Support::class, 'escape'];
        $crews = $rows;
        $layoutVersion = $this->layoutVersion();
        ob_start();
        require dirname(__DIR__) . '/views/share.php';

        return (string) ob_get_clean();
    }

    /**
     * Fingerprint of the page template. Editing the layout therefore invalidates
     * every already-generated file, so existing share links pick up the new
     * markup on their next request without anyone touching the contact data.
     */
    private function layoutVersion(): string
    {
        $template = dirname(__DIR__) . '/views/share.php';
        $mtime = @filemtime($template);
        $size = @filesize($template);

        return substr(sha1(($mtime === false ? 0 : $mtime) . ':' . ($size === false ? 0 : $size)), 0, 12);
    }

    /** Missing file, or a file rendered by an older template, must be rebuilt. */
    private function needsRebuild(string $path): bool
    {
        if (!is_file($path)) {
            return true;
        }
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return true;
        }
        $head = (string) fread($handle, 1024);
        fclose($handle);

        return !str_contains($head, 'data-layout="' . $this->layoutVersion() . '"');
    }
}
