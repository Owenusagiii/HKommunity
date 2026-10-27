<?php

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../middleware/coordinator.php';
require_once __DIR__ . '/../../app/controllers/ScholarRequirementController.php';
require_once __DIR__ . '/../../app/models/Coordinator.php';
require_once __DIR__ . '/../../app/models/Notification.php';

$coordinator = (new Coordinator($pdo))->findByUserId((int) $_SESSION['user_id']);

if (!$coordinator) {
    jsonResponse(['success' => false, 'message' => 'Coordinator profile not found.'], 403);
}

$controller = new ScholarRequirementController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = postString('action');

    if ($action === 'review') {
        $id = (int) ($_POST['id'] ?? 0);
        $status = postString('status');

        $stmt = $pdo->prepare(
            'SELECT sc.year_level, sc.user_id, r.name
             FROM scholar_requirements sr
             JOIN scholars sc ON sc.id = sr.scholar_id
             JOIN requirements r ON r.id = sr.requirement_id
             WHERE sr.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $target = $stmt->fetch();

        if (!$target || $target['year_level'] !== $coordinator['year_level']) {
            jsonResponse(['success' => false, 'message' => 'Scholar is outside your Assigned Year Level.'], 403);
        }

        $reason = postString('reason');
        $ok = in_array($status, ['approved', 'rejected'], true)
            && $controller->review($id, $status, $reason, (int) $coordinator['user_id']);

        if ($ok) {
            $message = 'Your requirement "' . $target['name'] . '" was ' . $status . '.';
            if ($status === 'rejected') {
                $message .= ' Reason: ' . $reason;
            }
            (new Notification($pdo))->createNotification(
                (int) $target['user_id'],
                'Requirement ' . ucfirst($status),
                $message,
                'requirement'
            );
        }

        jsonResponse(['success' => $ok], $ok ? 200 : 400);
    }

    if ($action === 'assign') {
        $scholarId = (int) ($_POST['scholar_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT year_level, user_id FROM scholars WHERE id = :id');
        $stmt->execute(['id' => $scholarId]);
        $scholar = $stmt->fetch();

        if (!$scholar || $scholar['year_level'] !== $coordinator['year_level']) {
            jsonResponse(['success' => false, 'message' => 'Scholar is outside your Assigned Year Level.'], 403);
        }

        $ok = $controller->assign(
            $scholarId,
            (int) ($_POST['requirement_id'] ?? 0),
            (int) ($_POST['renewal_period_id'] ?? 0),
            (int) $coordinator['user_id']
        );
        jsonResponse(['success' => $ok], $ok ? 200 : 400);
    }

    jsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);
}

jsonResponse([
    'success' => true,
    'items' => $controller->allForCoordinator($coordinator['year_level'])
]);
