<?php
declare(strict_types=1);

namespace ProjectO;

use PDO;
use ProjectO\Plugin\PluginRegistry;
use ProjectO\Plugin\PluginResponse;
use ProjectO\Storage\StorageFactory;
use Throwable;

final class Application
{
    private AuthService $auth;
    private PluginRegistry $plugins;
    private PlatformAdministrationService $platformAdministration;

    public function __construct(private readonly PDO $db)
    {
        $this->auth = new AuthService($db);
        $this->plugins = new PluginRegistry(
            $db,
            dirname(__DIR__) . '/modules',
            StorageFactory::fromEnvironment($this->db)
        );
        $this->platformAdministration = new PlatformAdministrationService($db, $this->plugins);
    }

    public function run(): void
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->handlePost();
                return;
            }

            $this->handleGet();
        } catch (HttpException $exception) {
            http_response_code($exception->statusCode);
            $this->render('error', ['message' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            error_log((string) $exception);
            http_response_code(500);
            $this->render('error', [
                'message' => '系统发生错误，请检查应用日志。',
            ]);
        }
    }

    private function handlePost(): void
    {
        if (!Support::verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            throw new HttpException('表单已过期，请刷新页面后重试。', 419);
        }

        $action = $_POST['action'] ?? '';
        if (!is_string($action)) {
            throw new HttpException('操作参数无效。', 422);
        }
        if ($action === 'login') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $username = is_string($username) ? trim($username) : '';
            $password = is_string($password) ? $password : '';
            if (!$this->auth->login($username, $password)) {
                Support::flash('用户名或密码错误。', 'error');
                $this->redirect('?page=login');
            }

            $this->redirect('?page=projects');
        }

        Support::requireLogin();
        Support::setDisplayTimezone(Support::resolveTimezone($this->db));

        if ($action === 'logout') {
            $this->auth->logout();
            $this->redirect('?page=login');
        }

        if ($action === 'profile.save') {
            $this->saveProfile();
            return;
        }

        if ($action === 'profile.password') {
            $this->changePassword();
            return;
        }

        if (str_starts_with($action, 'admin.')) {
            $this->respond($this->platformAdministration->dispatch($action, $_POST));
        }

        $input = $_POST;
        if ($_FILES !== []) {
            $input['_files'] = $_FILES;
        }
        $response = $this->plugins->dispatch('POST', $action, $input);
        if ($response === null) {
            throw new HttpException('未找到对应操作。', 404);
        }
        $this->respond($response);
    }

    private function handleGet(): void
    {
        $page = $_GET['page'] ?? 'projects';
        if (!is_string($page)) {
            throw new HttpException('页面参数无效。', 422);
        }
        if ($page === 'login') {
            if (isset($_SESSION['user_id'])) {
                $this->redirect('?page=projects');
            }
            $this->render('login', ['title' => 'Sign in']);
            return;
        }

        // Public share pages are the only GET routes that skip the login gate,
        // and only when a plugin manifest explicitly declares them public. The
        // plugin handler still validates its own share token and limits every
        // read to the project the token was issued for.
        if ($this->plugins->isPublicRoute('GET', $page)) {
            Support::setDisplayTimezone(Support::resolveTimezone($this->db));
            $response = $this->plugins->dispatch('GET', $page, $_GET);
            if ($response === null) {
                throw new HttpException('页面不存在。', 404);
            }
            $this->respond($response);
            return;
        }

        Support::requireLogin();
        Support::setDisplayTimezone(Support::resolveTimezone($this->db));

        if ($page === 'profile') {
            $this->render(dirname(__DIR__) . '/views/profile.php', $this->profileData(), true);
            return;
        }

        if ($page === 'admin') {
            $section = $_GET['section'] ?? 'settings';
            if (!is_string($section)) {
                throw new HttpException('设置分区参数无效。', 422);
            }
            $this->render(
                dirname(__DIR__) . '/views/admin.php',
                $this->platformAdministration->pageData($section),
                true
            );
            return;
        }

        $response = $this->plugins->dispatch('GET', $page, $_GET);
        if ($response === null) {
            throw new HttpException('页面不存在。', 404);
        }

        $this->respond($response);
    }

    private function respond(PluginResponse $response): void
    {
        if ($response->type === 'redirect') {
            if (
                $response->location === null
                || !str_starts_with($response->location, '?')
                || preg_match('/[\r\n]/', $response->location)
            ) {
                throw new HttpException('插件返回了无效的跳转地址。', 500);
            }
            $this->redirect($response->location);
        }

        if ($response->type === 'download') {
            $this->sendDownload($response->data);
            return;
        }

        if ($response->type === 'inline') {
            $this->sendInline($response->data);
            return;
        }

        if ($response->type === 'raw') {
            $this->sendRaw($response->data);
            return;
        }

        if ($response->type === 'not_modified') {
            $this->sendNotModified($response->data['headers'] ?? []);
            return;
        }

        if ($response->type !== 'view' || $response->viewPath === null) {
            throw new HttpException('插件返回了无效的响应。', 500);
        }

        $this->render($response->viewPath, $response->data, true);
    }

    /** Stream a pre-rendered body (static share page) without layout wrapping. */
    private function sendRaw(array $data): void
    {
        $body = $data['body'] ?? null;
        $contentType = $data['content_type'] ?? null;
        if (!is_string($body) || !is_string($contentType) || preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+#i', $contentType) !== 1) {
            throw new HttpException('插件返回了无效的静态内容。', 500);
        }
        // The static page is a trusted pre-rendered document with inline
        // CSS/JS and zero external loads. Replace the app-wide CSP (which
        // forbids inline code) with one scoped to exactly that: inline code
        // allowed, every network source denied. header() replaces the earlier
        // same-name policy sent by the entry script.
        if (str_starts_with(strtolower($contentType), 'text/html')) {
            header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; img-src data:; form-action 'none'; base-uri 'none'; frame-ancestors 'none'");
        }
        foreach (($data['headers'] ?? []) as $name => $value) {
            if (is_string($name) && preg_match('/^[A-Za-z0-9-]+$/', $name) === 1 && is_string($value) && preg_match('/[\r\n]/', $value) !== 1) {
                header($name . ': ' . $value);
            }
        }
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . strlen($body));
        echo $body;
    }

    private function sendNotModified(array $headers): void
    {
        http_response_code(304);
        foreach ($headers as $name => $value) {
            if (is_string($name) && preg_match('/^[A-Za-z0-9-]+$/', $name) === 1 && is_string($value) && preg_match('/[\r\n]/', $value) !== 1) {
                header($name . ': ' . $value);
            }
        }
    }

    private function render(string $view, array $viewData = [], bool $isAbsolutePath = false): void
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        $escape = [Support::class, 'escape'];
        // The login form needs a CSRF token before any session user exists.
        // Only the anonymous public share view skips it: minting a token there
        // would hand every visitor a session credential and start a session
        // per crawler hit, and that view renders no forms at all.
        $isPublicShareView = $viewData['publicShareView'] ?? false;
        $csrfToken = $isPublicShareView === true ? '' : Support::csrfToken();
        $username = (string) ($_SESSION['username'] ?? '');
        $canManagePlatform = isset($_SESSION['user_id'])
            && Support::hasPermission($this->db, 'manage_platform');
        $appearance = $this->platformAdministration->appearance();
        $theme = $appearance['theme'];
        $platformName = $appearance['platform_name'];
        $brandMark = self::brandMark($platformName);
        extract($viewData, EXTR_SKIP);
        $viewPath = $isAbsolutePath ? $view : dirname(__DIR__) . '/views/' . $view . '.php';
        if (!is_file($viewPath)) {
            throw new HttpException('页面模板不存在。', 500);
        }
        require $viewPath;
    }

    private function sendDownload(array $data): void
    {
        $filename = $data['filename'] ?? null;
        $contentType = $data['content_type'] ?? null;
        $body = $data['body'] ?? null;
        if (
            !is_string($filename)
            || $filename === ''
            || mb_strlen($filename, 'UTF-8') > 150
            || preg_match('/["\\;\x00-\x1F\x7F]/', $filename) === 1
        ) {
            throw new HttpException('插件返回了无效的下载文件名。', 500);
        }
        if (!is_string($contentType) || preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', $contentType) !== 1) {
            throw new HttpException('插件返回了无效的下载类型。', 500);
        }
        if (!is_string($body)) {
            throw new HttpException('插件返回了无效的下载内容。', 500);
        }

        header('Content-Type: ' . $contentType);
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $filename) === 1) {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        } else {
            $fallback = preg_match('/\.[A-Za-z0-9]{1,10}$/', $filename) === 1
                ? 'download.' . substr($filename, (int) strrpos($filename, '.') + 1)
                : 'download';
            header("Content-Disposition: attachment; filename=\"{$fallback}\"; filename*=UTF-8''" . rawurlencode($filename));
        }
        header('Content-Length: ' . strlen($body));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        echo $body;
    }

    private function sendInline(array $data): void
    {
        $filename = $data['filename'] ?? null;
        $contentType = $data['content_type'] ?? null;
        $body = $data['body'] ?? null;
        if (
            !is_string($filename)
            || $filename === ''
            || mb_strlen($filename, 'UTF-8') > 150
            || preg_match('/["\\;\x00-\x1F\x7F]/', $filename) === 1
        ) {
            throw new HttpException('插件返回了无效的图片文件名。', 500);
        }
        if (!is_string($contentType) || preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', $contentType) !== 1) {
            throw new HttpException('插件返回了无效的图片类型。', 500);
        }
        if (!is_string($body)) {
            throw new HttpException('插件返回了无效的图片内容。', 500);
        }

        header('Content-Type: ' . $contentType);
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $filename) === 1) {
            header('Content-Disposition: inline; filename="' . $filename . '"');
        } else {
            $fallback = preg_match('/\.[A-Za-z0-9]{1,10}$/', $filename) === 1
                ? 'image.' . substr($filename, (int) strrpos($filename, '.') + 1)
                : 'image';
            header("Content-Disposition: inline; filename=\"{$fallback}\"; filename*=UTF-8''" . rawurlencode($filename));
        }
        header('Content-Length: ' . strlen($body));
        header('X-Content-Type-Options: nosniff');
        // Inline images are immutable per object key (the key embeds 16 random
        // bytes), so browsers may retain them for a week instead of refetching
        // a multi-second remote read every five minutes.
        header('Cache-Control: private, max-age=604800, immutable');
        echo $body;
    }

    private function saveProfile(): void
    {
        $timezone = $_POST['timezone'] ?? '';
        if (!is_string($timezone)) {
            throw new HttpException('时区选项无效。', 422);
        }
        (new ProfileService($this->db))->saveTimezone((int) ($_SESSION['user_id'] ?? 0), $timezone);
        Support::flash('个人时区设置已保存。');
        $this->redirect('?page=profile');
    }

    private function changePassword(): void
    {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmation = $_POST['new_password_confirmation'] ?? '';
        if (!is_string($currentPassword) || !is_string($newPassword) || !is_string($confirmation)) {
            throw new HttpException('密码字段格式无效。', 422);
        }

        (new ProfileService($this->db))->changePassword(
            (int) ($_SESSION['user_id'] ?? 0),
            $currentPassword,
            $newPassword,
            $confirmation
        );
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        Support::flash('密码已更新，下次登录请使用新密码。');
        $this->redirect('?page=profile');
    }

    private function profileData(): array
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $statement = $this->db->prepare('SELECT username, timezone FROM Users WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
        $user = $statement->fetch() ?: ['username' => '', 'timezone' => null];

        $defaultTimezone = 'Asia/Shanghai';
        $setting = $this->db->prepare("SELECT setting_value FROM System_Settings WHERE setting_key = 'timezone'");
        $setting->execute();
        $stored = $setting->fetchColumn();
        if (is_string($stored) && Support::isValidTimezone($stored)) {
            $defaultTimezone = $stored;
        }

        return [
            'title' => '个人设置',
            'currentUsername' => (string) ($_SESSION['username'] ?? ($user['username'] ?? '')),
            'userTimezone' => (string) ($user['timezone'] ?? ''),
            'defaultTimezone' => $defaultTimezone,
            'effectiveTimezone' => Support::displayTimezone()->getName(),
            'timezones' => Support::timezoneOptions(),
            'currentLocalTime' => Support::nowLocal('Y-m-d H:i:s'),
        ];
    }

    private static function brandMark(string $platformName): string
    {
        $mark = mb_substr(trim($platformName), 0, 1, 'UTF-8');
        if ($mark === '') {
            return 'O';
        }

        return mb_strtoupper($mark, 'UTF-8');
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location, true, 303);
        exit;
    }
}
