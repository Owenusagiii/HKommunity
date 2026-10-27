<?php

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../app/controllers/QuickLinkController.php';

$controller = new QuickLinkController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = postString('action');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'create') {
        $ok = $controller->create(
            postString('title'),
            postString('description') ?: null,
            postString('url'),
            (int) $_SESSION['user_id']
        );
        jsonResponse(['success' => $ok], $ok ? 200 : 400);
    }

    if ($action === 'update') {
        $ok = $controller->update(
            $id,
            postString('title'),
            postString('description') ?: null,
            postString('url')
        );
        jsonResponse(['success' => $ok], $ok ? 200 : 400);
    }

    if ($action === 'status') {
        $ok = $controller->setStatus($id, postString('status'));
        jsonResponse(['success' => $ok], $ok ? 200 : 400);
    }

    jsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);
}

jsonResponse(['success' => true, 'items' => $controller->allAdmin()]);
