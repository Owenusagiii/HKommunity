<?php

require_once __DIR__ . '/../../middleware/coordinator.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/models/Announcement.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

$items = (new Announcement($pdo))->allForRole('coordinator');
$pageTitle = 'Announcements & Notifications';
$pageClass = 'scholar-announcements-page';
$pageScripts = [
    'public/assets/js/notifications.js',
    'public/assets/js/duty-records-filter.js',
    'public/assets/js/scholar-announcement-popouts.js',
];
require __DIR__ . '/../../app/views/components/header.php';
?>
<main>
    <section class="announcement-content combined-updates">
        <div class="page-head announcements-head">
            <div class="duty-records-actions updates-filter-actions scholar-updates-toolbar">
                <label class="duty-record-filter">
                    <span>Show</span>
                    <select id="updates-date-filter" aria-label="Filter announcements and notifications by date">
                        <option value="all">All</option>
                        <option value="latest">Latest</option>
                        <option value="last-week">Last week</option>
                        <option value="last-month">Last month</option>
                    </select>
                </label>
                <span class="record-count" id="updates-count" aria-live="polite"><?= e(count($items)) ?> updates</span>
                <button id="mark-all-notifications-read" class="updates-mark-all-read" type="button" disabled>
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    Mark all as read
                </button>
            </div>
        </div>

        <p id="notifications-action-status" class="updates-action-status" role="status" aria-live="polite"></p>

        <div class="updates-columns">
            <section class="updates-column">
                <div class="updates-panel">
                    <h2 class="updates-column-title"><i class="fa-solid fa-bullhorn" aria-hidden="true"></i> Announcements</h2>
                    <div class="scholar-announcements-scroll">
                        <div class="announce-list">
                            <?php foreach ($items as $item): ?>
                                <?php
                                $audienceLabel = $item['audience'] === 'all' ? 'ALL' : strtoupper((string) $item['audience']);
                                $categoryClass = $item['audience'] === 'all' ? 'cat-status' : 'cat-requirements';
                                $createdAt = strtotime((string) $item['created_at']);
                                $announcementDialogId = 'coordinator-announcement-dialog-' . $item['id'];
                                ?>
                                <article class="panel announce-card scholar-announcement-trigger <?= e($categoryClass) ?>" data-announcement-dialog="<?= e($announcementDialogId) ?>" data-update-date="<?= e($createdAt ? date('Y-m-d', $createdAt) : '') ?>" tabindex="0" role="button" aria-haspopup="dialog">
                                    <div class="announce-body">
                                        <span class="announce-tag"><?= e($audienceLabel) ?> &bull; <?= e($createdAt ? strtoupper(date('M d, Y', $createdAt)) : 'RECENT') ?></span>
                                        <h2 class="announce-title"><?= e($item['title']) ?></h2>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <p class="updates-filter-empty" data-updates-empty="announcements" hidden>No announcements found for this date range.</p>

                        <?php if (!$items): ?>
                            <div class="panel empty-announcements">
                                <i class="fa-solid fa-bullhorn" aria-hidden="true"></i>
                                <h2>No announcements yet</h2>
                                <p>Important coordinator updates will appear here.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="updates-column">
                <div class="updates-panel">
                    <h2 class="updates-column-title"><i class="fa-solid fa-bell" aria-hidden="true"></i> Notifications</h2>
                    <div class="scholar-notifications-scroll">
                        <div id="notifications-list" class="announce-list" data-api-url="<?= e(BASE_URL . 'api/notifications.php') ?>" data-csrf-token="<?= e(csrfToken()) ?>">
                            <p>Loading notifications...</p>
                        </div>
                        <p class="updates-filter-empty" data-updates-empty="notifications" hidden>No notifications found for this date range.</p>
                    </div>
                </div>
            </section>
        </div>

        <?php foreach ($items as $item): ?>
            <?php
            $createdAt = strtotime((string) $item['created_at']);
            $announcementDialogId = 'coordinator-announcement-dialog-' . $item['id'];
            ?>
            <dialog class="scholar-announcement-dialog" id="<?= e($announcementDialogId) ?>" aria-labelledby="<?= e($announcementDialogId) ?>-title">
                <div class="scholar-announcement-letter">
                    <header class="scholar-announcement-letter-head">
                        <span><i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i> Official announcement</span>
                        <button type="button" class="scholar-announcement-dialog-close" aria-label="Close announcement">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </header>
                    <div class="scholar-announcement-letter-content">
                        <p class="scholar-announcement-letter-date"><?= e($createdAt ? date('F j, Y', $createdAt) : 'Recently sent') ?></p>
                        <h2 id="<?= e($announcementDialogId) ?>-title"><?= e($item['title']) ?></h2>
                        <div class="scholar-announcement-letter-rule"></div>
                        <div class="scholar-announcement-letter-body"><?= nl2br(e($item['content'])) ?></div>
                        <?php if ($item['link']): ?>
                            <a class="scholar-announcement-letter-link" href="<?= e($item['link']) ?>" target="_blank" rel="noopener noreferrer">
                                Open attached link <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                            </a>
                        <?php endif; ?>
                        <p class="scholar-announcement-sender">Posted by <strong><?= e($item['sender_name'] ?? 'HKommunity') ?></strong></p>
                    </div>
                </div>
            </dialog>
        <?php endforeach; ?>
    </section>
</main>
<?php
require __DIR__ . '/../../app/views/components/footer.php';
?>
