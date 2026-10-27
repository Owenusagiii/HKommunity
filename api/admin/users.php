<?php

require_once __DIR__ . '/../../middleware/admin.php';

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/controllers/UserController.php';

header('Content-Type: application/json');

$email = trim($_GET['email'] ?? '');

$userController = new UserController($pdo);

if ($email !== '') {
    $users = $userController->searchUsers($email);
} else {
    $users = $userController->getUsers();
}

echo json_encode([
    'success' => true,
    'users' => $users
]);