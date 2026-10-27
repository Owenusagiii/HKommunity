<?php

require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/models/Report.php';
require_once __DIR__ . '/../../app/models/Notification.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

$model = new Report($pdo);
$notifications = new Notification($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) post('id');
    $status = post('status');
    $response = trim((string) post('response'));
    $ok = in_array($status, ['for_review', 'resolved', 'rejected'], true)
        && $model->updateStatus($id, $status, $response !== '' ? $response : null);

    if ($ok) {
        $q = $pdo->prepare('SELECT submitted_by, subject FROM reports WHERE id = :id');
        $q->execute(['id' => $id]);
        if ($report = $q->fetch()) {
            $notifications->createNotification(
                (int) $report['submitted_by'],
                'Report Updated',
                'Your report "' . $report['subject'] . '" was updated by Admin.',
                'report'
            );
        }
    }

    flash($ok ? 'success' : 'error', $ok ? 'Report updated.' : 'Unable to update report.');
    redirectTo(BASE_URL . 'public/admin/reports.php');
}

$items = $model->all();
$pageTitle = 'Reports';
require __DIR__ . '/../../app/views/components/header.php';
?>
<main class="admin-reports-main">
    <section class="duty-records-content admin-users-page admin-reports-page">
        <div class="page-head duty-page-head">
            <div>
                <h1>Reports</h1>
            </div>
            <div class="duty-info-banner">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>Open a report record to review its message and update its status or response.</span>
            </div>
        </div>

        <?php require __DIR__ . '/../../app/views/components/flash-message.php'; ?>

        <section class="records-card admin-users-card admin-reports-records-card" aria-labelledby="admin-report-records-title">
            <div class="records-head">
                <div>
                    <h2 class="admin-users-section-title" id="admin-report-records-title">
                        <span class="admin-users-section-icon"><i class="fa-solid fa-flag" aria-hidden="true"></i></span>
                        Escalated Reports
                    </h2>
                    <p class="sub-small">Reports escalated to Admin for review and follow-up</p>
                </div>
                <div class="admin-reports-tools">
                    <span class="record-count" id="admin-report-count" aria-live="polite"><?= e(count($items)) ?> reports</span>
                    <label class="visually-hidden" for="admin-report-status-filter">Filter reports by status</label>
                    <select class="admin-reports-status-filter" id="admin-report-status-filter" aria-controls="admin-reports-table">
                        <option value="">All statuses</option>
                        <?php foreach (['for_review', 'resolved', 'rejected'] as $statusOption): ?>
                            <option value="<?= e($statusOption) ?>"><?= e($statusOption === 'for_review' ? 'For Review' : ucwords(str_replace('_', ' ', $statusOption))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="table-scroll admin-reports-table-scroll">
                <table class="data-table admin-role-table admin-reports-table" id="admin-reports-table">
                    <thead>
                        <tr>
                            <th>From</th>
                            <th>Subject</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr class="admin-popout-row" tabindex="0" data-admin-report-row data-report-status="<?= e($item['status']) ?>" data-admin-dialog="admin-report-<?= e($item['id']) ?>" aria-haspopup="dialog" aria-controls="admin-report-<?= e($item['id']) ?>">
                                <td><?= e($item['from_email']) ?></td>
                                <td><?= e($item['subject']) ?></td>
                                <td><span class="status-pill status-<?= e($item['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $item['status']))) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="admin-reports-filter-empty" <?= $items ? 'hidden' : '' ?>>
                            <td colspan="3"><?= $items ? 'No reports match this status.' : 'No reports found.' ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php foreach ($items as $item): ?>
                <dialog class="admin-record-dialog admin-report-dialog" id="admin-report-<?= e($item['id']) ?>" aria-labelledby="admin-report-title-<?= e($item['id']) ?>">
                    <div class="coordinator-report-dialog-head">
                        <div>
                            <span class="coordinator-report-dialog-label"><i class="fa-solid fa-file-lines" aria-hidden="true"></i> Scholar report</span>
                            <h2 id="admin-report-title-<?= e($item['id']) ?>"><?= e($item['subject']) ?></h2>
                        </div>
                        <button type="button" class="coordinator-report-dialog-close" aria-label="Close report details">&times;</button>
                    </div>
                    <div class="admin-record-dialog-content admin-report-dialog-content">
                        <article class="coordinator-report-content">
                            <div class="coordinator-report-meta">
                                <div>
                                    <span>From</span>
                                    <p><?= e($item['from_email']) ?></p>
                                </div>
                                <div>
                                    <span>Created</span>
                                    <p><?= e((new DateTime($item['created_at']))->format('M j, Y \a\t g:i A')) ?></p>
                                </div>
                            </div>
                            <section class="coordinator-report-message">
                                <h3>Message</h3>
                                <p><?= nl2br(e($item['message'])) ?></p>
                            </section>
                            <section class="coordinator-report-response">
                                <h3>Response</h3>
                                <p><?= nl2br(e($item['response'] ?: 'No response yet.')) ?></p>
                            </section>
                        </article>
                        <?php if ($item['status'] === 'for_review'): ?>
                            <section class="coordinator-report-actions" aria-labelledby="manage-report-title-<?= e($item['id']) ?>">
                                <h3 id="manage-report-title-<?= e($item['id']) ?>"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Manage report</h3>
                                <div class="coordinator-report-action-row">
                                    <form method="post" class="coordinator-report-update-form admin-report-update-form">
                                        <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                        <div class="form-row">
                                            <label for="report-status-<?= e($item['id']) ?>">Status</label>
                                            <select id="report-status-<?= e($item['id']) ?>" name="status" required>
                                                <option value="" selected disabled>Select a status</option>
                                                <option value="resolved">Resolved</option>
                                                <option value="rejected">Rejected</option>
                                            </select>
                                        </div>
                                        <div class="form-row">
                                            <label for="report-response-<?= e($item['id']) ?>">Response</label>
                                            <textarea id="report-response-<?= e($item['id']) ?>" name="response"><?= e($item['response']) ?></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-green">Save Response</button>
                                    </form>
                                </div>
                            </section>
                        <?php endif; ?>
                    </div>
                </dialog>
            <?php endforeach; ?>
        </section>
    </section>
</main>
<dialog class="logout-confirm-dialog admin-report-confirm-dialog" id="admin-report-confirm-dialog" aria-labelledby="admin-report-confirm-title" aria-describedby="admin-report-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-circle-question" aria-hidden="true"></i></div>
    <h2 id="admin-report-confirm-title">Save response?</h2>
    <p id="admin-report-confirm-description">Save this response and update the report status?</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-admin-report-confirm-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-admin-report-confirm-submit>Save Response</button>
    </div>
</dialog>
<?php
$pageScripts = [
    'public/assets/js/admin-table-popouts.js',
    'public/assets/js/admin-reports-filter.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-reports-filter.js'),
    'public/assets/js/admin-report-confirmation.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-report-confirmation.js')
];
require __DIR__ . '/../../app/views/components/footer.php';
?>
