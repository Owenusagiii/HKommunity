<?php
require_once __DIR__ . '/../components/header.php';
?>
<main>
    <div class="content-shell scholar-dashboard-shell">
        <h1 class="visually-hidden">Dashboard</h1>
        <?php if ($progressPeriod && !$scholarPeriodContext['can_transact']): ?>
            <div class="scholar-period-read-only" role="status">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                Viewing <?= e($progressPeriod['academic_year'] . ' · ' . $progressPeriod['semester']) ?> history. Transactions in this period are locked.
            </div>
        <?php endif; ?>

        <div class="overview-card">
            <span class="overview-eyebrow">SCHOLARSHIP OVERVIEW</span>
            <h2 class="overview-greet">Good day, <?= e(fullName($scholar)) ?>!</h2>
            <span class="overview-watermark">HK</span>
        </div>

        <div class="stats scholar-dashboard-stats">
            <a class="stat stat-link year-card" href="<?= BASE_URL ?>public/profile.php">
                <div class="stat-head"><i class="fa-solid fa-graduation-cap stat-icon" aria-hidden="true"></i> Year Level</div>
                <div class="number"><?= e($scholar['year_level']) ?></div>
                <div class="stat-note">Current active record</div>
            </a>

            <a class="stat stat-link duty-card" href="<?= BASE_URL ?>public/scholar/duty-records.php">
                <div class="stat-head"><i class="fa-solid fa-clock stat-icon" aria-hidden="true"></i> Duty Hours</div>
                <div class="number"><?= e(number_format((float) $stats['duty_hours'], 2)) ?><?php if ((float) $stats['required_duty_hours'] > 0): ?> / <?= e(number_format((float) $stats['required_duty_hours'], 2)) ?><?php endif; ?></div>
                <div class="bar"><span style="width: <?= (int) $stats['duty_progress'] ?>%;"></span></div>
                <div class="stat-note"><?= e((float) $stats['required_duty_hours'] > 0
                    ? $stats['duty_progress'] . '% completed'
                    : ($progressPeriod ? 'Complete · no duty hours required' : 'No active duty target')) ?></div>
            </a>

            <a class="stat stat-link requirements-card" href="<?= BASE_URL ?>public/scholar/requirements.php">
                <div class="stat-head"><i class="fa-solid fa-tasks stat-icon" aria-hidden="true"></i> Requirements</div>
                <div class="number"><?= e($stats['approved_requirements_for_period'] ?? 0) ?> / <?= e($stats['total_requirements'] ?? 0) ?></div>
                <div class="bar"><span style="width: <?= (int) ($stats['requirement_progress'] ?? 0) ?>%;"></span></div>
                <div class="stat-note"><?= e(($stats['total_requirements'] ?? 0) > 0 ? ($stats['requirement_progress'] ?? 0) . '% complete' : 'No active requirements') ?></div>
            </a>

            <a class="stat stat-link renewal-card" href="<?= BASE_URL ?>public/scholar/renewal.php">
                <div class="stat-head"><i class="fa-solid fa-check-circle stat-icon" aria-hidden="true"></i> Renewal Status</div>
                <div class="number renewal-text"><?= e($stats['renewal_status']['label']) ?></div>
                <div class="bar"><span style="width: <?= (int) $stats['renewal_status']['percent'] ?>%;"></span></div>
                <div class="stat-note"><?= e($stats['renewal_status']['label']) ?></div>
            </a>
        </div>

        <div class="lower scholar-dashboard-lower">
            <div class="panel panel-link" role="link" tabindex="0" data-panel-url="<?= BASE_URL ?>public/scholar/announcements.php">
                <div class="panel-title">
                    <span>Recent Announcements</span>
                    <a href="<?= BASE_URL ?>public/scholar/announcements.php" class="view-all">View all →</a>
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

            <div class="panel panel-link" role="link" tabindex="0" data-panel-url="<?= BASE_URL ?>public/scholar/quick-links.php">
                <div class="panel-title">
                    <span>Quick Links</span>
                    <a href="<?= BASE_URL ?>public/scholar/quick-links.php" class="view-all">View all →</a>
                </div>
                <div class="dashboard-scroll-list">
                    <?php foreach ($quickLinks as $link): ?>
                        <a class="quick" href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-link" aria-hidden="true"></i> <?= e($link['title']) ?></a>
                    <?php endforeach; ?>

                    <?php if (empty($quickLinks)): ?>
                        <div class="announcement">
                            <div class="bullet">•</div>
                            <div class="announcement-info">
                                <b>No quick links</b>
                                <small>Available soon</small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
<?php require __DIR__ . '/../components/footer.php'; ?>
