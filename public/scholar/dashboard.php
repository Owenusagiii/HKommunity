<?php
require_once __DIR__ . '/../../middleware/scholar.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/models/Scholar.php';
require_once __DIR__ . '/../../app/models/Notification.php';
require_once __DIR__ . '/../../app/models/Announcement.php';
require_once __DIR__ . '/../../app/models/QuickLink.php';
require_once __DIR__ . '/../../app/models/DutyRecord.php';
require_once __DIR__ . '/../../app/models/ScholarRequirement.php';
require_once __DIR__ . '/../../app/models/ScholarPeriodContext.php';

$scholar = (new Scholar($pdo))->findByUserId((int) $_SESSION['user_id']);
if (!$scholar) {
    http_response_code(403);
    exit('Scholar profile not found.');
}

$scholarPeriodContext = ScholarPeriodContext::resolve($pdo, $scholar);
$progressPeriod = $scholarPeriodContext['selected'];
$periodId = (int) ($progressPeriod['id'] ?? 0);
$stats = [];
$q = $pdo->prepare(
    'SELECT COUNT(*) FROM scholar_requirements
     WHERE scholar_id = :s AND renewal_period_id = :period_id'
);
$q->execute(['s' => $scholar['id'], 'period_id' => $periodId]);
$stats['requirements'] = (int) $q->fetchColumn();

$q = $pdo->prepare(
    "SELECT COUNT(*) FROM scholar_requirements
     WHERE scholar_id = :s AND renewal_period_id = :period_id AND status = 'approved'"
);
$q->execute(['s' => $scholar['id'], 'period_id' => $periodId]);
$stats['approved_requirements'] = (int) $q->fetchColumn();

$q = $pdo->prepare(
    'SELECT COALESCE(SUM(hours), 0) FROM duty_records
     WHERE scholar_id = :s AND renewal_period_id = :period_id'
);
$q->execute(['s' => $scholar['id'], 'period_id' => $periodId]);
$stats['duty_hours'] = (float) $q->fetchColumn();

$stats['required_duty_hours'] = (float) ($progressPeriod['required_duty_hours'] ?? 0);
$stats['duty_progress'] = $stats['required_duty_hours'] > 0
    ? min(100, (int) round(($stats['duty_hours'] / $stats['required_duty_hours']) * 100))
    : ($progressPeriod !== null ? 100 : 0);

$periodRequirements = $progressPeriod
    ? array_values(array_filter(
        (new ScholarRequirement($pdo))->allForScholar((int) $scholar['id']),
        static fn ($row) => (int) ($row['renewal_period_id'] ?? 0) === (int) $progressPeriod['id']
    ))
    : [];

$stats['total_requirements'] = count($periodRequirements);
$stats['approved_requirements_for_period'] = count(array_filter(
    $periodRequirements,
    static fn ($row) => ($row['status'] ?? '') === 'approved'
));
$stats['requirement_progress'] = $stats['total_requirements'] > 0
    ? min(100, (int) round(($stats['approved_requirements_for_period'] / $stats['total_requirements']) * 100))
    : 0;

$renewalStatus = $pdo->prepare(
    'SELECT status FROM renewals
     WHERE scholar_id = :s AND renewal_period_id = :period_id
     LIMIT 1'
);
$renewalStatus->execute(['s' => $scholar['id'], 'period_id' => $periodId]);
$renewalStatusValue = (string) ($renewalStatus->fetchColumn() ?: 'not_started');
$renewalMap = [
    'not_started' => ['label' => 'Awaiting Coordinator', 'percent' => 0],
    'in_progress' => ['label' => 'In Progress', 'percent' => 35],
    'for_review' => ['label' => 'For Review', 'percent' => 70],
    'approved' => ['label' => 'Approved', 'percent' => 100],
    'rejected' => ['label' => 'Rejected', 'percent' => 30],
    'completed' => ['label' => 'Completed', 'percent' => 100],
];
$stats['renewal_status'] = $renewalMap[$renewalStatusValue] ?? $renewalMap['not_started'];

$stats['unread'] = (new Notification($pdo))->getUnreadCount((int) $_SESSION['user_id']);
$announcements = (new Announcement($pdo))->allForRole('scholar');
$quickLinks = (new QuickLink($pdo))->latestActive();

$pageTitle = 'Dashboard';
$pageScripts = ['public/assets/js/dashboard-panels.js'];
require __DIR__ . '/../../app/views/scholar/dashboard.php';
