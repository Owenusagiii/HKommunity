<?php

$role = $_SESSION['role'] ?? '';
$currentPage = $_SERVER['PHP_SELF'] ?? '';

function navActive(string $path): string
{
    global $currentPage;
    return $currentPage === $path ? 'active' : '';
}

?>
<aside id="app-sidebar" aria-label="Main navigation">
    <div class="sidebar-brand-row">
        <img class="sidebar-logo" src="<?= BASE_URL ?>public/assets/images/dashboard-logo.jpg" alt="<?= e(APP_NAME) ?>">
        <button class="sidebar-toggle" type="button" aria-controls="app-sidebar" aria-expanded="true" aria-label="Collapse navigation">
            <span aria-hidden="true"></span>
            <img class="sidebar-toggle-logo" src="<?= BASE_URL ?>public/assets/images/logo.jpg" alt="" aria-hidden="true">
        </button>
    </div>
    <nav>
        <?php if ($role === 'admin'): ?>
            <h3>Admin</h3>
            <ul>
                <li><a class="<?= navActive('/HKommunity/public/admin/dashboard.php') ?>" href="<?= BASE_URL ?>public/admin/dashboard.php">Dashboard</a></li>
                <li><a class="<?= navActive('/HKommunity/public/admin/users.php') ?>" href="<?= BASE_URL ?>public/admin/users.php">Users</a></li>
                <li><a class="<?= navActive('/HKommunity/public/admin/scholar-progress.php') ?>" href="<?= BASE_URL ?>public/admin/scholar-progress.php">Management</a></li>
                <li><a class="<?= navActive('/HKommunity/public/admin/registrations.php') ?>" href="<?= BASE_URL ?>public/admin/registrations.php">Registration</a></li>
                <li><a class="<?= navActive('/HKommunity/public/admin/registration-import.php') ?>" href="<?= BASE_URL ?>public/admin/registration-import.php">Registration Import</a></li>
                <li><a class="<?= navActive('/HKommunity/public/admin/academic-years.php') ?>" href="<?= BASE_URL ?>public/admin/academic-years.php">Academic Years</a></li>
                <li><a class="<?= navActive('/HKommunity/public/admin/renewal-periods.php') ?>" href="<?= BASE_URL ?>public/admin/renewal-periods.php">Renewal Periods</a></li>
                <li><a class="<?= navActive('/HKommunity/public/admin/requirements.php') ?>" href="<?= BASE_URL ?>public/admin/requirements.php">Requirements</a></li>
                <li><a class="<?= navActive('/HKommunity/public/admin/announcements.php') ?>" href="<?= BASE_URL ?>public/admin/announcements.php">Announcements</a></li>
                <li><a class="<?= navActive('/HKommunity/public/admin/quick-links.php') ?>" href="<?= BASE_URL ?>public/admin/quick-links.php">Quick Links</a></li>
            </ul>
        <?php elseif ($role === 'coordinator'): ?>
            <h3>Coordinator</h3>
            <ul>
                <li><a class="<?= navActive('/HKommunity/public/coordinator/dashboard.php') ?>" href="<?= BASE_URL ?>public/coordinator/dashboard.php">Dashboard</a></li>
                <li><a class="<?= navActive('/HKommunity/public/coordinator/scholars.php') ?>" href="<?= BASE_URL ?>public/coordinator/scholars.php">Scholars</a></li>
                <li><a class="<?= navActive('/HKommunity/public/coordinator/requirements.php') ?>" href="<?= BASE_URL ?>public/coordinator/requirements.php">Requirements</a></li>
                <li><a class="<?= navActive('/HKommunity/public/coordinator/duty-records.php') ?>" href="<?= BASE_URL ?>public/coordinator/duty-records.php">Duty Reviews &amp; Records</a></li>
                <li><a class="<?= navActive('/HKommunity/public/coordinator/renewals.php') ?>" href="<?= BASE_URL ?>public/coordinator/renewals.php">Renewals</a></li>
                <li><a class="<?= navActive('/HKommunity/public/coordinator/announcements.php') ?>" href="<?= BASE_URL ?>public/coordinator/announcements.php">Announcements</a></li>
            </ul>
        <?php elseif ($role === 'scholar'): ?>
            <h3>Scholar</h3>
            <ul>
                <li><a class="<?= navActive('/HKommunity/public/scholar/dashboard.php') ?>" href="<?= BASE_URL ?>public/scholar/dashboard.php">Dashboard</a></li>
                <li><a class="<?= navActive('/HKommunity/public/scholar/requirements.php') ?>" href="<?= BASE_URL ?>public/scholar/requirements.php">My Requirements</a></li>
                <li><a class="<?= navActive('/HKommunity/public/scholar/duty-records.php') ?>" href="<?= BASE_URL ?>public/scholar/duty-records.php" data-icon="&#xf017;">My Duty Hours</a></li>
                <li><a class="<?= navActive('/HKommunity/public/scholar/renewal.php') ?>" href="<?= BASE_URL ?>public/scholar/renewal.php">My Renewal</a></li>
                <li><a class="<?= navActive('/HKommunity/public/scholar/announcements.php') ?>" href="<?= BASE_URL ?>public/scholar/announcements.php">Announcements</a></li>
                <li><a class="<?= navActive('/HKommunity/public/scholar/quick-links.php') ?>" href="<?= BASE_URL ?>public/scholar/quick-links.php">Quick Links</a></li>
            </ul>
        <?php endif; ?>

        <a class="sidebar-signout" href="<?= BASE_URL ?>public/logout.php" data-confirm-logout>Log out</a>

    </nav>
</aside>
<div class="sidebar-overlay" data-sidebar-overlay hidden></div>
