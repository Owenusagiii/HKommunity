<?php

require_once __DIR__ . '/../../middleware/coordinator.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/models/Coordinator.php';
require_once __DIR__ . '/../../app/models/Scholar.php';
require_once __DIR__ . '/../../app/models/DutyRecord.php';
require_once __DIR__ . '/../../app/models/DutySubmission.php';
require_once __DIR__ . '/../../app/models/Notification.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

$coordinator = (new Coordinator($pdo))->findByUserId((int) $_SESSION['user_id']);

if (!$coordinator) {
    http_response_code(403);
    exit('Coordinator profile not found.');
}

$dutyModel = new DutyRecord($pdo);
$scholarModel = new Scholar($pdo);
$submissionModel = new DutySubmission($pdo);
$notifications = new Notification($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    $ok = false;
    $reopenSubmissionId = null;

    if ($action === 'update-duty-submission') {
        if (!isValidCsrfToken(post('csrf_token'))) {
            flash('error', 'Unable to verify your request. Please try again.');
            redirectTo(BASE_URL . 'public/coordinator/duty-records.php');
        }

        $postedSubmissionId = post('submission_id');
        $submissionId = is_string($postedSubmissionId)
            ? filter_var($postedSubmissionId, FILTER_VALIDATE_INT)
            : false;
        $reopenSubmissionId = is_int($submissionId) && $submissionId > 0 ? $submissionId : null;
        $postedDate = post('duty_date');
        $postedType = post('duty_type');
        $postedHours = post('claimed_hours');
        $date = is_string($postedDate) ? trim($postedDate) : '';
        $type = is_string($postedType) ? trim($postedType) : '';
        $rawHours = is_string($postedHours) ? trim($postedHours) : '';
        $dateValue = DateTime::createFromFormat('!Y-m-d', $date);
        $hours = is_numeric($rawHours) ? (float) $rawHours : 0.0;

        if (
            !is_int($submissionId)
            || $submissionId < 1
            || !$dateValue
            || $dateValue->format('Y-m-d') !== $date
            || $type === ''
            || strlen($type) > 100
            || !is_numeric($rawHours)
            || $hours <= 0
            || $hours > 8
        ) {
            flash('error', 'Enter a valid duty date, duty type, and claimed hours (up to 8 per week).');
            $redirectUrl = BASE_URL . 'public/coordinator/duty-records.php';
            if ($action === 'update-duty-submission' && $reopenSubmissionId !== null) {
                $redirectUrl .= '?open-submission=' . $reopenSubmissionId;
            }
            redirectTo($redirectUrl);
        }

        try {
            $ok = $submissionModel->updateForCoordinator(
                $submissionId,
                $coordinator['year_level'],
                $date,
                $type,
                $hours
            );
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
            redirectTo(BASE_URL . 'public/coordinator/duty-records.php');
        } catch (PDOException $exception) {
            error_log('Duty submission update database error: ' . $exception->getMessage());
            flash('error', 'Unable to update this duty submission right now. Please try again.');
            redirectTo(BASE_URL . 'public/coordinator/duty-records.php');
        }

        flash(
            $ok ? 'success' : 'error',
            $ok ? 'Duty submission updated.' : 'This submission is no longer pending or is outside your assigned year level.'
        );
    } elseif ($action === 'review-duty') {
        if (!isValidCsrfToken(post('csrf_token'))) {
            flash('error', 'Unable to verify your request. Please try again.');
            redirectTo(BASE_URL . 'public/coordinator/duty-records.php');
        }

        $decision = post('decision');
        $decision = is_string($decision) ? $decision : '';
        $postedReason = post('rejection_reason');
        $reason = is_string($postedReason) ? trim($postedReason) : '';
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            flash('error', 'Choose whether to approve or reject the submission.');
            redirectTo(BASE_URL . 'public/coordinator/duty-records.php');
        }
        if ($decision === 'rejected' && ($reason === '' || strlen($reason) > 2000)) {
            flash('error', 'Rejection requires a reason of no more than 2000 characters.');
            redirectTo(BASE_URL . 'public/coordinator/duty-records.php');
        }

        try {
            $reviewedSubmission = $submissionModel->review(
                (int) post('submission_id'),
                (int) $coordinator['id'],
                $coordinator['year_level'],
                $decision,
                $decision === 'rejected' ? $reason : null
            );
            $ok = $reviewedSubmission !== null;
        } catch (PDOException $exception) {
            error_log('Duty submission review database error: ' . $exception->getMessage());
            flash('error', 'Unable to review this duty submission right now. Please try again.');
            redirectTo(BASE_URL . 'public/coordinator/duty-records.php');
        }

        if ($ok) {
            $message = $decision === 'approved'
                ? 'Your duty submission was approved. ' . number_format((float) $reviewedSubmission['claimed_hours'], 2) . ' hour(s) were added to your official records.'
                : 'Your duty submission was rejected. Reason: ' . $reason;
            try {
                $notifications->createNotification(
                    (int) $reviewedSubmission['scholar_user_id'],
                    $decision === 'approved' ? 'Duty Submission Approved' : 'Duty Submission Rejected',
                    $message,
                    'duty'
                );
            } catch (PDOException $exception) {
                error_log('Duty submission notification error: ' . $exception->getMessage());
                flash('error', 'The review was saved, but the Scholar notification could not be created.');
                redirectTo(BASE_URL . 'public/coordinator/duty-records.php');
            }
        }

        $successMessage = $decision === 'approved'
            ? 'Submission approved and added to the duty records.'
            : 'Submission rejected and the Scholar was notified.';
        flash($ok ? 'success' : 'error', $ok ? $successMessage : 'Submission is no longer pending or is outside your assigned year level.');
    } else {
        flash('error', 'Unknown duty-record action.');
    }

    $redirectUrl = BASE_URL . 'public/coordinator/duty-records.php';
    if ($action === 'update-duty-submission' && $reopenSubmissionId !== null) {
        $redirectUrl .= '?open-submission=' . $reopenSubmissionId;
    }
    redirectTo($redirectUrl);
}

$submissions = array_values(array_filter(
    $submissionModel->forCoordinatorYear($coordinator['year_level']),
    static fn (array $submission): bool => $submission['status'] !== 'approved'
));
$items = $dutyModel->forCoordinatorYear($coordinator['year_level']);
$scholarDutyRecords = [];
$dutyRecordIds = [];
foreach ($items as $item) {
    $scholarId = (int) $item['scholar_id'];
    $dutyRecordIds[(int) $item['id']] = true;
    if (!isset($scholarDutyRecords[$scholarId])) {
        $scholarDutyRecords[$scholarId] = [
            'scholar' => $item,
            'records' => [],
            'total_hours' => 0.0,
        ];
    }

    $scholarDutyRecords[$scholarId]['records'][] = $item;
    $scholarDutyRecords[$scholarId]['total_hours'] += (float) $item['hours'];
}

$dutyFilesByRecord = [];
if ($dutyRecordIds) {
    $recordPlaceholders = [];
    $recordParams = [];
    foreach (array_keys($dutyRecordIds) as $index => $dutyRecordId) {
        $parameter = 'record_' . $index;
        $recordPlaceholders[] = ':' . $parameter;
        $recordParams[$parameter] = $dutyRecordId;
    }

    $dutyFilesStmt = $pdo->prepare(
        'SELECT dr.id AS duty_record_id, dsf.id, dsf.original_filename, dsf.file_size
         FROM duty_records dr
         JOIN duty_submission_files dsf ON dsf.submission_id = dr.duty_submission_id
         WHERE dr.id IN (' . implode(', ', $recordPlaceholders) . ')
         ORDER BY dsf.id'
    );
    $dutyFilesStmt->execute($recordParams);
    foreach ($dutyFilesStmt->fetchAll(PDO::FETCH_ASSOC) as $dutyFile) {
        $dutyFilesByRecord[(int) $dutyFile['duty_record_id']][] = $dutyFile;
    }
}

$scholars = $scholarModel->search(null, $coordinator['year_level'], null, $coordinator['year_level']);
foreach ($scholars as $scholar) {
    $scholarId = (int) $scholar['id'];
    if (!isset($scholarDutyRecords[$scholarId])) {
        $scholarDutyRecords[$scholarId] = [
            'scholar' => $scholar,
            'records' => [],
            'total_hours' => 0.0,
        ];
    }
}

$pageTitle = 'Duty Records';
require __DIR__ . '/../../app/views/components/header.php';
?>
<main>
    <section class="duty-records-content">
        <div class="page-head duty-page-head">
            <div>
                <h1>Duty Records</h1>
            </div>
            <div class="duty-info-banner coordinator-duty-info-banner">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>Approved submissions become official duty records and count toward the correct renewal period.</span>
            </div>
        </div>

        <?php require __DIR__ . '/../../app/views/components/flash-message.php'; ?>

        <section class="records-card coordinator-duty-card coordinator-duty-submissions-card" aria-labelledby="duty-submissions-title">
            <div class="records-head">
                <div>
                    <h2 id="duty-submissions-title">Scholar Duty Submissions</h2>
                    <p class="sub-small">Review proof submitted by Scholars assigned to your year level.</p>
                </div>
                <span class="record-count"><?= e(count(array_filter(
                    $submissions,
                    static fn (array $submission): bool => $submission['status'] === 'pending'
                        && (float) $submission['required_duty_hours'] > 0
                ))) ?> pending</span>
            </div>
            <div class="table-scroll coordinator-duty-submissions-scroll">
                <table class="data-table duty-records-table">
                    <thead>
                        <tr>
                            <th>Scholar</th>
                            <th>Duty Date</th>
                            <th>Duty Type</th>
                            <th>Claimed Hours</th>
                            <th>Period</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $submission): ?>
                            <tr
                                class="coordinator-popout-row"
                                tabindex="0"
                                data-popout-dialog="duty-submission-dialog-<?= e($submission['id']) ?>"
                                aria-haspopup="dialog"
                                aria-controls="duty-submission-dialog-<?= e($submission['id']) ?>"
                            >
                                <td class="coordinator-duty-scholar-cell">
                                    <strong><?= e(nameCaps($submission)) ?></strong>
                                    <small><?= e($submission['student_id_number']) ?></small>
                                </td>
                                <td><?= e($submission['duty_date']) ?></td>
                                <td><?= e($submission['duty_type']) ?></td>
                                <td><?= e(number_format((float) $submission['claimed_hours'], 2)) ?></td>
                                <td>
                                    <?= e($submission['academic_year'] . ' - ' . $submission['semester']) ?>
                                    <?php if ((float) $submission['required_duty_hours'] <= 0): ?>
                                        <small class="duty-submission-locked-label">Duty transactions locked</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$submissions): ?>
                            <tr><td colspan="5">No unapproved Scholar duty submissions.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php foreach ($submissions as $submission): ?>
                <dialog class="coordinator-record-dialog scholar-report-dialog coordinator-duty-review-drawer" id="duty-submission-dialog-<?= e($submission['id']) ?>" aria-labelledby="duty-submission-dialog-title-<?= e($submission['id']) ?>">
                    <div class="scholar-report-dialog-head">
                        <div>
                            <span class="scholar-report-dialog-eyebrow">Scholar duty submission</span>
                            <h2 id="duty-submission-dialog-title-<?= e($submission['id']) ?>"><?= e(nameCaps($submission)) ?></h2>
                            <span class="coordinator-dialog-student-id"><?= e($submission['student_id_number']) ?></span>
                        </div>
                        <button type="button" class="scholar-report-dialog-close" aria-label="Close duty submission details">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="scholar-report-dialog-content">
                        <section class="duty-submission-review-card">
                            <h3>Duty submission details</h3>
                            <p class="duty-submission-review-note">Review the submitted duty information and proof files below.</p>
                            <div class="duty-submission-review-layout">
                                <div class="duty-submission-edit-summary duty-submission-review-summary">
                                    <?php if ($submission['status'] === 'pending' && (float) $submission['required_duty_hours'] > 0): ?>
                                        <form method="post" id="coordinator-duty-submission-edit-form-<?= e($submission['id']) ?>" class="duty-submission-edit-fields coordinator-duty-submission-edit-fields" data-confirm-duty-submission-update>
                                            <input type="hidden" name="action" value="update-duty-submission">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="submission_id" value="<?= e($submission['id']) ?>">
                                            <div class="form-row">
                                                <label for="coordinator-duty-date-<?= e($submission['id']) ?>">Duty date</label>
                                                <input id="coordinator-duty-date-<?= e($submission['id']) ?>" type="date" name="duty_date" value="<?= e($submission['duty_date']) ?>" required>
                                            </div>
                                            <div class="form-row">
                                                <label for="coordinator-duty-type-<?= e($submission['id']) ?>">Duty type</label>
                                                <input id="coordinator-duty-type-<?= e($submission['id']) ?>" type="text" name="duty_type" maxlength="100" value="<?= e($submission['duty_type']) ?>" required>
                                            </div>
                                            <div class="form-row">
                                                <label for="coordinator-duty-hours-<?= e($submission['id']) ?>">Claimed hours</label>
                                                <input id="coordinator-duty-hours-<?= e($submission['id']) ?>" type="number" name="claimed_hours" min="0.01" max="8" step="0.01" value="<?= e(number_format((float) $submission['claimed_hours'], 2, '.', '')) ?>" required>
                                                <small>Weekly total cannot exceed 8 hours, Monday through Sunday.</small>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <div>
                                            <span class="duty-submission-detail-label">Duty date</span>
                                            <p><?= e($submission['duty_date']) ?></p>
                                        </div>
                                        <div>
                                            <span class="duty-submission-detail-label">Duty type</span>
                                            <p><?= e($submission['duty_type']) ?></p>
                                        </div>
                                        <div>
                                            <span class="duty-submission-detail-label">Claimed hours</span>
                                            <p><?= e(number_format((float) $submission['claimed_hours'], 2)) ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="duty-submission-edit-proof-column duty-submission-review-proof-column duty-submission-review-proof-card">
                                    <span class="duty-submission-edit-section-label">Proof Files</span>
                                    <div class="duty-proof-dropzone duty-proof-dropzone-compact duty-submission-edit-dropzone duty-proof-dropzone-locked">
                                    <?php if ($submission['files']): ?>
                                        <ul class="duty-proof-file-list duty-submission-proof-list duty-submission-proof-list-in-dropzone" aria-label="Proof files">
                                            <?php foreach ($submission['files'] as $file): ?>
                                                <li>
                                                    <i class="fa-regular fa-file-lines duty-proof-file-icon" aria-hidden="true"></i>
                                                    <a class="duty-proof-file-name" href="<?= BASE_URL ?>public/duty-proof.php?id=<?= e($file['id']) ?>" target="_blank" rel="noopener noreferrer"><?= e($file['original_filename']) ?></a>
                                                    <small><?= e(number_format((int) $file['file_size'] / (1024 * 1024), 2)) ?> MB</small>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="duty-submission-no-proof">No proof file was attached.</p>
                                    <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php if ($submission['status'] === 'pending' && (float) $submission['required_duty_hours'] <= 0): ?>
                                <div class="scholar-period-read-only" role="status">
                                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                    No duty hours are required for this period. Editing and reviewing this submission are locked.
                                </div>
                            <?php endif; ?>
                        </section>

                        <?php if ($submission['status'] === 'pending' && (float) $submission['required_duty_hours'] > 0): ?>
                            <div class="duty-submission-dialog-actions duty-submission-review-actions">
                                <button type="submit" form="coordinator-duty-submission-edit-form-<?= e($submission['id']) ?>" class="duty-submission-save-button duty-submission-update-button">
                                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                                    Save changes
                                </button>
                                <form method="post" class="duty-review-approve-form" data-confirm-duty-approval>
                                    <input type="hidden" name="action" value="review-duty">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="submission_id" value="<?= e($submission['id']) ?>">
                                    <input type="hidden" name="decision" value="approved">
                                    <button type="submit" class="duty-submission-save-button">
                                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                                        Approve
                                    </button>
                                </form>
                                <button
                                    type="button"
                                    class="duty-submission-delete-button duty-review-reject-button"
                                    data-duty-reject-trigger
                                    data-submission-id="<?= e($submission['id']) ?>"
                                >
                                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                    Reject
                                </button>
                            </div>
                        <?php endif; ?>

                        <?php if ($submission['status'] !== 'pending'): ?>
                            <section class="scholar-report-detail-section">
                                <h3>Decision details</h3>
                                <?php if ($submission['status'] === 'rejected' && $submission['rejection_reason']): ?>
                                <p><?= e($submission['rejection_reason']) ?></p>
                                <?php else: ?>
                                <p>Reviewed <?= e((new DateTime($submission['reviewed_at']))->format('M j, Y')) ?></p>
                                <?php endif; ?>
                            </section>
                        <?php endif; ?>
                    </div>
                </dialog>
            <?php endforeach; ?>
        </section>

        <section class="records-card coordinator-duty-card" aria-labelledby="duty-records-title">
            <div class="records-head">
                <div>
                    <h2 id="duty-records-title">Duty Records</h2>
                    <p class="sub-small">All scholars and their duty records in your assigned year level</p>
                </div>
                <div class="coordinator-duty-tools">
                    <span class="coordinator-toolbar-count"><?= e(count($scholarDutyRecords)) ?> scholars</span>
                    <label class="visually-hidden" for="duty-record-hours-sort">Sort by rendered hours</label>
                    <select id="duty-record-hours-sort" class="coordinator-promotion-filter" aria-label="Sort by rendered hours" <?= !$scholarDutyRecords ? 'disabled' : '' ?>>
                        <option value="default">Default</option>
                        <option value="highest">Highest</option>
                        <option value="lowest">Lowest</option>
                    </select>
                    <label class="requirement-search">
                        <span class="visually-hidden">Search duty records by student ID number</span>
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" id="duty-record-search" placeholder="Search by student ID" autocomplete="off" aria-controls="duty-records-table" <?= !$scholarDutyRecords ? 'disabled' : '' ?>>
                    </label>
                </div>
            </div>

            <div class="table-scroll coordinator-duty-table-scroll">
                <table class="data-table duty-records-table" id="duty-records-table">
                    <thead>
                        <tr>
                            <th>Scholar</th>
                            <th>Duty Records</th>
                            <th>Total Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scholarDutyRecords as $scholarId => $scholarRecordGroup): ?>
                            <?php $scholar = $scholarRecordGroup['scholar']; ?>
                            <tr
                                class="coordinator-popout-row"
                                tabindex="0"
                                data-student-id="<?= e($scholar['student_id_number']) ?>"
                                data-popout-dialog="scholar-duty-dialog-<?= e($scholarId) ?>"
                                aria-haspopup="dialog"
                                aria-controls="scholar-duty-dialog-<?= e($scholarId) ?>"
                                data-rendered-hours="<?= e($scholarRecordGroup['total_hours']) ?>"
                                data-last-name="<?= e($scholar['last_name']) ?>"
                            >
                                <td class="coordinator-duty-scholar-cell">
                                    <strong><?= e(nameCaps($scholar)) ?></strong>
                                    <small><?= e($scholar['student_id_number']) ?></small>
                                </td>
                                <td><?= e(count($scholarRecordGroup['records'])) ?></td>
                                <td><?= e(number_format($scholarRecordGroup['total_hours'], 2)) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$scholarDutyRecords): ?>
                            <tr><td colspan="3">No scholars found.</td></tr>
                        <?php else: ?>
                            <tr class="duty-search-empty" hidden>
                                <td colspan="3">No scholars match that student ID.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php foreach ($scholarDutyRecords as $scholarId => $scholarRecordGroup): ?>
                <?php $scholar = $scholarRecordGroup['scholar']; ?>
                <?php
                $scholarPeriods = [];
                foreach ($scholarRecordGroup['records'] as $periodRecord) {
                    $scholarPeriods[(int) $periodRecord['renewal_period_id']] =
                        $periodRecord['academic_year'] . ' - ' . $periodRecord['semester'];
                }
                ?>
                <dialog class="coordinator-record-dialog scholar-report-dialog coordinator-duty-review-drawer coordinator-fill-table-drawer" id="scholar-duty-dialog-<?= e($scholarId) ?>" aria-labelledby="scholar-duty-dialog-title-<?= e($scholarId) ?>">
                    <div class="scholar-report-dialog-head">
                        <div>
                            <span class="scholar-report-dialog-eyebrow">Scholar duty records</span>
                            <h2 id="scholar-duty-dialog-title-<?= e($scholarId) ?>"><?= e(nameCaps($scholar)) ?></h2>
                            <span class="coordinator-dialog-student-id"><?= e($scholar['student_id_number']) ?></span>
                        </div>
                        <button type="button" class="scholar-report-dialog-close" aria-label="Close scholar duty records">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="scholar-report-dialog-content coordinator-scholar-duty-content">
                        <section class="duty-submission-review-card coordinator-scholar-duty-card">
                            <div class="coordinator-scholar-duty-card-head">
                                <div>
                                    <h3>Duty records</h3>
                                    <p class="duty-submission-review-note">
                                        <?= e(count($scholarRecordGroup['records'])) ?> record<?= count($scholarRecordGroup['records']) === 1 ? '' : 's' ?>
                                        &middot; <?= e(number_format($scholarRecordGroup['total_hours'], 2)) ?> total hours
                                    </p>
                                </div>
                                <div class="coordinator-scholar-duty-filters">
                                    <label class="duty-record-filter coordinator-scholar-duty-date-filter">
                                        <span>Date</span>
                                        <select id="scholar-duty-date-filter-<?= e($scholarId) ?>" data-scholar-duty-date-filter aria-label="Filter duty records by recorded date">
                                            <option value="all">All</option>
                                            <option value="latest">Latest</option>
                                            <option value="last-week">Last week</option>
                                            <option value="last-month">Last month</option>
                                        </select>
                                    </label>
                                    <label class="duty-record-filter coordinator-scholar-duty-period-filter">
                                        <span>Period</span>
                                        <select id="scholar-duty-period-filter-<?= e($scholarId) ?>" data-scholar-duty-period-filter aria-label="Filter duty records by period">
                                            <option value="all">All</option>
                                            <?php foreach ($scholarPeriods as $periodId => $periodLabel): ?>
                                                <option value="<?= e($periodId) ?>"><?= e($periodLabel) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>
                            </div>
                            <div class="table-scroll coordinator-scholar-duty-table-scroll">
                                <table class="data-table coordinator-scholar-duty-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Duty type</th>
                                            <th>Hours</th>
                                            <th>Period</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$scholarRecordGroup['records']): ?>
                                            <tr>
                                                <td colspan="4">No duty records submitted yet.</td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php foreach ($scholarRecordGroup['records'] as $item): ?>
                                            <?php $dutyFiles = $dutyFilesByRecord[(int) $item['id']] ?? []; ?>
                                            <tr
                                                class="coordinator-duty-file-row"
                                                tabindex="0"
                                                data-duty-files-expand
                                                aria-expanded="false"
                                                aria-controls="coordinator-duty-files-<?= e($item['id']) ?>"
                                                data-recorded-date="<?= e((new DateTime($item['created_at']))->format('Y-m-d')) ?>"
                                                data-period-id="<?= e($item['renewal_period_id']) ?>"
                                            >
                                                <td>
                                                    <?= e($item['duty_date']) ?>
                                                    <small class="coordinator-scholar-duty-record-meta">
                                                        Recorded <?= e((new DateTime($item['created_at']))->format('M j, Y g:i A')) ?>
                                                        <?php if ($item['updated_at']): ?>
                                                            <br>Updated <?= e((new DateTime($item['updated_at']))->format('M j, Y g:i A')) ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </td>
                                                <td><?= e($item['duty_type']) ?></td>
                                                <td><?= e(number_format((float) $item['hours'], 2)) ?></td>
                                                <td><?= e($item['academic_year'] . ' - ' . $item['semester']) ?></td>
                                            </tr>
                                            <tr
                                                class="coordinator-assigned-requirement-details admin-management-duty-details"
                                                id="coordinator-duty-files-<?= e($item['id']) ?>"
                                                hidden
                                            >
                                                <td colspan="4">
                                                    <div class="coordinator-requirement-expanded-content">
                                                        <section>
                                                            <h4>Uploaded Files</h4>
                                                            <?php if ($dutyFiles): ?>
                                                                <ul class="duty-proof-file-list admin-management-duty-file-list" aria-label="Files uploaded for this duty record">
                                                                    <?php foreach ($dutyFiles as $dutyFile): ?>
                                                                        <li>
                                                                            <i class="fa-regular fa-file-lines duty-proof-file-icon" aria-hidden="true"></i>
                                                                            <a class="duty-proof-file-name" href="<?= BASE_URL ?>public/duty-proof.php?id=<?= e($dutyFile['id']) ?>" target="_blank" rel="noopener noreferrer"><?= e($dutyFile['original_filename']) ?></a>
                                                                            <small><?= e(number_format((int) $dutyFile['file_size'] / (1024 * 1024), 2)) ?> MB</small>
                                                                        </li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            <?php else: ?>
                                                                <p class="admin-management-duty-no-files">No files were uploaded for this duty record.</p>
                                                            <?php endif; ?>
                                                        </section>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="coordinator-scholar-duty-no-matches" hidden>
                                            <td colspan="4">No duty records match these filters.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </dialog>
            <?php endforeach; ?>
        </section>
    </section>
</main>
<dialog class="logout-confirm-dialog duty-rejection-confirm-dialog" id="duty-rejection-confirm-dialog" aria-labelledby="duty-rejection-confirm-title">
    <div class="logout-confirm-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div>
    <h2 id="duty-rejection-confirm-title">Reject duty submission?</h2>
    <p>Enter a reason for rejecting this submission. The Scholar will be notified.</p>
    <form method="post" id="duty-rejection-confirm-form">
        <input type="hidden" name="action" value="review-duty">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="submission_id" id="duty-rejection-submission-id">
        <input type="hidden" name="decision" value="rejected">
        <label for="duty-rejection-reason">Reason for rejection</label>
        <textarea id="duty-rejection-reason" name="rejection_reason" maxlength="2000" placeholder="Enter reason for rejection" required></textarea>
        <div class="logout-confirm-actions">
            <button type="button" class="logout-confirm-cancel" data-duty-reject-cancel>Cancel</button>
            <button type="submit" class="logout-confirm-submit">Confirm Rejection</button>
        </div>
    </form>
</dialog>
<dialog class="logout-confirm-dialog duty-approval-confirm-dialog" id="duty-approval-confirm-dialog" aria-labelledby="duty-approval-confirm-title" aria-describedby="duty-approval-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-circle-question" aria-hidden="true"></i></div>
    <h2 id="duty-approval-confirm-title">Want to check again?</h2>
    <p id="duty-approval-confirm-description">Make sure you have checked the submission details and proof before approving.</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-duty-approve-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-duty-approval-confirm>Confirm Approval</button>
    </div>
</dialog>
<dialog class="logout-confirm-dialog duty-submission-update-confirm-dialog" id="duty-submission-update-confirm-dialog" aria-labelledby="duty-submission-update-confirm-title" aria-describedby="duty-submission-update-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-circle-question" aria-hidden="true"></i></div>
    <h2 id="duty-submission-update-confirm-title">Save these changes?</h2>
    <p id="duty-submission-update-confirm-description">Confirm that you want to update the duty date, type, and claimed hours for this submission.</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-duty-submission-update-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-duty-submission-update-confirm>Confirm Changes</button>
    </div>
</dialog>
<?php
$pageScripts = [
    'public/assets/js/coordinator-table-popouts.js?v=' . filemtime(__DIR__ . '/../assets/js/coordinator-table-popouts.js'),
    'public/assets/js/duty-records-search.js',
];
require __DIR__ . '/../../app/views/components/footer.php';
?>
