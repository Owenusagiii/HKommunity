<?php

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../app/controllers/SemesterController.php';

$controller = new SemesterController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = postString('action');

    if ($action === 'create') {
        $ok = $controller->create((int) ($_POST['academic_year_id'] ?? 0), postString('semester'));
        jsonResponse(['success' => $ok], $ok ? 200 : 400);
    }

    if ($action === 'status') {
        $ok = $controller->setStatus((int) ($_POST['id'] ?? 0), postString('status'));
        jsonResponse(['success' => $ok], $ok ? 200 : 400);
    }

    jsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);
}

jsonResponse(['success' => true, 'items' => $controller->all()]);
