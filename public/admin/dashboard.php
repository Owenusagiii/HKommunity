<?php

require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/models/LoginActivity.php';
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../../app/views/components/header.php';

$stats = [];
$stats['users'] = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$stats['scholars'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'scholar' AND status = 'active'")->fetchColumn();
$stats['coordinators'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'coordinator' AND status = 'active'")->fetchColumn();
$stats['pending_registrations'] = (int) $pdo->query("SELECT COUNT(*) FROM scholar_registrations WHERE status = 'pending'")->fetchColumn();
$stats['reports'] = (int) $pdo->query('SELECT COUNT(*) FROM reports')->fetchColumn();
$recentLoginActivities = (new LoginActivity($pdo))->recent();

$activeDashboardSemesterStmt = $pdo->query(
    "SELECT sm.id AS semester_id, sm.semester, ay.academic_year
     FROM semesters sm
     JOIN academic_years ay ON ay.id = sm.academic_year_id
     WHERE sm.status = 'active' AND ay.status = 'active'
     ORDER BY ay.academic_year DESC,
              FIELD(sm.semester, '1st Semester', '2nd Semester') DESC
     LIMIT 1"
);
$activeDashboardSemester = $activeDashboardSemesterStmt->fetch(PDO::FETCH_ASSOC) ?: null;
$dashboardProgressStmt = $pdo->prepare(
    "SELECT
        COALESCE(SUM(
            CASE
                WHEN COALESCE(req.assigned_count, 0) > 0
                 AND req.approved_count >= req.assigned_count
                THEN 1
                ELSE 0
            END
        ), 0) AS completed_requirements,
        COALESCE(SUM(
            CASE
                WHEN rp.id IS NOT NULL
                 AND COALESCE(hours.rendered_hours, 0) >= rp.required_duty_hours
                THEN 1
                ELSE 0
            END
        ), 0) AS completed_duty_hours,
        COALESCE(SUM(
            CASE
                WHEN rp.id IS NOT NULL
                 AND COALESCE(req.assigned_count, 0) > 0
                 AND req.approved_count >= req.assigned_count
                 AND COALESCE(hours.rendered_hours, 0) >= rp.required_duty_hours
                THEN 1
                ELSE 0
            END
        ), 0) AS completed_both
     FROM scholars sc
     JOIN users u ON u.id = sc.user_id
     LEFT JOIN renewal_periods rp
        ON rp.year_level = sc.year_level
       AND rp.semester_id = :semester_id
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
     WHERE u.role = 'scholar' AND u.status = 'active'"
);
$dashboardProgressStmt->execute([
    'semester_id' => (int) ($activeDashboardSemester['semester_id'] ?? 0),
]);
$dashboardProgress = $dashboardProgressStmt->fetch(PDO::FETCH_ASSOC);
$dashboardProgressMetrics = [
    [
        'label' => 'Requirements Complete',
        'value' => (int) ($dashboardProgress['completed_requirements'] ?? 0),
        'description' => 'Scholars with all assigned requirements approved',
    ],
    [
        'label' => 'Duty Hours Complete',
        'value' => (int) ($dashboardProgress['completed_duty_hours'] ?? 0),
        'description' => 'Scholars who rendered their required duty hours',
    ],
    [
        'label' => 'Fully Complete',
        'value' => (int) ($dashboardProgress['completed_both'] ?? 0),
        'description' => 'Scholars who completed both requirements and duty hours',
    ],
];
foreach ($dashboardProgressMetrics as &$metric) {
    $metric['percentage'] = $stats['scholars'] > 0
        ? (int) round(($metric['value'] / $stats['scholars']) * 100)
        : 0;
}
unset($metric);

$yearLevelColors = [
    '1st Year' => '#4CAF50',
    '2nd Year' => '#F44336',
    '3rd Year' => '#2196F3',
    '4th Year' => '#FFC107',
    '5th Year' => '#9C27B0',
];
$dashboardYearLevelProgress = array_fill_keys(
    array_keys($yearLevelColors),
    ['scholars' => 0, 'complete' => 0]
);
$dashboardYearLevelProgressStmt = $pdo->prepare(
    "SELECT sc.year_level,
            COUNT(*) AS scholar_count,
            SUM(
                CASE
                    WHEN rp.id IS NOT NULL
                     AND COALESCE(req.assigned_count, 0) > 0
                     AND req.approved_count >= req.assigned_count
                     AND COALESCE(hours.rendered_hours, 0) >= rp.required_duty_hours
                    THEN 1
                    ELSE 0
                END
            ) AS completed_count
     FROM scholars sc
     JOIN users u ON u.id = sc.user_id
     LEFT JOIN renewal_periods rp
        ON rp.year_level = sc.year_level
       AND rp.semester_id = :semester_id
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
     WHERE u.role = 'scholar' AND u.status = 'active'
     GROUP BY sc.year_level"
);
$dashboardYearLevelProgressStmt->execute([
    'semester_id' => (int) ($activeDashboardSemester['semester_id'] ?? 0),
]);
foreach ($dashboardYearLevelProgressStmt->fetchAll(PDO::FETCH_ASSOC) as $yearLevelProgress) {
    $yearLevel = (string) $yearLevelProgress['year_level'];
    if (isset($dashboardYearLevelProgress[$yearLevel])) {
        $dashboardYearLevelProgress[$yearLevel] = [
            'scholars' => (int) $yearLevelProgress['scholar_count'],
            'complete' => (int) $yearLevelProgress['completed_count'],
        ];
    }
}
$activeScholarsByYearLevel = array_fill_keys(array_keys($yearLevelColors), 0);
$activeScholarsByYearLevelStmt = $pdo->query(
    "SELECT sc.year_level, COUNT(*) AS scholar_count
     FROM scholars sc
     JOIN users u ON u.id = sc.user_id
     WHERE u.role = 'scholar' AND u.status = 'active'
     GROUP BY sc.year_level"
);
foreach ($activeScholarsByYearLevelStmt->fetchAll(PDO::FETCH_ASSOC) as $yearLevelCount) {
    $yearLevel = (string) $yearLevelCount['year_level'];
    if (isset($activeScholarsByYearLevel[$yearLevel])) {
        $activeScholarsByYearLevel[$yearLevel] = (int) $yearLevelCount['scholar_count'];
    }
}
$activeScholarTotal = array_sum($activeScholarsByYearLevel);
$distributionStops = [];
$distributionDegrees = 0.0;
foreach ($yearLevelColors as $yearLevel => $yearColor) {
    $count = $activeScholarsByYearLevel[$yearLevel];
    if ($count === 0 || $activeScholarTotal === 0) {
        continue;
    }

    $startDegrees = $distributionDegrees;
    $distributionDegrees += ($count / $activeScholarTotal) * 360;
    $distributionStops[] = $yearColor
        . ' ' . number_format($startDegrees, 3, '.', '') . 'deg '
        . number_format($distributionDegrees, 3, '.', '') . 'deg';
}
$distributionBackground = $distributionStops
    ? 'conic-gradient(' . implode(', ', $distributionStops) . ')'
    : '#e5e7eb';
$distributionDescription = implode(', ', array_map(
    static fn (string $yearLevel): string => $yearLevel . ': ' . $activeScholarsByYearLevel[$yearLevel],
    array_keys($yearLevelColors)
));
$largestYearLevelCount = max($activeScholarsByYearLevel);
$largestYearLevels = $largestYearLevelCount > 0
    ? array_keys(array_filter(
        $activeScholarsByYearLevel,
        static fn (int $count): bool => $count === $largestYearLevelCount
    ))
    : [];
$representedYearLevelCount = count(array_filter(
    $activeScholarsByYearLevel,
    static fn (int $count): bool => $count > 0
));
$largestYearLevelShare = $activeScholarTotal > 0
    ? (int) round(($largestYearLevelCount / $activeScholarTotal) * 100)
    : 0;

?>
<main>
    <div class="content-shell admin-dashboard-shell">
        <h1 class="visually-hidden">Admin Dashboard</h1>

        <div class="overview-card">
            <span class="overview-eyebrow">ADMINISTRATION OVERVIEW</span>
            <h2 class="overview-greet">Good day, Admin!</h2>
            <p class="overview-sub">Manage accounts and registrations, track scholar progress, and review reports and activity.</p>
            <span class="overview-watermark">HK</span>
        </div>

        <div class="admin-dashboard-content">
            <div class="stats admin-dashboard-stats">
                <a class="stat stat-link" href="<?= BASE_URL ?>public/admin/users.php">
                    <div class="stat-head"><i class="fa-solid fa-users stat-icon" aria-hidden="true"></i> Users</div>
                    <div class="number"><?= e($stats['users']) ?></div>
                    <div class="admin-user-breakdown">
                        <span><i class="fa-solid fa-user-tie" aria-hidden="true"></i> Coordinators: <strong><?= e($stats['coordinators']) ?></strong></span>
                        <span><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> Scholars: <strong><?= e($stats['scholars']) ?></strong></span>
                    </div>
                </a>
                <a class="stat stat-link" href="<?= BASE_URL ?>public/admin/registrations.php">
                    <div class="stat-head"><i class="fa-solid fa-clipboard-list stat-icon" aria-hidden="true"></i> Pending Registrations</div>
                    <div class="number"><?= e($stats['pending_registrations']) ?></div>
                    <div class="stat-note"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i> Awaiting review</div>
                </a>
                <a class="stat stat-link" href="<?= BASE_URL ?>public/admin/reports.php">
                    <div class="stat-head"><i class="fa-solid fa-flag stat-icon" aria-hidden="true"></i> Escalated Reports</div>
                    <div class="number"><?= e($stats['reports']) ?></div>
                    <div class="stat-note"><i class="fa-solid fa-flag" aria-hidden="true"></i> Needs follow-up</div>
                </a>
            </div>

            <section class="admin-year-progress-card" aria-labelledby="admin-active-scholars-title">
                <h2 class="admin-dashboard-progress-title" id="admin-active-scholars-title">Active Scholars</h2>
                <div class="admin-scholar-distribution">
                    <div
                        class="admin-scholar-distribution-chart"
                        role="img"
                        aria-label="<?= e($activeScholarTotal . ' active scholars. ' . $distributionDescription) ?>"
                        style="--scholar-distribution: <?= e($distributionBackground) ?>;"
                    >
                        <div class="admin-scholar-distribution-total">
                            <strong><?= e($activeScholarTotal) ?></strong>
                            <span>Total</span>
                        </div>
                    </div>
                    <ul class="admin-scholar-distribution-legend" aria-label="Active scholars by year level">
                        <?php foreach ($yearLevelColors as $yearLevel => $yearColor): ?>
                            <li>
                                <span class="admin-scholar-distribution-swatch" style="--year-level-color: <?= e($yearColor) ?>;" aria-hidden="true"></span>
                                <span><?= e($yearLevel) ?></span>
                                <span class="admin-scholar-distribution-separator" aria-hidden="true">:</span>
                                <strong><?= e($activeScholarsByYearLevel[$yearLevel]) ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="admin-scholar-distribution-summary" aria-label="Scholar distribution summary">
                        <div class="admin-scholar-distribution-summary-item">
                            <span>Largest group</span>
                            <strong><?= e($largestYearLevels ? implode(' / ', $largestYearLevels) : '—') ?></strong>
                            <small>
                                <?= e($largestYearLevelCount) ?> scholar<?= $largestYearLevelCount === 1 ? '' : 's' ?>
                                · <?= e($largestYearLevelShare) ?>%
                            </small>
                        </div>
                        <div class="admin-scholar-distribution-summary-item">
                            <span>Year levels active</span>
                            <strong><?= e($representedYearLevelCount) ?> <small>of <?= e(count($yearLevelColors)) ?></small></strong>
                        </div>
                    </div>
                </div>
                <h2 class="admin-dashboard-progress-title">Overall</h2>
                <div class="admin-year-progress-metrics" aria-label="Overall active scholar progress">
                    <?php foreach ($dashboardProgressMetrics as $metric): ?>
                        <div class="admin-year-progress-metric">
                            <div class="admin-year-progress-metric-head">
                                <div>
                                    <h3><?= e($metric['label']) ?></h3>
                                    <p><?= e($metric['description']) ?></p>
                                </div>
                                <strong>
                                    <?= e($metric['value']) ?>
                                    /
                                    <?= e($stats['scholars']) ?>
                                    <span class="admin-year-progress-percentage" data-summary-progress-percent><?= e($metric['percentage']) ?>%</span>
                                </strong>
                            </div>
                            <div
                                class="admin-year-progress-bar"
                                role="progressbar"
                                aria-label="<?= e($metric['label']) ?>"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                aria-valuenow="<?= e($metric['percentage']) ?>"
                            >
                                <span style="width: <?= e($metric['percentage']) ?>%"></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <div class="admin-dashboard-bottom">
            <section class="records-card admin-activity-card" aria-labelledby="admin-activity-title">
            <div class="records-head">
                <div>
                    <h2 class="admin-users-section-title" id="admin-activity-title">
                        <span class="admin-users-section-icon"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span>
                        User Logs
                    </h2>
                    <p class="sub-small">Successful user sign-ins</p>
                </div>
                <div class="coordinator-scholars-tools admin-activity-tools">
                    <span class="record-count" id="login-activity-result-count" aria-live="polite"><?= e(min(count($recentLoginActivities), 8)) ?> logins</span>
                    <label class="visually-hidden" for="login-activity-period">Filter login activity by time</label>
                    <select id="login-activity-period" class="coordinator-promotion-filter" aria-controls="admin-login-activity-table">
                        <option value="recent" selected>Recent</option>
                        <option value="last_7_days">Last 7 days</option>
                        <option value="last_week">Last week</option>
                        <option value="last_month">Last month</option>
                    </select>
                </div>
            </div>

            <div class="table-scroll admin-activity-table-scroll">
                <table class="data-table admin-activity-table" id="admin-login-activity-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Login Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentLoginActivities as $activity): ?>
                            <tr data-admin-login-activity-row data-login-timestamp="<?= e(strtotime($activity['logged_in_at'])) ?>">
                                <td>
                                    <span class="admin-activity-user">
                                        <?= e($activity['user_email']) ?>
                                    </span>
                                </td>
                                <td><?= e(ucfirst($activity['role'])) ?></td>
                                <td><?= e((new DateTime($activity['logged_in_at']))->format('M j, Y \a\t g:i A')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$recentLoginActivities): ?>
                            <tr><td colspan="3">No login activity recorded yet.</td></tr>
                        <?php else: ?>
                            <tr class="admin-login-activity-filter-empty" hidden>
                                <td colspan="3">No login activity matches this time period.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            </section>
            <section class="records-card admin-year-completion-card" aria-labelledby="admin-year-completion-title">
                <div class="records-head">
                    <div>
                        <h2 class="admin-users-section-title" id="admin-year-completion-title">
                            <span class="admin-users-section-icon"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i></span>
                            Year-Level Completion
                        </h2>
                        <p class="sub-small"><?= e($activeDashboardSemester
                            ? $activeDashboardSemester['academic_year'] . ' · ' . $activeDashboardSemester['semester']
                            : 'No active semester') ?></p>
                    </div>
                </div>
                <div class="admin-year-completion-list" aria-label="Fully complete scholars by year level">
                    <?php foreach ($yearLevelColors as $yearLevel => $yearColor): ?>
                        <?php
                        $scholarCount = $dashboardYearLevelProgress[$yearLevel]['scholars'];
                        $completedCount = $dashboardYearLevelProgress[$yearLevel]['complete'];
                        $completionPercentage = $scholarCount > 0
                            ? (int) round(($completedCount / $scholarCount) * 100)
                            : 0;
                        ?>
                        <div class="admin-year-completion-item">
                            <div class="admin-year-completion-label">
                                <h3><?= e($yearLevel) ?></h3>
                                <p><?= e($completedCount) ?> / <?= e($scholarCount) ?> complete</p>
                            </div>
                            <div
                                class="admin-year-completion-bar"
                                role="progressbar"
                                aria-label="<?= e($yearLevel . ' completion') ?>"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                aria-valuenow="<?= e($completionPercentage) ?>"
                            >
                                <span style="width: <?= e($completionPercentage) ?>%; --year-level-color: <?= e($yearColor) ?>;"></span>
                            </div>
                            <strong><?= e($completionPercentage) ?>%</strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            </div>

        </div>

    </div>
</main>
<?php
$pageScripts = ['public/assets/js/admin-login-activity-filter.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-login-activity-filter.js')];
require __DIR__ . '/../../app/views/components/footer.php';
?>
