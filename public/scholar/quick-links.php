<?php

require_once __DIR__ . '/../../middleware/scholar.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/models/QuickLink.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

$items = (new QuickLink($pdo))->all();
$pageTitle = 'Quick Links';
$pageClass = 'scholar-quick-links-page';
$pageScripts = ['public/assets/js/quick-links-filter.js'];
require __DIR__ . '/../../app/views/components/header.php';
?>
<main>
    <section class="duty-records-content">
        <div class="page-head duty-page-head scholar-links-head">
            <div>
                <h2>Quick Links</h2>
            </div>
            <div class="scholar-links-tools">
                <?php if ($items): ?>
                    <label class="duty-record-filter">
                        <span>Show</span>
                        <select id="quick-links-filter" aria-label="Filter quick links">
                            <option value="all">All</option>
                            <option value="latest">Latest</option>
                        </select>
                    </label>
                <?php endif; ?>
                <span class="scholar-page-count"><i class="fa-solid fa-link" aria-hidden="true"></i><span id="quick-links-count" aria-live="polite"><?= e(count($items)) ?> resources</span></span>
            </div>
        </div>

        <?php if ($items): ?>
            <div class="scholar-quick-links-scroll">
                <div class="quick-links-grid">
                    <?php foreach ($items as $item): ?>
                        <?php $createdAt = !empty($item['created_at']) ? strtotime((string) $item['created_at']) : false; ?>
                        <article class="records-card quick-link-card" data-created-at="<?= e($createdAt ?: 0) ?>">
                            <div class="quick-link-head">
                                <div class="quick-link-icon">
                                    <i class="fa-solid fa-link" aria-hidden="true"></i>
                                </div>
                                <div>
                                    <span class="quick-link-kicker">Scholar resource</span>
                                    <h2><?= e($item['title']) ?></h2>
                                </div>
                            </div>

                            <?php if ($item['description']): ?>
                                <p class="quick-link-desc"><?= nl2br(e($item['description'])) ?></p>
                            <?php endif; ?>

                            <div class="quick-link-card-footer">
                                <?php if ($createdAt !== false): ?>
                                    <span class="quick-link-created"><i class="fa-regular fa-calendar" aria-hidden="true"></i> Created <?= e(date('M j, Y', $createdAt)) ?></span>
                                <?php endif; ?>
                                <a class="quick-link-btn" href="<?= e($item['url']) ?>" target="_blank" rel="noopener">
                                    Open Link
                                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="records-card empty-state-card">
                <p class="empty-inline">No quick links.</p>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../../app/views/components/footer.php'; ?>
