<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';

$authController = new AuthController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $identifier = is_string($_POST['identifier'] ?? null) ? $_POST['identifier'] : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    $result = $authController->login($identifier, $password);

    if ($result['success']) {
        unset($_SESSION['login_identifier']);

        switch ($result['role']) {

            case 'admin':
                header('Location: ' . BASE_URL . 'admin/dashboard');
                exit;

            case 'coordinator':
                header('Location: ' . BASE_URL . 'coordinator/dashboard');
                exit;

            case 'scholar':
                header('Location: ' . BASE_URL . 'scholar/dashboard');
                exit;

            default:
                $_SESSION['login_error'] = 'Invalid account role.';
                header('Location: ' . BASE_URL . 'login');
                exit;
        }

    }

    $_SESSION['login_identifier'] = $identifier;
    $_SESSION['login_error'] = $result['message'];

    header('Location: ' . BASE_URL . 'login');
    exit;
}

require_once __DIR__ . '/../app/views/auth/login.php';