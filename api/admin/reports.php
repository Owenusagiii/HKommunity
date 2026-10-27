<?php

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../app/controllers/ReportController.php';
require_once __DIR__ . '/../../app/models/Notification.php';

$controller = new ReportController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $status = postString('status');
    $response = postString('response');

    $ok = $controller->updateStatus($id, $status, $response !== '' ? $response : null);

    if ($ok) {
        $stmt = $pdo->prepare('SELECT submitted_by, subject FROM reports WHERE id = :id');
        $stmt->execute(['id' => $id]);
        if ($report = $stmt->fetch()) {
            (new Notification($pdo))->createNotification(
                (int) $report['submitted_by'],
                'Report Updated',
                'Your report "' . $report['subject'] . '" was updated by Admin.',
                'report'
            );
        }
    }

    jsonResponse(['success' => $ok], $ok ? 200 : 400);
}

jsonResponse(['success' => true, 'items' => $controller->all()]);
