<?php
declare(strict_types=1);

namespace ProjectO\Modules\LaborCrew;

use PDO;
use ProjectO\HttpException;
use ProjectO\Plugin\PluginResponse;
use ProjectO\Support;

/**
 * Pre-rendered static public contact pages served at a fixed URL.
 *
 * Each share owns one permanent address — <base>/s/<token>.html — published as
 * a real file inside public/s/ together with its card thumbnail <token>.png.
 * Apache serves those bytes directly: no PHP, no session, no database query and
 * no rendering on read. public/s/.htaccess supplies the page's own CSP and
 * revalidation headers, so the document behaves identically to the app-served
 * version.
 *
 * Writes happen only when something actually changes:
 *  - crew create/edit/archive/restore and craft-type edits rebuild the file;
 *  - enabling a share publishes it, revoking deletes it;
 *  - project archived/deleted and plugin disabled (core events) delete it,
 *    project restored / plugin enabled republish it.
 *
 * The legacy ?page=labor-crew-share&token=… route stays alive as a redirect to
 * the fixed address, so links already handed out keep working. It is also the
 * recovery path: if the published file is missing while the share is valid
 * (e.g. it was created before static publishing existed), it republishes and
 * redirects. Normal reads never touch this code.
 */
final class CrewSharePage
{
    /**
     * The static page is a trusted pre-rendered document with inline CSS/JS
     * and zero external loads: allow inline code, deny every network source.
     */
    private const CSP = "default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; img-src 'self' data:; form-action 'none'; base-uri 'none'; frame-ancestors 'none'";

    public function __construct(
        private readonly PDO $db,
        private readonly CrewDirectoryService $directory,
        private readonly CrewShareService $shares
    ) {
    }

    /**
     * Legacy entry point: redirect to the fixed static address. Only reached by
     * old links (or when a published file went missing), never by normal reads
     * of the static URL, which Apache answers without PHP.
     */
    public function serve(string $token, ?string $ifNoneMatch): PluginResponse
    {
        if (preg_match('/^[0-9a-f]{32}$/', $token) !== 1) {
            throw new HttpException('分享链接无效或已停用，请联系分享者获取新链接。', 404);
        }

        $project = $this->shares->projectForToken($token);
        if ($project === null) {
            // Revoked, archived or unknown: drop any stale artifact so it can
            // never be served again.
            $this->removeFile($token);
            throw new HttpException('分享链接无效或已停用，请联系分享者获取新链接。', 404);
        }

        if (!is_file($this->pathForToken($token))) {
            // Recovery/upgrade path for shares published before static files
            // existed, or after an accidental deletion.
            $this->writePage($token, $project);
        }

        return PluginResponse::redirect(CrewShareService::publicUrl($token));
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
        $rows = $this->directory->exportRows((int) $project['project_id']);
        $html = $this->render($project, $rows, $token);
        $this->writeImage($token, $project, $rows);
        $path = $this->pathForToken($token);
        if (!$this->ensurePublishDirectory()) {
            return;
        }

        // Atomic publish: readers never observe a half-written page.
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $html) === false || !@rename($tmp, $path)) {
            @unlink($tmp);
            error_log('labor.crew: unable to write static share page for project ' . (int) $project['project_id']);
            return;
        }
        // Apache and the CLI user must both be able to replace the file later.
        $this->shareOwnership($path, false);
    }

    /**
     * Create public/s and its .htaccess. The directory is world-readable by
     * design (that is the point of a static page); the token in the filename is
     * the secret.
     *
     * Publishing happens both from the web server (www-data) and from the CLI
     * (the deploying user), so the directory is made group-writable and, when
     * possible, assigned to the web server's group. Without that, whichever
     * side created the directory would lock the other out of updating it.
     */
    private function ensurePublishDirectory(): bool
    {
        $directory = $this->publishDirectory();
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            error_log('labor.crew: unable to create static share directory ' . $directory);
            return false;
        }
        $this->shareOwnership($directory, true);

        $htaccess = $directory . '/.htaccess';
        $expected = $this->htaccessRules();
        if (!is_file($htaccess) || @file_get_contents($htaccess) !== $expected) {
            @file_put_contents($htaccess, $expected);
            $this->shareOwnership($htaccess, false);
        }

        return true;
    }

    /**
     * Make a published path writable by both the CLI user and the web server.
     * chgrp only succeeds for a group the current user belongs to, so this is a
     * best-effort improvement, never a hard requirement; the fallback is that
     * the creating side keeps ownership and the other side reads only.
     */
    private function shareOwnership(string $path, bool $isDirectory): void
    {
        @chmod($path, $isDirectory ? 0775 : 0664);
        foreach (['www-data', 'apache', 'http', 'nginx'] as $group) {
            $info = function_exists('posix_getgrnam') ? @posix_getgrnam($group) : false;
            if (is_array($info) && isset($info['gid'])) {
                @chgrp($path, (int) $info['gid']);
                break;
            }
        }
        if ($isDirectory) {
            // setgid so files created later inherit the shared group.
            @chmod($path, 02775);
        }
    }

    /**
     * Static files carry their own security/cache policy so Apache can answer
     * without PHP. HTML revalidates every time (edits must show immediately);
     * the card image may be cached for a day.
     */
    private function htaccessRules(): string
    {
        return "# Generated by labor.crew — public share pages served directly by the web server.\n"
            . "Options -Indexes\n"
            . "<IfModule mod_headers.c>\n"
            . "  <FilesMatch \"\\.html$\">\n"
            . "    Header always set Content-Security-Policy \"" . self::CSP . "\"\n"
            . "    Header always set X-Content-Type-Options \"nosniff\"\n"
            . "    Header always set Referrer-Policy \"same-origin\"\n"
            . "    Header always set Cache-Control \"public, no-cache\"\n"
            . "  </FilesMatch>\n"
            . "  <FilesMatch \"\\.png$\">\n"
            . "    Header always set X-Content-Type-Options \"nosniff\"\n"
            . "    Header always set Cache-Control \"public, max-age=86400\"\n"
            . "  </FilesMatch>\n"
            . "</IfModule>\n"
            . "# Only the published artifacts may be requested.\n"
            . "<FilesMatch \"^(?!.*\\.(html|png)$)\">\n"
            . "  Require all denied\n"
            . "</FilesMatch>\n";
    }

    private function publishDirectory(): string
    {
        return dirname(__DIR__, 3) . '/public/s';
    }

    /**
     * Render the standalone page. If the file could not be written, serve()
     * still needs content: render on demand as a resilient fallback.
     */
    public function renderFallback(array $project, string $token = ''): string
    {
        return $this->render($project, $this->directory->exportRows((int) $project['project_id']), $token);
    }

    /**
     * Legacy thumbnail entry: redirect to the published PNG. Normal card fetches
     * hit the static file directly and never run PHP.
     */
    public function serveImage(string $token, ?string $ifNoneMatch): PluginResponse
    {
        if (preg_match('/^[0-9a-f]{32}$/', $token) !== 1) {
            throw new HttpException('分享链接无效或已停用。', 404);
        }
        $project = $this->shares->projectForToken($token);
        if ($project === null) {
            $this->removeFile($token);
            throw new HttpException('分享链接无效或已停用。', 404);
        }

        if (!is_file($this->imagePathForToken($token))) {
            $this->writeImage($token, $project, $this->directory->exportRows((int) $project['project_id']));
        }
        if (!is_file($this->imagePathForToken($token))) {
            // No GD/font on this host: there is simply no thumbnail to serve.
            throw new HttpException('分享缩略图不可用。', 404);
        }

        return PluginResponse::redirect(CrewShareService::imageUrl($token));
    }

    /** Title/description used by chat link cards and the copy-with-title action. */
    public function cardMeta(array $project, array $rows): array
    {
        $trades = [];
        foreach ($rows as $row) {
            $trades[(string) $row['type_name']] = true;
        }
        $names = array_keys($trades);
        $shown = array_slice($names, 0, 4);
        $tradeText = implode('、', $shown);
        if (count($names) > count($shown)) {
            $tradeText .= '等';
        }
        $projectName = (string) ($project['project_name'] ?? '');

        return [
            'title' => $projectName . ' · 班组联系人',
            'description' => count($rows) . ' 个在用班组 · ' . count($names) . ' 个工种'
                . ($tradeText !== '' ? '（' . $tradeText . '）' : '')
                . '，点击卡片即可直接拨号。',
            'crewCount' => count($rows),
            'tradeCount' => count($names),
        ];
    }

    private function writeImage(string $token, array $project, array $rows): void
    {
        if (!CrewShareImage::isSupported()) {
            return;
        }
        $meta = $this->cardMeta($project, $rows);
        $bytes = CrewShareImage::render($project, $meta['crewCount'], $meta['tradeCount']);
        if ($bytes === null) {
            return;
        }
        if (!$this->ensurePublishDirectory()) {
            return;
        }
        $path = $this->imagePathForToken($token);
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $bytes) === false || !@rename($tmp, $path)) {
            @unlink($tmp);
            error_log('labor.crew: unable to write share card image for project ' . (int) $project['project_id']);
            return;
        }
        $this->shareOwnership($path, false);
    }

    private function pathForToken(string $token): string
    {
        // Token format is validated before this is called: 32 hex chars only,
        // so the path can never escape the share directory.
        return $this->publishDirectory() . '/' . $token . '.html';
    }

    private function imagePathForToken(string $token): string
    {
        return $this->publishDirectory() . '/' . $token . '.png';
    }

    private function removeFile(string $token): void
    {
        if (preg_match('/^[0-9a-f]{32}$/', $token) !== 1) {
            return;
        }
        foreach ([$this->pathForToken($token), $this->imagePathForToken($token)] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /** @param array<int, array<string, string|null>> $rows */
    private function render(array $project, array $rows, string $token = ''): string
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
        $cardMeta = $this->cardMeta($project, $rows);
        $shareUrl = $token !== '' ? CrewShareService::publicUrl($token) : '';
        $imageUrl = $token !== '' && CrewShareImage::isSupported() ? CrewShareService::imageUrl($token) : '';
        $siteName = $this->platformName();

        // WeChat in-app share card, only when an operator configured a verified
        // service account. Signing needs the page's absolute URL; when it cannot
        // be produced the page simply keeps its OG metadata.
        $wechatConfig = null;
        if ($shareUrl !== '' && WechatShare::isConfigured()) {
            try {
                $wechatConfig = WechatShare::configFor($shareUrl);
            } catch (\Throwable $exception) {
                error_log('labor.crew: WeChat share config failed: ' . $exception->getMessage());
            }
        }

        // frame-ancestors is ignored in <meta>, so the document-level copy omits
        // it; the .htaccess header keeps it for servers with mod_headers.
        $contentSecurityPolicy = str_replace("; frame-ancestors 'none'", '', self::CSP);
        if ($wechatConfig !== null) {
            // The SDK is the only external script this page may ever load.
            $contentSecurityPolicy = str_replace(
                "script-src 'unsafe-inline'",
                "script-src 'unsafe-inline' https://res.wx.qq.com https://res2.wx.qq.com",
                $contentSecurityPolicy
            );
        }
        ob_start();
        require dirname(__DIR__) . '/views/share.php';

        return (string) ob_get_clean();
    }

    /** Platform name for og:site_name; falls back to the product default. */
    private function platformName(): string
    {
        $statement = $this->db->prepare("SELECT setting_value FROM System_Settings WHERE setting_key = 'platform_name'");
        $statement->execute();
        $value = $statement->fetchColumn();

        return is_string($value) && trim($value) !== '' ? trim($value) : 'Project O';
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

    /**
     * Republish every active share. Used after a template upgrade or a deploy so
     * already-shared fixed URLs pick up new markup without any data change.
     * Returns the number of pages published.
     */
    public function publishAll(): int
    {
        $count = 0;
        foreach ($this->shares->allActiveShares() as $share) {
            $token = (string) $share['token'];
            $project = $this->shares->projectForToken($token);
            if ($project === null) {
                $this->removeFile($token);
                continue;
            }
            $this->writePage($token, $project);
            $count++;
        }

        return $count;
    }

    /** Drop every published artifact (plugin disabled). */
    public function removeAll(): void
    {
        $statement = $this->db->query('SELECT token FROM Labor_Crew_Shares');
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $token) {
            if (is_string($token)) {
                $this->removeFile($token);
            }
        }
    }
}
