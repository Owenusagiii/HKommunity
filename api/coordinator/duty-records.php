<?php

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../middleware/coordinator.php';
require_once __DIR__ . '/../../app/controllers/DutyController.php';
require_once __DIR__ . '/../../app/models/Coordinator.php';

$coordinator = (new Coordinator($pdo))->findByUserId((int) $_SESSION['user_id']);

if (!$coordinator) {
    jsonResponse(['success' => false, 'message' => 'Coordinator profile not found.'], 403);
}

$controller = new DutyController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = postString('action');

    if ($action === 'update') {
        $ok = $controller->update(
            (int) ($_POST['id'] ?? 0),
            postString('duty_date'),
            postString('duty_type'),
            (float) ($_POST['hours'] ?? 0),
            (int) $coordinator['user_id']
        );
        jsonResponse(['success' => $ok], $ok ? 200 : 400);
    }

    jsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);
}

jsonResponse([
    'success' => true,
    'items' => $controller->all(null, $_GET['student_id'] ?? null, (int) $coordinator['user_id'])
]);
