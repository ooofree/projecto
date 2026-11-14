<?php
declare(strict_types=1);

namespace ProjectO\Modules\LaborCrew;

use PDO;

/**
 * Opt-in public sharing of the crew contact directory.
 *
 * A share is an unguessable per-project token: whoever holds the link may read
 * the public contact page without signing in, and nothing else. One active
 * token per project; disabling keeps the row (revoked_at) for audit and never
 * deletes data. Re-enabling issues a fresh token so a leaked old link stays
 * dead. The public read path resolves the project from the token server-side —
 * a client-supplied project id is never trusted.
 */
final class CrewShareService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** The project's active share row, or null when sharing is off. */
    public function activeShare(int $projectId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT share_id, project_id, token, created_by, created_at, revoked_at, revoked_by
             FROM Labor_Crew_Shares
             WHERE project_id = :project_id AND revoked_at IS NULL
             ORDER BY share_id DESC
             LIMIT 1'
        );
        $statement->execute(['project_id' => $projectId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Enable sharing for the project. Repeated calls while a share is active
     * return the existing token instead of minting a second one, so a double
     * submit cannot invalidate links that were already handed out.
     */
    public function enable(int $projectId, int $userId): string
    {
        $this->db->beginTransaction();
        try {
            // Serialize concurrent enables per project on MySQL by locking the
            // parent project row; without it two simultaneous requests could
            // both pass the active check and mint two live tokens. SQLite
            // serialises writers at the database level already.
            if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $lock = $this->db->prepare('SELECT project_id FROM Projects WHERE project_id = :project_id FOR UPDATE');
                $lock->execute(['project_id' => $projectId]);
                if ($lock->fetchColumn() === false) {
                    throw new \ProjectO\HttpException('项目不存在或已不可用。', 404);
                }
            }

            $existing = $this->activeShare($projectId);
            if ($existing !== null) {
                $this->db->commit();

                return (string) $existing['token'];
            }

            // Each enable appends a new row with a fresh token; revoked rows
            // are never rewritten, so old links stay dead and the full
            // enable/revoke history remains in the table and the audit log.
            $token = bin2hex(random_bytes(16));
            $statement = $this->db->prepare(
                'INSERT INTO Labor_Crew_Shares (project_id, token, created_by)
                 VALUES (:project_id, :token, :created_by)'
            );
            $statement->execute([
                'project_id' => $projectId,
                'token' => $token,
                'created_by' => $userId,
            ]);
            $this->db->commit();

            return $token;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    /** Every active share across projects (used to refresh static pages). */
    public function allActiveShares(): array
    {
        return $this->db->query(
            'SELECT share_id, project_id, token, created_by, created_at
             FROM Labor_Crew_Shares
             WHERE revoked_at IS NULL
             ORDER BY share_id'
        )->fetchAll();
    }

    /** Disable sharing; the row is kept with revoked_at set. Idempotent. */
    public function revoke(int $projectId, int $userId): bool
    {
        $statement = $this->db->prepare(
            'UPDATE Labor_Crew_Shares
             SET revoked_at = CURRENT_TIMESTAMP, revoked_by = :revoked_by
             WHERE project_id = :project_id AND revoked_at IS NULL'
        );
        $statement->execute(['project_id' => $projectId, 'revoked_by' => $userId]);

        return $statement->rowCount() > 0;
    }

    /**
     * Public read path: resolve an active token to its project. Archived
     * projects are refused exactly like the session-based project context, so
     * archiving a project also stops its public contact page.
     */
    public function projectForToken(string $token): ?array
    {
        if (preg_match('/^[0-9a-f]{32}$/', $token) !== 1) {
            return null;
        }

        $statement = $this->db->prepare(
            'SELECT p.project_id, p.project_name, p.start_date, p.end_date, p.status
             FROM Labor_Crew_Shares s
             JOIN Projects p ON p.project_id = s.project_id
             WHERE s.token = :token AND s.revoked_at IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records a
                   WHERE a.entity_type = \'project\'
                     AND a.entity_id = p.project_id AND a.project_id = p.project_id
               )
             LIMIT 1'
        );
        $statement->execute(['token' => $token]);
        $project = $statement->fetch();

        return $project === false ? null : $project;
    }

    /**
     * The share's permanent address: a real static file the web server answers
     * directly. It never changes while the share is active, so a link handed out
     * once keeps working and keeps pointing at the same URL after every edit.
     */
    public static function publicUrl(string $token): string
    {
        return self::baseUrl() . 's/' . $token . '.html';
    }

    /**
     * Permanent address of the share card thumbnail, published next to the page.
     * Chat clients (WeChat, QQ, …) fetch og:image themselves; the file contains
     * only the project name and counts, never contact rows.
     */
    public static function imageUrl(string $token): string
    {
        return self::baseUrl() . 's/' . $token . '.png';
    }

    /** Legacy query-string entry, kept only to redirect old links. */
    public static function legacyUrl(string $token): string
    {
        return self::baseUrl() . '?page=labor-crew-share&token=' . $token;
    }

    /**
     * Absolute base URL of the application, always ending in "/".
     *
     * Under a web request it is derived from the request and remembered, so a
     * later CLI publish (installer, console) produces exactly the same links.
     * Deriving it from SCRIPT_NAME alone is wrong on the CLI — there it is the
     * script filename ("bin/install.php"), which used to yield broken hosts like
     * "http://localhostbin/s/…". An explicit APP_PUBLIC_URL override wins.
     */
    private static function baseUrl(): string
    {
        $configured = trim((string) (getenv('APP_PUBLIC_URL') ?: ''));
        if ($configured !== '' && preg_match('#^https?://[^\s]+$#i', $configured) === 1) {
            return rtrim($configured, '/') . '/';
        }

        // A real web request is always authoritative, and it refreshes the
        // remembered value so the next CLI publish uses the same absolute base.
        $fromRequest = self::baseUrlFromRequest();
        if ($fromRequest !== null) {
            if (self::recallBaseUrl() !== $fromRequest) {
                self::rememberBaseUrl($fromRequest);
            }

            return $fromRequest;
        }

        $remembered = self::recallBaseUrl();
        if ($remembered !== null) {
            return $remembered;
        }

        // Nothing known yet (fresh install, no request seen): a relative base
        // keeps links usable instead of inventing a wrong host.
        return self::appPathFromScript() . '/';
    }

    /** Base URL from a real web request, or null on the CLI. */
    private static function baseUrlFromRequest(): ?string
    {
        if (PHP_SAPI === 'cli' || !isset($_SERVER['HTTP_HOST'])) {
            return null;
        }
        $host = (string) $_SERVER['HTTP_HOST'];
        // The host header is client input: keep only characters valid in an
        // authority so it can never inject headers or markup into the link.
        if (preg_match('/^[A-Za-z0-9.\-]+(:[0-9]{1,5})?$/', $host) !== 1) {
            return null;
        }
        $https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        return ($https ? 'https://' : 'http://') . $host . self::appPathFromScript() . '/';
    }

    /** The application's directory path, e.g. "/o/public" ("" at root). */
    private static function appPathFromScript(): string
    {
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        // On the CLI, SCRIPT_NAME points at bin/install.php — not a web path.
        if (PHP_SAPI === 'cli' || $script === '' || !str_starts_with($script, '/')) {
            return '';
        }
        $base = str_replace('\\', '/', dirname($script));

        return ($base === '/' || $base === '.' || $base === '') ? '' : rtrim($base, '/');
    }

    private static function rememberBaseUrl(string $url): void
    {
        $path = self::baseUrlCachePath();
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }
        // Same shared-group treatment as the published pages: both the web
        // server and the CLI must be able to refresh this value.
        @file_put_contents($path, $url, LOCK_EX);
        @chmod($path, 0664);
        foreach (['www-data', 'apache', 'http', 'nginx'] as $group) {
            $info = function_exists('posix_getgrnam') ? @posix_getgrnam($group) : false;
            if (is_array($info) && isset($info['gid'])) {
                @chgrp($path, (int) $info['gid']);
                break;
            }
        }
    }

    private static function recallBaseUrl(): ?string
    {
        $path = self::baseUrlCachePath();
        if (!is_file($path)) {
            return null;
        }
        $value = trim((string) @file_get_contents($path));

        return preg_match('#^https?://[^\s]+/$#i', $value) === 1 ? $value : null;
    }

    private static function baseUrlCachePath(): string
    {
        // public/s is created group-writable by the publisher, so both Apache
        // and the CLI can write here (storage/ is often CLI-only). The dotfile
        // is denied over HTTP by the directory's .htaccess rules.
        return dirname(__DIR__, 3) . '/public/s/.base-url';
    }
}
