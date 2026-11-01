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
            StorageFactory::fromEnvironment()
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

        if ($action === 'logout') {
            $this->auth->logout();
            $this->redirect('?page=login');
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

        Support::requireLogin();
        if ($page === 'admin') {
            $this->render(
                dirname(__DIR__) . '/views/admin.php',
                $this->platformAdministration->pageData(),
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

        if ($response->type !== 'view' || $response->viewPath === null) {
            throw new HttpException('插件返回了无效的响应。', 500);
        }

        $this->render($response->viewPath, $response->data, true);
    }

    private function render(string $view, array $viewData = [], bool $isAbsolutePath = false): void
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        $escape = [Support::class, 'escape'];
        $csrfToken = Support::csrfToken();
        $username = (string) ($_SESSION['username'] ?? '');
        $canManagePlatform = isset($_SESSION['user_id'])
            && Support::hasPermission($this->db, 'manage_platform');
        $theme = $this->platformAdministration->theme();
        extract($viewData, EXTR_SKIP);
        $viewPath = $isAbsolutePath ? $view : dirname(__DIR__) . '/views/' . $view . '.php';
        if (!is_file($viewPath)) {
            throw new HttpException('页面模板不存在。', 500);
        }
        require $viewPath;
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location, true, 303);
        exit;
    }
}
