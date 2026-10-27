<?php

require_once __DIR__ . '/components/header.php';

?>
<main>
    <?php $isAdminNotificationsPage = ($_SESSION['role'] ?? '') === 'admin'; ?>
    <section class="announcement-content notification-content <?= $isAdminNotificationsPage ? 'admin-notifications-page' : '' ?>">
        <div class="page-head announcements-head">
            <div>
                <h1>Notifications</h1>
            </div>
            <?php if ($isAdminNotificationsPage): ?>
                <div class="duty-records-actions updates-filter-actions admin-notifications-toolbar">
                    <label class="duty-record-filter">
                        <span>Show</span>
                        <select id="updates-date-filter" aria-label="Filter notifications by date">
                            <option value="all">All</option>
                            <option value="latest">Latest</option>
                            <option value="last-week">Last week</option>
                            <option value="last-month">Last month</option>
                        </select>
                    </label>
                    <span class="record-count" id="updates-count" aria-live="polite">0 updates</span>
                    <button id="mark-all-notifications-read" class="updates-mark-all-read" type="button" disabled>
                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                        Mark all as read
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($isAdminNotificationsPage): ?>
            <p id="notifications-action-status" class="updates-action-status" role="status" aria-live="polite"></p>
        <?php endif; ?>

        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
            <div class="admin-notifications-scroll">
        <?php endif; ?>
        <div id="notifications-list" class="announce-list" data-api-url="<?= e(BASE_URL . 'api/notifications.php') ?>" data-csrf-token="<?= e(csrfToken()) ?>">
            <p>Loading notifications...</p>
        </div>
        <?php if ($isAdminNotificationsPage): ?>
            <p class="updates-filter-empty" data-updates-empty="notifications" hidden>No notifications found for this date range.</p>
        <?php endif; ?>
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php

$pageScripts = ['public/assets/js/notifications.js'];
if ($isAdminNotificationsPage) {
    $pageScripts[] = 'public/assets/js/duty-records-filter.js';
}
require __DIR__ . '/components/footer.php';
?>
