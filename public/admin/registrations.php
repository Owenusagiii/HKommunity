<?php

require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/controllers/AdminRegistrationController.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

$controller = new AdminRegistrationController($pdo);
$studentId = trim((string) ($_GET['student_id'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));

if (!in_array($status, ['', 'pending', 'approved', 'rejected'], true)) {
    $status = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) post('id');
    $action = post('action');

    if ($action === 'verify') {
        $ok = $controller->getRegistration($id) !== null
            && $controller->verifyRegistration($id);
        flash($ok ? 'success' : 'error', $ok ? 'Registration verification refreshed.' : 'Unable to verify registration.');
    } elseif ($action === 'approve') {
        $ok = $controller->approveRegistration($id, (int) $_SESSION['user_id']);
        flash($ok ? 'success' : 'error', $ok ? 'Registration approved and Scholar account created.' : 'Only a matched pending registration can be approved.');
    } elseif ($action === 'bulk_approve') {
        $submittedIds = post('registration_ids', []);
        if (!isValidCsrfToken(post('csrf_token'))) {
            flash('error', 'Unable to verify your request. Please try again.');
        } elseif (!is_array($submittedIds) || $submittedIds === []) {
            flash('error', 'Select at least one matched pending registration to approve.');
        } else {
            $registrationIds = [];
            foreach ($submittedIds as $submittedId) {
                if (!is_int($submittedId) && !is_string($submittedId)) {
                    $registrationIds = [];
                    break;
                }

                $validatedId = filter_var($submittedId, FILTER_VALIDATE_INT);
                if ($validatedId === false || $validatedId < 1) {
                    $registrationIds = [];
                    break;
                }
                $registrationIds[] = $validatedId;
            }

            $registrationIds = array_values(array_unique($registrationIds));
            if ($registrationIds === []) {
                flash('error', 'The selected registrations could not be validated. Please refresh and try again.');
            } else {
                $approvedCount = 0;
                foreach ($registrationIds as $registrationId) {
                    if ($controller->approveRegistration($registrationId, (int) $_SESSION['user_id'])) {
                        $approvedCount++;
                    }
                }

                $failedCount = count($registrationIds) - $approvedCount;
                if ($failedCount === 0) {
                    flash('success', sprintf(
                        '%d registration%s approved successfully.',
                        $approvedCount,
                        $approvedCount === 1 ? '' : 's'
                    ));
                } elseif ($approvedCount > 0) {
                    flash('error', sprintf(
                        '%d registration%s approved; %d could not be approved. Check that each remaining request is still pending, matched, and does not already have an account.',
                        $approvedCount,
                        $approvedCount === 1 ? '' : 's',
                        $failedCount
                    ));
                } else {
                    flash('error', 'No selected registrations could be approved. Check that they are still pending, matched, and do not already have accounts.');
                }
            }
        }
    } elseif ($action === 'reject') {
        $reason = trim((string) post('rejection_reason'));
        $ok = $reason !== '' && $controller->rejectRegistration($id, (int) $_SESSION['user_id'], $reason);
        flash($ok ? 'success' : 'error', $ok ? 'Registration rejected.' : 'A rejection reason is required.');
    }

    redirectTo(BASE_URL . 'public/admin/registrations.php');
}

$items = $controller->getRegistrations();

$pageTitle = 'Registration';
require __DIR__ . '/../../app/views/components/header.php';
?>
<main class="admin-registration-main">
    <section class="duty-records-content admin-registration-page">
        <div class="page-head duty-page-head">
            <div>
                <h1>Scholar Registration Requests</h1>
            </div>
            <div class="duty-info-banner">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>Open a request to reverify, approve, or reject a pending registration.</span>
            </div>
        </div>

        <?php require __DIR__ . '/../../app/views/components/flash-message.php'; ?>

        <div class="records-card admin-users-card admin-registration-card coordinator-scholars-card">
            <div class="records-head">
                <div>
                    <h2 class="admin-users-section-title"><span class="admin-users-section-icon"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i></span>Registration Records</h2>
                    <p class="sub-small">Scholar application submissions</p>
                </div>
                <div class="coordinator-scholars-tools admin-registration-tools">
                    <span class="record-count" id="registration-result-count" aria-live="polite"><?= e(count($items)) ?> requests</span>
                    <span class="admin-registration-selection-count" id="registration-selection-count" aria-live="polite" hidden>0 selected</span>
                    <button
                        type="submit"
                        class="btn btn-green admin-registration-bulk-approve"
                        form="registration-bulk-approval-form"
                        data-confirm-admin-action
                        data-confirm-title="Approve selected registrations?"
                        data-confirm-description="Only selected requests that are still pending and matched will be approved."
                        data-confirm-submit-label="Approve Selected"
                        data-confirm-variant="success"
                        hidden
                        disabled
                    >Approve selected</button>
                    <form method="get" class="coordinator-scholars-search admin-registration-search" data-admin-registration-search>
                        <label class="visually-hidden" for="status">Filter registrations by status</label>
                        <select id="status" name="status">
                            <option value="">All statuses</option>
                            <?php foreach (['pending', 'approved', 'rejected'] as $option): ?>
                                <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="requirement-search">
                            <span class="visually-hidden">Search registrations by student ID number</span>
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <input type="search" id="student_id" name="student_id" value="<?= e($studentId) ?>" placeholder="Search by student ID" autocomplete="off" aria-controls="admin-registrations-table">
                        </label>
                    </form>
                </div>
            </div>

            <form method="post" id="registration-bulk-approval-form">
                <input type="hidden" name="action" value="bulk_approve">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            </form>
            <div class="table-scroll coordinator-scholars-table-scroll">
                <table class="data-table admin-registration-table" id="admin-registrations-table">
                    <thead>
                        <tr>
                            <th class="admin-registration-select-column">
                                <input type="checkbox" id="registration-select-all" aria-label="Select all eligible registrations in the current view">
                            </th>
                            <th class="admin-registration-scholar-heading"><span>Scholar</span></th>
                            <th>Year Level</th>
                            <th>Verification</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr class="registration-row admin-popout-row" tabindex="0" data-admin-dialog="admin-registration-<?= e($item['id']) ?>" data-admin-registration-row data-search-id="<?= e($item['student_id_number']) ?>" data-search-status="<?= e($item['status']) ?>" aria-haspopup="dialog" aria-controls="admin-registration-<?= e($item['id']) ?>">
                            <td class="admin-registration-select-column">
                                <?php if ($item['status'] === 'pending' && $item['verification_status'] === 'matched'): ?>
                                    <input
                                        type="checkbox"
                                        class="admin-registration-select"
                                        name="registration_ids[]"
                                        value="<?= e($item['id']) ?>"
                                        form="registration-bulk-approval-form"
                                        aria-label="Select <?= e(nameCaps($item)) ?> for approval"
                                    >
                                <?php endif; ?>
                            </td>
                            <td class="admin-registration-scholar-cell">
                                    <strong class="admin-scholar-progress-name"><?= e(nameCaps($item)) ?></strong>
                                    <small><?= e($item['student_id_number']) ?></small>
                                </td>
                                <td><?= e($item['year_level']) ?></td>
                                <td><span class="status-pill status-<?= e($item['verification_status'] ?: 'not_started') ?>"><?= e(ucwords(str_replace('_', ' ', (string) ($item['verification_status'] ?: 'Not checked')))) ?></span></td>
                                <td><span class="status-pill status-<?= e($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>

                        <tr class="admin-registration-search-empty" hidden><td colspan="5">No registration requests match your search.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    <?php foreach ($items as $item): ?>
        <dialog class="admin-record-dialog scholar-requirement-dialog admin-registration-detail-dialog" id="admin-registration-<?= e($item['id']) ?>" aria-labelledby="admin-registration-title-<?= e($item['id']) ?>">
            <div class="scholar-requirement-dialog-head">
                <div>
                    <span class="scholar-requirement-dialog-eyebrow">Registration request</span>
                    <h2 id="admin-registration-title-<?= e($item['id']) ?>"><?= e(nameCaps($item)) ?></h2>
                    <span class="admin-registration-header-student-id"><?= e($item['student_id_number']) ?></span>
                </div>
                <button type="button" class="scholar-requirement-dialog-close" aria-label="Close registration details"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="scholar-requirement-dialog-content admin-user-detail-content">
                <section class="admin-user-detail-card admin-registration-detail-card">
                    <div class="admin-user-detail-card-heading">
                        <h3>Registration details</h3>
                        <p>Review the applicant's information and registration verification below.</p>
                    </div>
                    <div class="scholar-requirement-detail-grid">
                        <div><span>Email</span><strong><?= e($item['phinmaed_email']) ?></strong></div>
                        <div><span>Course</span><strong><?= e($item['course_name'] ?? 'N/A') ?></strong></div>
                        <div><span>Year level</span><strong><?= e($item['year_level']) ?></strong></div>
                        <div><span>HK Discount Type</span><strong><?= e($item['hk_discount_type']) ?></strong></div>
                        <div><span>Verification</span><strong><span class="status-pill status-<?= e($item['verification_status'] ?: 'not_started') ?>"><?= e(ucwords(str_replace('_', ' ', (string) ($item['verification_status'] ?: 'Not checked')))) ?></span></strong></div>
                        <div><span>Status</span><strong><span class="status-pill status-<?= e($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span></strong></div>
                        <div><span>Submitted</span><strong><?= e(!empty($item['created_at']) ? (new DateTime($item['created_at']))->format('M j, Y \a\t g:i A') : 'N/A') ?></strong></div>
                    </div>
                    <?php if ($item['rejection_reason']): ?>
                        <section class="scholar-requirement-detail-section is-rejected">
                            <h3>Rejection reason</h3>
                            <p><?= nl2br(e($item['rejection_reason'])) ?></p>
                        </section>
                    <?php endif; ?>
                </section>
                <?php if ($item['status'] === 'pending'): ?>
                    <div class="admin-registration-actions admin-registration-popout-actions">
                        <form method="post" class="inline-form admin-registration-action-form">
                            <input type="hidden" name="action" value="verify">
                            <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                            <button type="submit" class="btn btn-green">Reverify</button>
                        </form>
                        <?php if ($item['verification_status'] === 'matched'): ?>
                            <form method="post" class="inline-form admin-registration-action-form">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                <button
                                    type="submit"
                                    class="btn btn-green"
                                    data-confirm-admin-action
                                    data-confirm-variant="success"
                                    data-confirm-title="Approve registration?"
                                    data-confirm-description="Approve this matched registration and create the scholar account?"
                                    data-confirm-submit-label="Approve Registration"
                                >Approve</button>
                            </form>
                        <?php endif; ?>
                        <button
                            type="button"
                            class="btn btn-danger admin-registration-reject-trigger"
                            data-admin-registration-reject="<?= e($item['id']) ?>"
                        >Reject</button>
                    </div>
                <?php endif; ?>
            </div>
        </dialog>
    <?php endforeach; ?>
    </section>
</main>
<dialog class="logout-confirm-dialog admin-registration-reject-confirm-dialog" id="admin-registration-reject-confirm-dialog" aria-labelledby="admin-registration-reject-confirm-title" aria-describedby="admin-registration-reject-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div>
    <h2 id="admin-registration-reject-confirm-title">Reject registration?</h2>
    <p id="admin-registration-reject-confirm-description">Provide a reason to reject this registration request.</p>
    <form method="post" class="admin-registration-reject-confirm-form">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="id" value="">
        <label for="admin-registration-rejection-reason">Rejection reason</label>
        <textarea id="admin-registration-rejection-reason" name="rejection_reason" required></textarea>
        <div class="logout-confirm-actions">
            <button type="button" class="logout-confirm-cancel" data-admin-registration-reject-cancel>Cancel</button>
            <button type="submit" class="logout-confirm-submit">Reject</button>
        </div>
    </form>
</dialog>
<dialog class="logout-confirm-dialog admin-record-save-confirm-dialog" id="admin-action-confirm-dialog" aria-labelledby="admin-action-confirm-title" aria-describedby="admin-action-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-circle-question" aria-hidden="true"></i></div>
    <h2 id="admin-action-confirm-title">Approve selected registrations?</h2>
    <p id="admin-action-confirm-description">Only selected requests that are still pending and matched will be approved.</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-admin-action-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-admin-action-confirm>Approve Selected</button>
    </div>
</dialog>
<?php
$pageScripts = [
    'public/assets/js/admin-table-popouts.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-table-popouts.js'),
    'public/assets/js/admin-registration-rejection-confirmation.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-registration-rejection-confirmation.js'),
    'public/assets/js/admin-registration-search.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-registration-search.js'),
    'public/assets/js/admin-action-confirmation.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-action-confirmation.js')
];
require __DIR__ . '/../../app/views/components/footer.php';
?>
