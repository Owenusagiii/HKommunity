<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$authController = new AuthController($pdo);
$authController->logout();

header('Location: ' . BASE_URL . 'public/login.php');
exit;
