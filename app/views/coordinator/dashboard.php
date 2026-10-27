<?php
require_once __DIR__ . '/../components/header.php';
?>
<main>
    <div class="content-shell">
        <h1>Coordinator Dashboard</h1>

        <div class="overview-card role-overview-card">
            <span class="overview-eyebrow">COORDINATOR OVERVIEW</span>
            <h2 class="overview-greet">Good day, <?= e(fullName($coordinator)) ?>!</h2>
            <p class="overview-sub">Assigned Year Level: <?= e($coordinator['year_level']) ?></p>
            <span class="overview-watermark">HK</span>
        </div>

        <div class="stat-grid">
            <div class="stat">Scholars<strong><?= e($stats['scholars']) ?></strong></div>
            <div class="stat">Pending Requirements<strong><?= e($stats['pending_requirements']) ?></strong></div>
            <div class="stat">Renewals<strong><?= e($stats['renewals']) ?></strong></div>
            <div class="stat">Unread Notifications<strong><?= e($stats['unread']) ?></strong></div>
        </div>
    </div>
</main>
<?php require __DIR__ . '/../components/footer.php'; ?>
