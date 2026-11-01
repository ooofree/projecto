<?php
declare(strict_types=1);

namespace ProjectO;

use PDO;

final class AuthService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function login(string $username, string $password): bool
    {
        $stmt = $this->db->prepare(
            'SELECT user_id, username, password_hash
             FROM Users
             WHERE username = :username AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['user_id'];
        $_SESSION['username'] = (string) $user['username'];
        unset($_SESSION['csrf_token']);
        unset($_SESSION['active_project_id']);

        Support::audit($this->db, 'login', 'User signed in.');

        return true;
    }

    public function logout(): void
    {
        if (isset($_SESSION['user_id'])) {
            Support::audit($this->db, 'logout', 'User signed out.');
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
    }
}
