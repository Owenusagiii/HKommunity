<?php

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../middleware/coordinator.php';
require_once __DIR__ . '/../../app/controllers/ScholarController.php';
require_once __DIR__ . '/../../app/models/Coordinator.php';

$coordinator = (new Coordinator($pdo))->findByUserId((int) $_SESSION['user_id']);

if (!$coordinator) {
    jsonResponse(['success' => false, 'message' => 'Coordinator profile not found.'], 403);
}

$controller = new ScholarController($pdo);

jsonResponse([
    'success' => true,
    'items' => $controller->search(
        $_GET['student_id'] ?? null,
        $coordinator['year_level'],
        $_GET['name'] ?? null,
        $coordinator['year_level']
    )
]);
