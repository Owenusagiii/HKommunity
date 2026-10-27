<?php
require_once __DIR__ . '/../components/header.php';
?>
<main>
    <div class="content-shell">
        <h1>Admin Dashboard</h1>

        <div class="overview-card role-overview-card">
            <span class="overview-eyebrow">ADMINISTRATION OVERVIEW</span>
            <h2 class="overview-greet">Good day, Admin!</h2>
            <p class="overview-sub">Monitor users, scholars, registrations, and system activity.</p>
            <span class="overview-watermark">HK</span>
        </div>

    <div class="stat-grid">
        <div class="stat">Users<strong><?= e($stats['users']) ?></strong></div>
        <div class="stat">Scholars<strong><?= e($stats['scholars']) ?></strong></div>
        <div class="stat">Coordinators<strong><?= e($stats['coordinators']) ?></strong></div>
        <div class="stat">Pending Registrations<strong><?= e($stats['pending_registrations']) ?></strong></div>
        <div class="stat">Unread Notifications<strong><?= e($stats['unread']) ?></strong></div>
    </div>

    <h2>Recent Activity</h2>
    <?php if (!$recentNotifications): ?>
        <p>No recent notifications.</p>
    <?php else: ?>
        <?php foreach ($recentNotifications as $notification): ?>
            <article class="card">
                <strong><?= e($notification['title']) ?></strong>
                <p><?= e($notification['message']) ?></p>
                <small class="muted"><?= e($notification['created_at']) ?></small>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>
</main>
<?php require __DIR__ . '/../components/footer.php'; ?>
