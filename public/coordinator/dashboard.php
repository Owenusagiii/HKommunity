<?php

require_once __DIR__ . '/../../middleware/coordinator.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/models/Coordinator.php';
require_once __DIR__ . '/../../app/models/Announcement.php';
require_once __DIR__ . '/../../app/models/Notification.php';
require_once __DIR__ . '/../../app/models/RenewalPeriod.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

$coordinator = (new Coordinator($pdo))->findByUserId((int) $_SESSION['user_id']);

if (!$coordinator) {
    http_response_code(403);
    exit('Coordinator profile not found.');
}

$yearLevel = $coordinator['year_level'];
$activePeriod = (new RenewalPeriod($pdo))->findActiveForYearLevel($yearLevel);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM scholars WHERE year_level = :year_level");
$stmt->execute(['year_level' => $yearLevel]);
$scholars = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM scholar_requirements sr
     JOIN scholars sc ON sc.id = sr.scholar_id
     WHERE sc.year_level = :year_level
       AND sr.status = 'submitted'"
);
$stmt->execute(['year_level' => $yearLevel]);
$pendingRequirements = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM duty_submissions ds
     JOIN scholars sc ON sc.id = ds.scholar_id
     WHERE sc.year_level = :year_level
       AND ds.status = 'pending'"
);
$stmt->execute(['year_level' => $yearLevel]);
$pendingDutyReviews = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM renewals rn
     JOIN scholars sc ON sc.id = rn.scholar_id
     WHERE sc.year_level = :year_level"
);
$stmt->execute(['year_level' => $yearLevel]);
$renewals = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM reports
     WHERE submitted_to = :user_id
       AND status = 'for_review'"
);
$stmt->execute(['user_id' => (int) $_SESSION['user_id']]);
$reportsAwaitingReview = (int) $stmt->fetchColumn();

$progressScholarCount = 0;
$progressMetrics = [];
if ($activePeriod) {
    $progressStmt = $pdo->prepare(
        "SELECT COUNT(*) AS scholar_count,
                COALESCE(SUM(
                    CASE WHEN COALESCE(req.assigned_count, 0) > 0
                              AND COALESCE(req.approved_count, 0) >= req.assigned_count
                         THEN 1 ELSE 0 END
                ), 0) AS requirements_complete,
                COALESCE(SUM(
                    CASE WHEN COALESCE(hours.rendered_hours, 0) >= rp.required_duty_hours
                         THEN 1 ELSE 0 END
                ), 0) AS duty_complete,
                COALESCE(SUM(
                    CASE WHEN COALESCE(req.assigned_count, 0) > 0
                              AND COALESCE(req.approved_count, 0) >= req.assigned_count
                              AND COALESCE(hours.rendered_hours, 0) >= rp.required_duty_hours
                         THEN 1 ELSE 0 END
                ), 0) AS fully_complete
         FROM scholars sc
         JOIN users u ON u.id = sc.user_id
         JOIN renewal_periods rp ON rp.id = :period_id
         LEFT JOIN (
             SELECT scholar_id, renewal_period_id, COUNT(*) AS assigned_count,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_count
             FROM scholar_requirements
             GROUP BY scholar_id, renewal_period_id
         ) req ON req.scholar_id = sc.id AND req.renewal_period_id = rp.id
         LEFT JOIN (
             SELECT scholar_id, renewal_period_id, SUM(hours) AS rendered_hours
             FROM duty_records
             GROUP BY scholar_id, renewal_period_id
         ) hours ON hours.scholar_id = sc.id AND hours.renewal_period_id = rp.id
         WHERE sc.year_level = :year_level
           AND u.role = 'scholar'
           AND u.status = 'active'"
    );
    $progressStmt->execute([
        'period_id' => (int) $activePeriod['id'],
        'year_level' => $yearLevel,
    ]);
    $progress = $progressStmt->fetch(PDO::FETCH_ASSOC);
    $progressScholarCount = (int) $progress['scholar_count'];
    $progressMetrics = [
        [
            'label' => 'Requirements Complete',
            'value' => (int) $progress['requirements_complete'],
        ],
        [
            'label' => 'Duty Hours Complete',
            'value' => (int) $progress['duty_complete'],
        ],
        [
            'label' => 'Fully Complete',
            'value' => (int) $progress['fully_complete'],
        ],
    ];
    foreach ($progressMetrics as &$progressMetric) {
        $progressMetric['percentage'] = $progressScholarCount > 0
            ? (int) round(($progressMetric['value'] / $progressScholarCount) * 100)
            : 0;
    }
    unset($progressMetric);
}

$announcements = (new Announcement($pdo))->allForRole('coordinator');
$recentNotifications = (new Notification($pdo))->getRecentForUser((int) $_SESSION['user_id']);

$pageTitle = 'Dashboard';
require __DIR__ . '/../../app/views/components/header.php';
?>
<main class="coordinator-dashboard-page">
    <div class="content-shell coordinator-dashboard-shell">
        <h1 class="visually-hidden">Coordinator Dashboard</h1>

        <div class="overview-card role-overview-card">
            <span class="overview-eyebrow">COORDINATOR OVERVIEW</span>
            <h2 class="overview-greet">Good day, <?= e(fullName($coordinator)) ?>!</h2>
            <p class="overview-sub">
                <i class="fa-solid fa-graduation-cap" aria-hidden="true"></i>
                <span>Assigned year level</span>
                <strong><?= e($coordinator['year_level']) ?></strong>
            </p>
            <span class="overview-watermark">HK</span>
        </div>

        <div class="stat-grid admin-dashboard-stats">
            <a class="stat stat-link year-card" href="<?= BASE_URL ?>public/coordinator/scholars.php">
                <div class="stat-head"><i class="fa-solid fa-graduation-cap stat-icon" aria-hidden="true"></i> Scholars</div>
                <div class="number"><?= e($scholars) ?></div>
                <div class="stat-note">In your assigned cohort</div>
            </a>

            <a class="stat stat-link requirements-card" href="<?= BASE_URL ?>public/coordinator/requirements.php">
                <div class="stat-head"><i class="fa-solid fa-tasks stat-icon" aria-hidden="true"></i> Pending Requirements</div>
                <div class="number"><?= e($pendingRequirements) ?></div>
                <div class="stat-note">Waiting for your review</div>
            </a>

            <a class="stat stat-link coordinator-dashboard-duty-card" href="<?= BASE_URL ?>public/coordinator/duty-records.php">
                <div class="stat-head"><i class="fa-solid fa-clipboard-check stat-icon" aria-hidden="true"></i> Duty Reviews</div>
                <div class="number"><?= e($pendingDutyReviews) ?></div>
                <div class="stat-note">Awaiting your review</div>
            </a>

            <a class="stat stat-link reports-card" href="<?= BASE_URL ?>public/coordinator/reports.php">
                <div class="stat-head"><i class="fa-solid fa-file-lines stat-icon" aria-hidden="true"></i> Reports to Review</div>
                <div class="number"><?= e($reportsAwaitingReview) ?></div>
                <div class="stat-note">Needs your attention</div>
            </a>

        </div>

        <section class="records-card coordinator-dashboard-progress-card" aria-labelledby="coordinator-dashboard-progress-title">
            <div class="records-head">
                <div>
                    <h2 id="coordinator-dashboard-progress-title">Year-Level Progress</h2>
                    <p class="sub-small">
                        <?= e($yearLevel) ?> Scholars
                        <?php if ($activePeriod): ?>
                            · <?= e($activePeriod['academic_year'] . ' · ' . $activePeriod['semester']) ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php if ($activePeriod): ?>
                <div class="admin-year-progress-metrics" aria-label="Scholar progress for <?= e($yearLevel) ?>">
                    <?php foreach ($progressMetrics as $progressMetric): ?>
                        <div class="admin-year-progress-metric">
                            <div class="admin-year-progress-metric-head">
                                <div>
                                    <h3><?= e($progressMetric['label']) ?></h3>
                                </div>
                                <strong>
                                    <?= e($progressMetric['value']) ?> / <?= e($progressScholarCount) ?>
                                    <span class="admin-year-progress-percentage"><?= e($progressMetric['percentage']) ?>%</span>
                                </strong>
                            </div>
                            <div
                                class="admin-year-progress-bar"
                                role="progressbar"
                                aria-label="<?= e($progressMetric['label']) ?>"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                aria-valuenow="<?= e($progressMetric['percentage']) ?>"
                            >
                                <span style="width: <?= e($progressMetric['percentage']) ?>%"></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="coordinator-scholar-progress-empty">
                    Progress metrics are unavailable because there is no active renewal period for this year level.
                </p>
            <?php endif; ?>
        </section>

        <div class="lower coordinator-dashboard-lower">
            <div class="panel panel-link" role="link" tabindex="0" data-panel-url="<?= BASE_URL ?>public/coordinator/announcements.php">
                <div class="panel-title">
                    <span>Recent Announcements</span>
                    <a href="<?= BASE_URL ?>public/coordinator/announcements.php" class="view-all">View all →</a>
                </div>

                <div class="dashboard-scroll-list">
                    <?php foreach (array_slice($announcements, 0, 2) as $announcement): ?>
                        <div class="announcement dashboard-announcement-row">
                            <div class="bullet"><i class="fa-solid fa-bullhorn" aria-hidden="true"></i></div>
                            <div class="announcement-info">
                                <b><?= e($announcement['title']) ?></b>
                            </div>
                            <div class="date"><?= e(date('M d', strtotime($announcement['created_at']))) ?></div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($announcements)): ?>
                        <div class="announcement">
                            <div class="bullet"><i class="fa-solid fa-bullhorn" aria-hidden="true"></i></div>
                            <div class="announcement-info">
                                <b>No announcements yet</b>
                                <small>Check back later</small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel panel-link coordinator-dashboard-notifications" role="link" tabindex="0" data-panel-url="<?= BASE_URL ?>public/coordinator/announcements.php">
                <div class="panel-title">
                    <span>Recent Notifications</span>
                    <a href="<?= BASE_URL ?>public/coordinator/announcements.php" class="view-all">View all →</a>
                </div>

                <div class="dashboard-scroll-list">
                    <?php foreach (array_slice($recentNotifications, 0, 2) as $notification): ?>
                        <?php $notificationDate = strtotime((string) $notification['created_at']); ?>
                        <div class="announcement dashboard-notification-row<?= (int) $notification['is_read'] === 0 ? ' is-unread' : '' ?>">
                            <div class="bullet"><i class="fa-solid fa-bell" aria-hidden="true"></i></div>
                            <div class="announcement-info">
                                <b><?= e($notification['title']) ?></b>
                            </div>
                            <div class="date"><?= e($notificationDate ? date('M d', $notificationDate) : '') ?></div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($recentNotifications)): ?>
                        <div class="announcement">
                            <div class="bullet"><i class="fa-solid fa-bell" aria-hidden="true"></i></div>
                            <div class="announcement-info">
                                <b>No notifications yet</b>
                                <small>New activity will appear here</small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
<?php
$pageScripts = ['public/assets/js/dashboard-panels.js'];
require __DIR__ . '/../../app/views/components/footer.php';
?>
