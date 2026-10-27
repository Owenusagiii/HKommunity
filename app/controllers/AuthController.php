<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/LoginActivity.php';

class AuthController
{
    private User $userModel;
    private LoginActivity $loginActivity;

    public function __construct(PDO $pdo)
    {
        $this->userModel = new User($pdo);
        $this->loginActivity = new LoginActivity($pdo);
    }

    public function login(string $identifier, string $password): array
    {
        $identifier = trim($identifier);

        if ($identifier === '' || $password === '') {
            return [
                'success' => false,
                'message' => 'Please enter your email or student ID number and password.'
            ];
        }

        $user = $this->userModel->findByLoginIdentifier($identifier);

        if (!$user || !password_verify($password, $user['password'])) {
            return [
                'success' => false,
                'message' => 'Invalid email, student ID number, or password.'
            ];
        }

        if ($user['status'] !== 'active') {
            return [
                'success' => false,
                'message' => 'Your account is inactive. Please contact the HK Coordinator or Admin.'
            ];
        }

        try {
            $this->loginActivity->record((int) $user['id']);
        } catch (PDOException $exception) {
            error_log('Unable to record successful login activity: ' . $exception->getMessage());
        }

        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];

        return [
            'success' => true,
            'role' => $user['role']
        ];
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}