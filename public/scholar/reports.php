<?php

require_once __DIR__ . '/../../middleware/scholar.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/models/Scholar.php';
require_once __DIR__ . '/../../app/models/Coordinator.php';
require_once __DIR__ . '/../../app/models/Report.php';
require_once __DIR__ . '/../../app/models/Notification.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

$userId = (int) $_SESSION['user_id'];
$scholar = (new Scholar($pdo))->findByUserId($userId);

if (!$scholar) {
    http_response_code(403);
    exit('Scholar profile not found.');
}

$model = new Report($pdo);
$notifications = new Notification($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isValidCsrfToken(post('csrf_token'))) {
        flash('error', 'Unable to verify your request. Please try again.');
        redirectTo(BASE_URL . 'public/scholar/reports.php');
    }

    if (post('action') === 'delete-report') {
        $postedReportId = post('report_id');
        $reportId = is_string($postedReportId)
            ? filter_var($postedReportId, FILTER_VALIDATE_INT)
            : false;
        $deleted = is_int($reportId)
            && $reportId > 0
            && $model->deleteForReviewByUser($reportId, $userId);

        flash(
            $deleted ? 'success' : 'error',
            $deleted ? 'Report deleted.' : 'Only reports awaiting coordinator review can be deleted.'
        );
        redirectTo(BASE_URL . 'public/scholar/reports.php');
    }

    $subject = trim((string) post('subject'));
    $message = trim((string) post('message'));
    $target = null;

    foreach ((new Coordinator($pdo))->getAllCoordinators() as $coordinator) {
        if ($coordinator['year_level'] === $scholar['year_level'] && $coordinator['status'] === 'active') {
            $target = (int) $coordinator['user_id'];
            break;
        }
    }

    $ok = $target !== null
        && $subject !== ''
        && $message !== ''
        && $model->create($userId, $target, $subject, $message);

    if ($ok) {
        $notifications->createNotification(
            $target,
            'New Scholar Report',
            nameCaps($scholar) . ' submitted a report.',
            'report'
        );
    }

    flash($ok ? 'success' : 'error', $ok ? 'Report submitted.' : 'Unable to submit report. Make sure an active Coordinator is assigned to your year level.');
    redirectTo(BASE_URL . 'public/scholar/reports.php');
}

$items = $model->forUser($userId);
$pageTitle = 'Reports';
require __DIR__ . '/../../app/views/components/header.php';
?>
<main class="scholar-reports-page">
    <section class="duty-records-content">
        <div class="page-head duty-page-head">
            <div>
                <h1>Reports</h1>
            </div>
            <div class="duty-info-banner">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>Your coordinator receives and reviews each report you submit.</span>
            </div>
        </div>

        <?php require __DIR__ . '/../../app/views/components/flash-message.php'; ?>

        <div class="scholar-report-layout">
            <div class="records-card report-submit-card">
                <div class="records-head">
                    <div>
                        <h2>Submit a Report</h2>
                        <p class="sub-small">Share a concern, issue, or request for follow-up</p>
                    </div>
                </div>

                <form method="post" class="duty-submission-form report-form" data-confirm-scholar-report-submit>
                    <input type="hidden" name="action" value="submit-report">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <div class="form-row">
                        <label for="subject">Subject</label>
                        <input id="subject" name="subject" maxlength="255" placeholder="Briefly describe your report" required>
                    </div>
                    <div class="form-row report-message-field">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" rows="7" placeholder="Share the details your coordinator should know." required></textarea>
                    </div>
                    <button type="submit" class="report-submit-button">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                        Submit Report
                    </button>
                </form>
            </div>

            <div class="records-card report-records-card">
                <div class="records-head">
                    <div>
                        <h2>My Reports</h2>
                        <p class="sub-small">All messages sent to your assigned coordinator</p>
                    </div>
                    <div class="duty-records-actions">
                        <label class="duty-record-filter">
                            <span>Show</span>
                            <select id="scholar-report-date-filter" aria-label="Filter reports by date">
                                <option value="all">All</option>
                                <option value="latest">Latest</option>
                                <option value="last-week">Last week</option>
                                <option value="last-month">Last month</option>
                            </select>
                        </label>
                        <span class="record-count" id="scholar-report-count" aria-live="polite"><?= e(count($items)) ?> reports</span>
                    </div>
                </div>

                <div class="table-scroll scholar-reports-table-scroll">
                    <table class="data-table report-records-table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>To</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <?php $reportDialogId = 'scholar-report-dialog-' . $item['id']; ?>
                                <tr class="data-row scholar-report-row" tabindex="0" data-report-dialog="<?= e($reportDialogId) ?>" data-scholar-report-row data-report-date="<?= e((new DateTime($item['created_at']))->format('Y-m-d')) ?>" aria-haspopup="dialog">
                                    <td><?= e($item['subject']) ?></td>
                                    <td><?= e($item['to_email']) ?></td>
                                    <td>
                                        <span class="status-pill status-<?= e(strtolower(str_replace(' ', '_', $item['status']))) ?>"><?= e(ucwords(str_replace('_', ' ', $item['status']))) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$items): ?>
                                <tr><td colspan="3">No reports found.</td></tr>
                            <?php endif; ?>
                            <tr id="scholar-report-filter-empty" hidden><td colspan="3">No reports found for this date range.</td></tr>
                        </tbody>
                    </table>
                </div>
                <?php foreach ($items as $item): ?>
                    <?php
                    $reportDialogId = 'scholar-report-dialog-' . $item['id'];
                    ?>
                    <dialog class="scholar-report-dialog" id="<?= e($reportDialogId) ?>" aria-labelledby="<?= e($reportDialogId) ?>-title">
                        <div class="scholar-report-dialog-head">
                            <div>
                                <span class="scholar-report-dialog-eyebrow">Report details</span>
                                <h2 id="<?= e($reportDialogId) ?>-title"><?= e($item['subject']) ?></h2>
                            </div>
                            <button type="button" class="scholar-report-dialog-close" aria-label="Close report details">
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="scholar-report-dialog-content">
                            <article class="coordinator-report-content">
                                <div class="coordinator-report-meta">
                                    <div>
                                        <span>Sent to</span>
                                        <p><?= e($item['to_email']) ?></p>
                                    </div>
                                    <div>
                                        <span>Created</span>
                                        <p><?= e((new DateTime($item['created_at']))->format('M j, Y \a\t g:i A')) ?></p>
                                    </div>
                                </div>
                                <section class="coordinator-report-message">
                                    <h3>Your message</h3>
                                    <p><?= nl2br(e($item['message'])) ?></p>
                                </section>
                                <section class="coordinator-report-response">
                                    <h3>Coordinator response</h3>
                                    <p><?= nl2br(e($item['response'] ?: 'No response yet.')) ?></p>
                                </section>
                            </article>
                            <?php if ($item['status'] === 'for_review'): ?>
                                <form method="post" data-confirm-scholar-report-delete>
                                    <input type="hidden" name="action" value="delete-report">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="report_id" value="<?= e($item['id']) ?>">
                                    <button type="submit" class="duty-submission-delete-button">
                                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                        Delete report
                                    </button>
                                </form>
                            <?php else: ?>
                                <button
                                    type="button"
                                    class="duty-submission-delete-button is-locked"
                                    disabled
                                    title="Only reports awaiting review can be deleted."
                                    aria-label="Delete report locked: this report is no longer awaiting review"
                                >
                                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                    Delete locked
                                </button>
                            <?php endif; ?>
                        </div>
                    </dialog>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>
<dialog class="logout-confirm-dialog" id="scholar-report-delete-confirm-dialog" aria-labelledby="scholar-report-delete-confirm-title" aria-describedby="scholar-report-delete-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></div>
    <h2 id="scholar-report-delete-confirm-title">Delete report?</h2>
    <p id="scholar-report-delete-confirm-description">This will permanently delete your report. This action cannot be undone.</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-scholar-report-delete-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-scholar-report-delete-confirm>Delete Report</button>
    </div>
</dialog>
<dialog class="logout-confirm-dialog scholar-report-submit-confirm-dialog" id="scholar-report-submit-confirm-dialog" aria-labelledby="scholar-report-submit-confirm-title" aria-describedby="scholar-report-submit-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></div>
    <h2 id="scholar-report-submit-confirm-title">Submit report?</h2>
    <p id="scholar-report-submit-confirm-description">Are you sure you want to send this report to your Coordinator for review?</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-scholar-report-submit-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-scholar-report-submit-confirm>Submit Report</button>
    </div>
</dialog>
<?php
$pageScripts = [
    'public/assets/js/duty-records-filter.js',
    'public/assets/js/scholar-report-popouts.js?v=' . filemtime(__DIR__ . '/../assets/js/scholar-report-popouts.js'),
];
require __DIR__ . '/../../app/views/components/footer.php';
?>
