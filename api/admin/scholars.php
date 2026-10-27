<?php

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../app/controllers/ScholarController.php';
require_once __DIR__ . '/../../app/controllers/UserController.php';

$scholarController = new ScholarController($pdo);
$userController = new UserController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = postString('status');
    $ok = $userController->updateStatus((int) ($_POST['user_id'] ?? 0), $status);
    jsonResponse(['success' => $ok], $ok ? 200 : 400);
}

jsonResponse([
    'success' => true,
    'items' => $scholarController->search(
        $_GET['student_id'] ?? null,
        $_GET['year_level'] ?? null,
        $_GET['name'] ?? null
    )
]);
