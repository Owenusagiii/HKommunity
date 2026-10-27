<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../app/views/components/helpers.php';
require_once __DIR__ . '/../app/controllers/NotificationController.php';

$controller = new NotificationController($pdo);
$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        jsonResponse(['success' => false, 'message' => 'Unable to verify your request. Please refresh the page and try again.'], 403);
    }

    if (postString('action') === 'mark_as_read') {
        $ok = $controller->markAsRead((int) ($_POST['notification_id'] ?? 0), $userId);
        jsonResponse([
            'success' => $ok,
            'message' => $ok ? 'Notification marked as read.' : 'Notification not found.'
        ], $ok ? 200 : 404);
    }

    if (postString('action') === 'mark_all_as_read') {
        $updatedCount = $controller->markAllAsRead($userId);
        jsonResponse([
            'success' => true,
            'updated_count' => $updatedCount,
            'message' => 'All notifications marked as read.'
        ]);
    }

    jsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);
}

jsonResponse([
    'success' => true,
    'notifications' => $controller->getNotifications($userId),
    'unread_count' => $controller->getUnreadCount($userId),
]);
