<?php require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../middleware/coordinator.php';
require_once __DIR__ . '/../../app/controllers/ReportController.php';
require_once __DIR__ . '/../../app/models/Coordinator.php';
require_once __DIR__ . '/../../app/models/Notification.php';
$uid = (int) $_SESSION['user_id'];
$cs = (new Coordinator($pdo))->getAllCoordinators();
$me = null;
foreach ($cs as $c) {
    if ((int) $c['user_id'] === $uid) {
        $me = $c;
        break;
    }
}
if (!$me)
    jsonResponse(['success' => false], 403);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin = $pdo->query("SELECT id FROM users WHERE role='admin' AND status='active' LIMIT 1")->fetchColumn();
    if (!$admin)
        jsonResponse(['success' => false, 'message' => 'No active Admin found.'], 400);
    $ok = (new ReportController($pdo))->create($uid, (int) $admin, postString('subject'), postString('message'));
    if ($ok)
        (new Notification($pdo))->createNotification((int) $admin, 'New Coordinator Report', 'A coordinator submitted a report/request for Admin review.', 'report');
    jsonResponse(['success' => $ok]);
}
jsonResponse(['success' => true, 'items' => (new ReportController($pdo))->forUser($uid)]);
