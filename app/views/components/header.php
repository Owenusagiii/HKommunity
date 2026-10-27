<?php

require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Notification.php';
require_once __DIR__ . '/../../models/Scholar.php';
require_once __DIR__ . '/../../models/ScholarPeriodContext.php';
require_once __DIR__ . '/helpers.php';

$pageTitle = $pageTitle ?? APP_NAME;
$pageScripts = $pageScripts ?? [];
$pageStyles = array_merge(['public/assets/css/users.css'], $pageStyles ?? []);
$sharedStyles = [
    'foundation.css',
    'dashboards.css',
    'duties.css',
    'coordinator.css',
    'admin.css',
    'scholar-records.css',
    'reports.css',
    'shared-pages.css',
    'components.css',
    'responsive.css',
    'scholar-pages.css',
    'admin-progress.css',
    'responsive-overrides.css',
    'descriptions.css',
];
$currentRole = $_SESSION['role'] ?? '';
$pageClass = trim(
    'app-page '
    . ($currentRole === 'admin' ? 'admin-portal ' : '')
    . ($currentRole === 'coordinator' ? 'coordinator-portal ' : '')
    . ($currentRole === 'scholar' ? 'scholar-portal ' : '')
    . ($pageClass ?? '')
);
$currentUser = null;
$userFirstName = 'Account';
$userInitials = 'A';
$headerNotifications = [];
$headerUnreadNotifications = 0;
$headerActivePeriod = null;
$headerScholarPeriodContext = null;
$reportsPath = match ($currentRole) {
    'admin' => 'public/admin/reports.php',
    'coordinator' => 'public/coordinator/reports.php',
    'scholar' => 'public/scholar/reports.php',
    default => 'public/profile.php'
};

if (isset($pdo, $_SESSION['user_id'])) {
    $userModel = new User($pdo);
    $currentUser = $userModel->findById((int) $_SESSION['user_id']);
    $userFirstName = $userModel->getFirstNameById((int) $_SESSION['user_id']) ?? 'Account';
    $userInitials = strtoupper(
        substr($userFirstName, 0, 1)
        . substr((string) ($currentUser['last_name'] ?? ''), 0, 1)
    ) ?: 'A';
    $notificationModel = new Notification($pdo);
    $headerNotifications = array_slice($notificationModel->getNotifications((int) $_SESSION['user_id']), 0, 4);
    $headerUnreadNotifications = $notificationModel->getUnreadCount((int) $_SESSION['user_id']);
    if ($currentRole === 'scholar') {
        $headerScholar = isset($scholar) && is_array($scholar)
            ? $scholar
            : (new Scholar($pdo))->findByUserId((int) $_SESSION['user_id']);
        if ($headerScholar) {
            $headerScholarPeriodContext = isset($scholarPeriodContext) && is_array($scholarPeriodContext)
                ? $scholarPeriodContext
                : ScholarPeriodContext::resolve($pdo, $headerScholar);
            $headerActivePeriod = $headerScholarPeriodContext['selected'];
        }
    } else {
        $activePeriodStmt = $pdo->query(
            "SELECT ay.academic_year, sm.semester
             FROM semesters sm
             JOIN academic_years ay ON ay.id = sm.academic_year_id
             WHERE sm.status = 'active' AND ay.status = 'active'
             ORDER BY ay.academic_year DESC,
                      FIELD(sm.semester, '1st Semester', '2nd Semester') DESC
             LIMIT 1"
        );
        $headerActivePeriod = $activePeriodStmt->fetch() ?: null;
    }
}

$notificationsPath = $currentRole === 'admin'
    ? 'public/admin/notifications.php'
    : ($currentRole === 'coordinator' ? 'public/coordinator/announcements.php' : 'public/scholar/announcements.php');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/jpeg" href="<?= BASE_URL ?>public/assets/images/logo.jpg">
    <link rel="stylesheet" href="<?= BASE_URL ?>public/assets/fontawesome/css/all.min.css?v=<?= filemtime(__DIR__ . '/../../../public/assets/fontawesome/css/all.min.css') ?>">
    <?php foreach ($sharedStyles as $style): ?>
        <link rel="stylesheet" href="<?= BASE_URL ?>public/assets/css/<?= e($style) ?>?v=<?= filemtime(__DIR__ . '/../../../public/assets/css/' . $style) ?>">
    <?php endforeach; ?>
    <?php foreach ($pageStyles as $style): ?>
        <link rel="stylesheet" href="<?= BASE_URL . ltrim($style, '/') ?>">
    <?php endforeach; ?>
    <script src="<?= BASE_URL ?>public/assets/js/sidebar.js" defer></script>
    <script src="<?= BASE_URL ?>public/assets/js/header-menu.js" defer></script>
    <script src="<?= BASE_URL ?>public/assets/js/logout-confirmation.js" defer></script>
    <script src="<?= BASE_URL ?>public/assets/js/flash-messages.js" defer></script>
    <script src="<?= BASE_URL ?>public/assets/js/password-toggle.js" defer></script>
</head>
<body class="<?= e($pageClass) ?>">
<script>
    if (
        window.matchMedia('(max-width: 800px)').matches
        || localStorage.getItem('HKommunity-sidebar-collapsed') === 'true'
    ) {
        document.body.classList.add('sidebar-collapsed');
    }
</script>
<header class="app-header">
    <button class="sidebar-reopen" type="button" aria-controls="app-sidebar" aria-expanded="false" aria-label="Expand navigation">
        <span aria-hidden="true">&#8250;</span>
        <img class="sidebar-reopen-logo" src="<?= BASE_URL ?>public/assets/images/logo.jpg" alt="" aria-hidden="true">
    </button>
    <span class="header-page-title" aria-current="page"><?= e($pageTitle) ?></span>
    <div class="header-actions">
        <?php if ($currentRole === 'scholar' && $headerScholarPeriodContext): ?>
            <?php
            $periodRequestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
            $periodRequestPath = is_string($periodRequestPath) ? $periodRequestPath : BASE_URL . 'public/scholar/dashboard.php';
            $periodQuery = $_GET;
            unset($periodQuery['view_period_id']);
            $periodQuery['view_period_id'] = 'current';
            $currentPeriodUrl = $periodRequestPath . '?' . http_build_query($periodQuery);
            ?>
            <div class="header-menu period-menu">
                <button class="header-period-badge<?= $headerScholarPeriodContext['can_transact'] ? '' : ' is-read-only' ?>" type="button" aria-expanded="false" aria-controls="period-options" aria-label="Choose academic period">
                    <span class="header-period-icon"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span>
                    <span class="header-period-copy">
                        <small><?= $headerScholarPeriodContext['can_transact'] ? 'ACTIVE PERIOD' : 'VIEWING HISTORY · READ ONLY' ?></small>
                        <b><?= $headerActivePeriod
                            ? e($headerActivePeriod['academic_year'] . ' · ' . $headerActivePeriod['semester'])
                            : 'No period selected' ?></b>
                    </span>
                    <i class="fa-solid fa-chevron-down header-period-chevron" aria-hidden="true"></i>
                </button>
                <div class="header-menu-options period-options" id="period-options" hidden>
                    <?php if ($headerScholarPeriodContext['current']): ?>
                        <a class="period-option<?= $headerScholarPeriodContext['can_transact'] ? ' is-selected' : '' ?>" href="<?= e($currentPeriodUrl) ?>">
                            <span><b><?= e($headerScholarPeriodContext['current']['academic_year'] . ' · ' . $headerScholarPeriodContext['current']['semester']) ?></b><small>Current active period</small></span>
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        </a>
                    <?php endif; ?>
                    <?php foreach ($headerScholarPeriodContext['periods'] as $period): ?>
                        <?php if ($headerScholarPeriodContext['current'] && (int) $period['id'] === (int) $headerScholarPeriodContext['current']['id']) { continue; } ?>
                        <?php
                        $periodQuery['view_period_id'] = (string) $period['id'];
                        $periodUrl = $periodRequestPath . '?' . http_build_query($periodQuery);
                        $isSelectedPeriod = $headerActivePeriod && (int) $headerActivePeriod['id'] === (int) $period['id'];
                        ?>
                        <a class="period-option<?= $isSelectedPeriod ? ' is-selected' : '' ?>" href="<?= e($periodUrl) ?>">
                            <span><b><?= e($period['academic_year'] . ' · ' . $period['semester']) ?></b><small><?= e($period['year_level']) ?> · View only</small></span>
                            <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        </a>
                    <?php endforeach; ?>
                    <?php if (!$headerScholarPeriodContext['current'] && !$headerScholarPeriodContext['periods']): ?>
                        <p class="period-options-empty">No academic period history is available.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <button class="header-period-badge header-period-display" type="button" aria-expanded="false" aria-label="<?= $headerActivePeriod
                ? e('Active period: ' . $headerActivePeriod['academic_year'] . ' ' . $headerActivePeriod['semester'])
                : 'No active academic period' ?>">
                <span class="header-period-icon"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span>
                <span class="header-period-copy">
                    <small>ACTIVE PERIOD</small>
                    <b><?= $headerActivePeriod
                        ? e($headerActivePeriod['academic_year'] . ' · ' . $headerActivePeriod['semester'])
                        : 'Not set' ?></b>
                </span>
            </button>
        <?php endif; ?>

        <div class="header-menu user-menu">
            <button class="user-menu-toggle" type="button" aria-expanded="false" aria-controls="user-menu-options">
                <span class="header-avatar"><?= e($userInitials) ?></span>
                <span class="header-user-copy"><b><?= e($userFirstName) ?></b><small><?= e(ucfirst($currentRole)) ?></small></span>
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="header-menu-options user-menu-options" id="user-menu-options" hidden>
                <div class="profile-menu-head">
                    <span class="header-avatar large"><?= e($userInitials) ?></span>
                    <span class="profile-menu-copy"><b><?= e($userFirstName) ?></b><small><?= e(ucfirst($currentRole)) ?> account</small></span>
                </div>
                <div class="profile-menu-label">PERSONAL</div>
                <a href="<?= BASE_URL ?>public/profile.php"><i class="fa-solid fa-user" aria-hidden="true"></i> Profile</a>
                <a href="<?= BASE_URL . $reportsPath ?>"><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Reports</a>
                <a href="<?= BASE_URL ?>public/logout.php" class="danger" data-confirm-logout><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Sign Out</a>
            </div>
        </div>

        <div class="header-menu notification-menu">
            <button class="notification-toggle" type="button" aria-expanded="false" aria-controls="notification-options" aria-label="Notifications">
                <i class="fa-solid fa-bell" aria-hidden="true"></i>
                <?php if ($headerUnreadNotifications > 0): ?><span class="notification-badge"><?= e($headerUnreadNotifications) ?></span><?php endif; ?>
            </button>
            <div class="header-menu-options notification-options" id="notification-options" hidden>
                <div class="notification-menu-head">
                    <strong>Notifications</strong>
                    <a href="<?= BASE_URL . $notificationsPath ?>">View all</a>
                </div>
                <?php foreach ($headerNotifications as $notification): ?>
                    <a class="notification-preview <?= (int) $notification['is_read'] === 0 ? 'unread' : '' ?>" href="<?= BASE_URL . $notificationsPath ?>">
                        <span class="notification-preview-icon"><i class="fa-solid fa-bell" aria-hidden="true"></i></span>
                        <span><b><?= e($notification['title']) ?></b><small><?= e($notification['message']) ?></small></span>
                    </a>
                <?php endforeach; ?>
                <?php if (!$headerNotifications): ?><p class="notification-empty">No notifications yet.</p><?php endif; ?>
            </div>
        </div>
    </div>
</header>
<dialog class="logout-confirm-dialog" id="logout-confirm-dialog" aria-labelledby="logout-confirm-title" aria-describedby="logout-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i></div>
    <h2 id="logout-confirm-title">Sign out?</h2>
    <p id="logout-confirm-description">Are you sure you want to log out of your account?</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-logout-cancel>Cancel</button>
        <a href="<?= BASE_URL ?>public/logout.php" class="logout-confirm-submit">Log Out</a>
    </div>
</dialog>
<?php require_once __DIR__ . '/sidebar.php'; ?>
