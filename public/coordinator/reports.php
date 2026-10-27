<?php

require_once __DIR__ . '/../../middleware/coordinator.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/models/Coordinator.php';
require_once __DIR__ . '/../../app/models/Report.php';
require_once __DIR__ . '/../../app/models/Notification.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

$userId = (int) $_SESSION['user_id'];
$coordinator = (new Coordinator($pdo))->findByUserId($userId);

if (!$coordinator) {
    http_response_code(403);
    exit('Coordinator profile not found.');
}

$model = new Report($pdo);
$notifications = new Notification($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    $id = (int) post('id');
    $ok = false;
    $errorMessage = 'Unable to update report.';

    if ($action === 'escalate') {
        $stmt = $pdo->prepare(
            'SELECT id
             FROM reports
             WHERE id = :id AND submitted_to = :user_id'
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $owned = $stmt->fetchColumn();
        $admin = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active' LIMIT 1")->fetchColumn();

        if ($owned && $admin) {
            $ok = $model->transfer($id, (int) $admin);
            if ($ok) {
                $notifications->createNotification(
                    (int) $admin,
                    'Coordinator Report Escalated',
                    'A Coordinator escalated a report to Admin.',
                    'report'
                );
            }
        }
    } elseif ($action === 'update') {
        $status = post('status');
        $rawResponse = post('response');
        $response = is_string($rawResponse) ? trim($rawResponse) : '';
        $validStatus = is_string($status) && in_array($status, ['for_review', 'resolved', 'rejected'], true);

        if (!$validStatus || $response === '') {
            $errorMessage = 'Choose a status and enter a response before saving.';
        } else {
            $stmt = $pdo->prepare('SELECT submitted_to FROM reports WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $recipient = $stmt->fetchColumn();

            if ((int) $recipient === $userId) {
                $ok = $model->updateStatus($id, $status, $response);

                if ($ok) {
                    $q = $pdo->prepare('SELECT submitted_by, subject FROM reports WHERE id = :id');
                    $q->execute(['id' => $id]);
                    if ($report = $q->fetch()) {
                        $notifications->createNotification(
                            (int) $report['submitted_by'],
                            'Report Updated',
                            'Your report "' . $report['subject'] . '" was updated by the Coordinator.',
                            'report'
                        );
                    }
                }
            }
        }
    }

    flash($ok ? 'success' : 'error', $ok ? 'Report updated.' : $errorMessage);
    redirectTo(BASE_URL . 'public/coordinator/reports.php');
}

$items = $model->forUser($userId);
$pageTitle = 'Reports';
require __DIR__ . '/../../app/views/components/header.php';
?>
<main class="coordinator-reports-main">
    <section class="duty-records-content coordinator-reports-page">
        <div class="page-head duty-page-head">
            <div>
                <h1>Reports</h1>
            </div>
            <div class="duty-info-banner">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>Open a report to review its message, respond, or escalate it to an administrator.</span>
            </div>
        </div>

        <?php require __DIR__ . '/../../app/views/components/flash-message.php'; ?>

        <div class="records-card report-records-card coordinator-report-records-card">
            <div class="records-head">
                <div>
                    <h2>Scholar Reports</h2>
                    <p class="sub-small">Reports assigned to you for review</p>
                </div>
                <div class="coordinator-renewals-tools coordinator-report-filter-tools">
                    <span class="record-count coordinator-report-count-badge" id="coordinator-report-count"><?= e(count($items)) ?> reports</span>
                    <label class="visually-hidden" for="coordinator-report-status-filter">Filter reports by status</label>
                    <select id="coordinator-report-status-filter" class="coordinator-promotion-filter">
                        <option value="">All statuses</option>
                        <option value="for_review">For Review</option>
                        <option value="resolved">Resolved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
            </div>

            <div class="coordinator-report-table-scroll">
                <table class="data-table report-records-table coordinator-report-table">
                    <thead>
                        <tr>
                            <th>From</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr
                                class="coordinator-report-row"
                                tabindex="0"
                                role="button"
                                data-report-row
                                data-status="<?= e($item['status']) ?>"
                                data-report-dialog="report-dialog-<?= e($item['id']) ?>"
                                aria-haspopup="dialog"
                                aria-controls="report-dialog-<?= e($item['id']) ?>"
                            >
                                <td><?= e($item['from_email']) ?></td>
                                <td><?= e($item['subject']) ?></td>
                                <td>
                                    <span class="status-pill status-<?= e($item['status']) ?>">
                                        <?= e(ucwords(str_replace('_', ' ', $item['status']))) ?>
                                    </span>
                                </td>
                                <td><?= e((new DateTime($item['created_at']))->format('M j, Y \a\t g:i A')) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$items): ?>
                            <tr><td colspan="4">No reports found.</td></tr>
                        <?php else: ?>
                            <tr class="coordinator-report-filter-empty" hidden>
                                <td colspan="4">No reports match this status.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php foreach ($items as $item): ?>
                <dialog class="coordinator-report-dialog" id="report-dialog-<?= e($item['id']) ?>" aria-labelledby="report-dialog-title-<?= e($item['id']) ?>">
                    <div class="coordinator-report-dialog-head">
                        <div>
                            <span class="coordinator-report-dialog-label"><i class="fa-solid fa-file-lines" aria-hidden="true"></i> Scholar report</span>
                            <h2 id="report-dialog-title-<?= e($item['id']) ?>"><?= e($item['subject']) ?></h2>
                        </div>
                        <button type="button" class="coordinator-report-dialog-close" aria-label="Close report details">&times;</button>
                    </div>
                    <div class="report-details <?= ((int) $item['submitted_to'] === $userId && $item['status'] === 'for_review') ? 'has-actions' : 'no-actions' ?>">
                        <div class="coordinator-report-detail-copy">
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
                        </div>

                        <?php if ((int) $item['submitted_to'] === $userId && $item['status'] === 'for_review'): ?>
                            <div class="coordinator-report-actions">
                                <h3><i class="fa-solid fa-sliders" aria-hidden="true"></i> Manage report</h3>
                                <div class="coordinator-report-action-row">
                                    <form method="post" class="coordinator-report-update-form" data-confirm-report-action="update">
                                        <input type="hidden" name="action" value="update">
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
                                            <textarea id="report-response-<?= e($item['id']) ?>" name="response" required></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-green">Save response</button>
                                    </form>
                                    <form method="post" class="coordinator-report-escalate-form" data-confirm-report-action="escalate">
                                        <input type="hidden" name="action" value="escalate">
                                        <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                        <button type="submit" class="btn btn-report">Escalate to Admin</button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </dialog>
            <?php endforeach; ?>
        </div>
    </section>
</main>
<dialog class="logout-confirm-dialog coordinator-report-confirm-dialog" id="coordinator-report-confirm-dialog" aria-labelledby="coordinator-report-confirm-title" aria-describedby="coordinator-report-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-circle-question" aria-hidden="true"></i></div>
    <h2 id="coordinator-report-confirm-title">Confirm report action</h2>
    <p id="coordinator-report-confirm-description">Are you sure you want to continue?</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-report-confirm-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-report-confirm-submit>Confirm</button>
    </div>
</dialog>
<?php
$pageScripts = ['public/assets/js/coordinator-reports.js?v=' . filemtime(__DIR__ . '/../assets/js/coordinator-reports.js')];
require __DIR__ . '/../../app/views/components/footer.php';
?>
