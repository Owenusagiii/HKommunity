<?php

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../app/controllers/AcademicYearController.php';

$controller = new AcademicYearController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = postString('action');

    if ($action === 'create') {
        $ok = $controller->create(postString('academic_year'));
        jsonResponse(['success' => $ok, 'message' => $ok ? 'Academic year created.' : 'Unable to create academic year.'], $ok ? 200 : 400);
    }

    if ($action === 'status') {
        $ok = $controller->setStatus((int) ($_POST['id'] ?? 0), postString('status'));
        jsonResponse(['success' => $ok], $ok ? 200 : 400);
    }

    jsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);
}

jsonResponse(['success' => true, 'items' => $controller->all()]);
