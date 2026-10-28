<?php

require_once __DIR__ . '/../../middleware/scholar.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/models/Scholar.php';
require_once __DIR__ . '/../../app/models/DutySubmission.php';
require_once __DIR__ . '/../../app/models/RenewalPeriod.php';
require_once __DIR__ . '/../../app/models/ScholarPeriodContext.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

$scholar = (new Scholar($pdo))->findByUserId((int) $_SESSION['user_id']);
if (!$scholar) {
    http_response_code(403);
    exit('Scholar profile not found.');
}

$submissionModel = new DutySubmission($pdo);
$periodModel = new RenewalPeriod($pdo);
$scholarPeriodContext = ScholarPeriodContext::resolve($pdo, $scholar);
$selectedPeriod = $scholarPeriodContext['selected'];
$dutyTransactionsLocked = $selectedPeriod !== null
    && (float) ($selectedPeriod['required_duty_hours'] ?? 0) <= 0;
$availableWorkPeriod = $periodModel->findWorkPeriodForScholar((int) $scholar['id'], $scholar['year_level']);
$workPeriod = $scholarPeriodContext['can_transact']
    && $availableWorkPeriod
    && $selectedPeriod
    && (int) $availableWorkPeriod['id'] === (int) $selectedPeriod['id']
    && (float) ($availableWorkPeriod['required_duty_hours'] ?? 0) > 0
        ? $availableWorkPeriod
        : null;
$uploadDirectory = __DIR__ . '/../../uploads/duty-submissions';
$storedPaths = [];
$storeProofFiles = static function (mixed $fileInput, bool $required) use ($uploadDirectory, &$storedPaths): array {
    if (!$required && $fileInput === null) {
        return [];
    }

    if (
        !is_array($fileInput)
        || !isset($fileInput['name'], $fileInput['tmp_name'], $fileInput['error'], $fileInput['size'])
        || !is_array($fileInput['name'])
        || !is_array($fileInput['tmp_name'])
        || !is_array($fileInput['error'])
        || !is_array($fileInput['size'])
        || count($fileInput['name']) !== count($fileInput['tmp_name'])
        || count($fileInput['name']) !== count($fileInput['error'])
        || count($fileInput['name']) !== count($fileInput['size'])
    ) {
        throw new RuntimeException($required ? 'Upload between 1 and 5 proof files.' : 'The proof-file upload could not be processed.');
    }

    $indexes = [];
    foreach ($fileInput['name'] as $index => $filename) {
        $uploadError = (int) $fileInput['error'][$index];
        if ($uploadError === UPLOAD_ERR_NO_FILE && !$required) {
            continue;
        }
        if ($uploadError !== UPLOAD_ERR_OK) {
            throw new RuntimeException($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE
                ? 'Each proof file must be no larger than 5 MB.'
                : 'A proof file could not be uploaded. Please choose the files again.');
        }
        $indexes[] = $index;
    }

    if (($required && count($indexes) < 1) || count($indexes) > 5) {
        throw new RuntimeException('Upload between 1 and 5 proof files.');
    }
    if (!$indexes) {
        return [];
    }

    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('The proof upload directory is unavailable.');
    }

    $allowedTypes = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $files = [];
    foreach ($indexes as $index) {
        $temporaryPath = (string) $fileInput['tmp_name'][$index];
        $fileSize = (int) $fileInput['size'][$index];
        if ($fileSize < 1 || $fileSize > 5 * 1024 * 1024 || !is_uploaded_file($temporaryPath)) {
            throw new RuntimeException('Each proof file must be no larger than 5 MB.');
        }

        $mimeType = $finfo->file($temporaryPath);
        if (!is_string($mimeType) || !isset($allowedTypes[$mimeType])) {
            throw new RuntimeException('Proof files must be PDF, JPG, or PNG.');
        }

        $originalFilename = basename(str_replace('\\', '/', (string) $fileInput['name'][$index]));
        $originalFilename = trim((string) preg_replace('/[\x00-\x1F\x7F]/', '', $originalFilename));
        if ($originalFilename === '') {
            $originalFilename = 'proof.' . $allowedTypes[$mimeType];
        }
        $originalFilename = substr($originalFilename, 0, 255);
        $storedFilename = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
        $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $storedFilename;
        if (!move_uploaded_file($temporaryPath, $destination)) {
            throw new RuntimeException('Unable to store a proof file. Please try again.');
        }

        $storedPaths[] = $destination;
        $files[] = [
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
        ];
    }

    return $files;
};
$cleanupUncommittedFiles = static function () use (&$storedPaths): void {
    foreach ($storedPaths as $storedPath) {
        if (is_file($storedPath) && !unlink($storedPath)) {
            error_log('Unable to remove an uncommitted duty proof file: ' . $storedPath);
        }
    }
    $storedPaths = [];
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isValidCsrfToken(post('csrf_token'))) {
        flash('error', 'Unable to verify your request. Please try again.');
        redirectTo(BASE_URL . 'public/scholar/duty-submission.php');
    }

    $action = post('action');
    if (
        in_array($action, ['update-duty-submission', 'delete-duty-submission', 'submit-duty'], true)
        && !$scholarPeriodContext['can_transact']
    ) {
        flash('error', 'Previous-period transactions are locked and can only be viewed.');
        redirectTo(BASE_URL . 'public/scholar/duty-submission.php');
    }
    if (
        in_array($action, ['update-duty-submission', 'delete-duty-submission', 'submit-duty'], true)
        && $dutyTransactionsLocked
    ) {
        flash('error', 'Duty transactions are locked because no duty hours are required for this period.');
        redirectTo(BASE_URL . 'public/scholar/duty-submission.php');
    }

    if ($action === 'update-duty-submission') {
        $postedSubmissionId = post('submission_id');
        $submissionId = is_string($postedSubmissionId)
            ? filter_var($postedSubmissionId, FILTER_VALIDATE_INT)
            : false;
        $postedDate = post('duty_date', null);
        $postedType = post('duty_type', null);
        $postedHours = post('claimed_hours', null);
        $postedRemoveFileIds = post('remove_file_ids', null);
        $date = is_string($postedDate) ? trim($postedDate) : null;
        $type = is_string($postedType) ? trim($postedType) : null;
        $rawHours = is_string($postedHours) ? trim($postedHours) : null;
        $removeFileIds = [];
        $invalidRemoveFileIds = $postedRemoveFileIds !== null && !is_array($postedRemoveFileIds);
        if (is_array($postedRemoveFileIds)) {
            foreach ($postedRemoveFileIds as $postedFileId) {
                $fileId = is_string($postedFileId)
                    ? filter_var($postedFileId, FILTER_VALIDATE_INT)
                    : false;
                if (!is_int($fileId) || $fileId < 1) {
                    $invalidRemoveFileIds = true;
                    break;
                }
                $removeFileIds[] = $fileId;
            }
        }
        $dateValue = $date !== null ? DateTime::createFromFormat('!Y-m-d', $date) : null;
        $hours = $rawHours !== null && is_numeric($rawHours) ? (float) $rawHours : null;

        if (
            !is_int($submissionId)
            || $submissionId < 1
            || ($date !== null && (!$dateValue || $dateValue->format('Y-m-d') !== $date))
            || ($type !== null && ($type === '' || strlen($type) > 100))
            || ($rawHours !== null && (!is_numeric($rawHours) || $hours === null || $hours <= 0 || $hours > 8))
            || $invalidRemoveFileIds
        ) {
            flash('error', 'Enter valid values for any duty fields you changed.');
            redirectTo(BASE_URL . 'public/scholar/duty-submission.php');
        }

        try {
            $files = $storeProofFiles($_FILES['proof_files'] ?? null, false);
            $deletedStoredFiles = $submissionModel->updateForScholar(
                $submissionId,
                (int) $scholar['id'],
                $date,
                $type,
                $hours,
                $files,
                $removeFileIds
            );

            if ($deletedStoredFiles === null) {
                $cleanupUncommittedFiles();
                flash('error', 'This submission can no longer be edited, or it must have between 1 and 5 proof files.');
            } else {
                $storedPaths = [];
                $fileCleanupFailed = false;
                foreach ($deletedStoredFiles as $storedFilename) {
                    if (!is_string($storedFilename) || !preg_match('/^[a-f0-9]{32}\.(pdf|jpg|png)$/', $storedFilename)) {
                        error_log('Skipped deleting a duty proof file with an unexpected stored filename.');
                        $fileCleanupFailed = true;
                        continue;
                    }

                    $storedPath = $uploadDirectory . DIRECTORY_SEPARATOR . $storedFilename;
                    if (is_file($storedPath) && !unlink($storedPath)) {
                        error_log('Unable to remove deleted duty proof file: ' . $storedPath);
                        $fileCleanupFailed = true;
                    }
                }

                flash(
                    $fileCleanupFailed ? 'error' : 'success',
                    $fileCleanupFailed
                        ? 'Your duty submission was updated, but one or more removed proof files could not be deleted from storage. Please contact support.'
                        : 'Your duty submission was updated and sent for Coordinator review.'
                );
            }
        } catch (RuntimeException $exception) {
            $cleanupUncommittedFiles();
            flash('error', $exception->getMessage());
        } catch (PDOException $exception) {
            $cleanupUncommittedFiles();
            error_log('Duty submission update database error: ' . $exception->getMessage());
            flash('error', 'Unable to update this duty submission right now. Please try again.');
        }

        redirectTo(BASE_URL . 'public/scholar/duty-submission.php');
    }

    if ($action === 'delete-duty-submission') {
        $postedSubmissionId = post('submission_id');
        $submissionId = is_string($postedSubmissionId)
            ? filter_var($postedSubmissionId, FILTER_VALIDATE_INT)
            : false;
        if (!is_int($submissionId) || $submissionId < 1) {
            flash('error', 'The selected duty submission is invalid.');
            redirectTo(BASE_URL . 'public/scholar/duty-submission.php');
        }

        try {
            $storedFiles = $submissionModel->deleteForScholar($submissionId, (int) $scholar['id']);
            if ($storedFiles === null) {
                flash('error', 'This submission cannot be deleted because it has already been approved or does not belong to your account.');
                redirectTo(BASE_URL . 'public/scholar/duty-submission.php');
            }

            $uploadDirectory = __DIR__ . '/../../uploads/duty-submissions';
            $fileCleanupFailed = false;
            foreach ($storedFiles as $storedFilename) {
                if (!is_string($storedFilename) || !preg_match('/^[a-f0-9]{32}\.(pdf|jpg|png)$/', $storedFilename)) {
                    error_log('Skipped deleting a duty proof file with an unexpected stored filename.');
                    $fileCleanupFailed = true;
                    continue;
                }

                $storedPath = $uploadDirectory . DIRECTORY_SEPARATOR . $storedFilename;
                if (is_file($storedPath) && !unlink($storedPath)) {
                    error_log('Unable to remove deleted duty proof file: ' . $storedPath);
                    $fileCleanupFailed = true;
                }
            }

            if ($fileCleanupFailed) {
                flash('error', 'Your submission was deleted, but one or more proof files could not be removed. Please contact support.');
            } else {
                flash('success', 'Your duty submission was deleted.');
            }
        } catch (PDOException $exception) {
            error_log('Duty submission deletion database error: ' . $exception->getMessage());
            flash('error', 'Unable to delete this duty submission right now. Please try again.');
        }

        redirectTo(BASE_URL . 'public/scholar/duty-submission.php');
    }

    if ($action !== 'submit-duty') {
        flash('error', 'Unable to process your request. Please try again.');
        redirectTo(BASE_URL . 'public/scholar/duty-submission.php');
    }

    $postedDate = post('duty_date');
    $postedType = post('duty_type');
    $postedHours = post('claimed_hours');
    $date = is_string($postedDate) ? trim($postedDate) : '';
    $type = is_string($postedType) ? trim($postedType) : '';
    $rawHours = is_string($postedHours) ? trim($postedHours) : '';
    $dateValue = DateTime::createFromFormat('!Y-m-d', $date);
    $hours = is_numeric($rawHours) ? (float) $rawHours : 0.0;
    try {
        if (!$workPeriod) {
            throw new RuntimeException('There is no renewal work period available for your account.');
        }
        if (
            !$dateValue
            || $dateValue->format('Y-m-d') !== $date
            || $type === ''
            || strlen($type) > 100
            || $hours <= 0
            || $hours > 8
            || !is_numeric($rawHours)
        ) {
            throw new RuntimeException('Enter a valid duty date, duty type, and claimed hours (up to 8 per week).');
        }
        $files = $storeProofFiles($_FILES['proof_files'] ?? null, true);

        $submissionModel->create(
            (int) $scholar['id'],
            (int) $workPeriod['id'],
            $date,
            $type,
            $hours,
            $files
        );
        flash('success', 'Your duty proof was submitted for Coordinator review.');
    } catch (RuntimeException $exception) {
        $cleanupUncommittedFiles();
        flash('error', $exception->getMessage());
    } catch (PDOException $exception) {
        $cleanupUncommittedFiles();
        error_log('Duty submission database error: ' . $exception->getMessage());
        flash('error', 'Unable to submit duty proof right now. Please try again.');
    }

    redirectTo(BASE_URL . 'public/scholar/duty-submission.php');
}

$submissions = $selectedPeriod
    ? $submissionModel->forScholar((int) $scholar['id'], (int) $selectedPeriod['id'])
    : [];
$pageTitle = 'Submit Weekly Duty';
$pageClass = 'scholar-full-height-page';
require __DIR__ . '/../../app/views/components/header.php';
?>
<main>
    <section class="duty-records-content">
        <div class="page-head duty-page-head">
            <div>
                <h1><?= $scholarPeriodContext['can_transact'] && !$dutyTransactionsLocked ? 'Submit Weekly Duty' : 'Duty Submission History' ?></h1>
            </div>
        </div>

        <?php require __DIR__ . '/../../app/views/components/flash-message.php'; ?>
        <?php if ($selectedPeriod && !$scholarPeriodContext['can_transact']): ?>
            <div class="scholar-period-read-only" role="status">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                Viewing <?= e($selectedPeriod['academic_year'] . ' · ' . $selectedPeriod['semester']) ?> history. Duty submissions and proof files are locked.
            </div>
        <?php elseif ($dutyTransactionsLocked): ?>
            <div class="scholar-period-read-only" role="status">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                No duty hours are required for this period. Duty submissions and proof files are locked.
            </div>
        <?php endif; ?>

        <section class="records-card duty-submission-card" aria-labelledby="submit-duty-title">
            <div class="records-head">
                <div>
                    <h2 id="submit-duty-title"><?= $scholarPeriodContext['can_transact'] && !$dutyTransactionsLocked ? 'Submit Duty Proof' : 'Period Transactions' ?></h2>
                    <p class="sub-small"><?= $scholarPeriodContext['can_transact'] && !$dutyTransactionsLocked
                        ? 'PDF, JPG, or PNG; up to 5 files, 5 MB each.'
                        : 'This period is closed. Existing transactions are shown below as read-only.' ?></p>
                </div>
               <a class="duty-weekly-submit-button duty-back-button" href="<?= BASE_URL ?>public/scholar/duty-records.php">
                   <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                   Back to Duty Hours
               </a>
            </div>
            <?php if ($workPeriod): ?>
                <form method="post" enctype="multipart/form-data" class="duty-submission-form" data-confirm-duty-submit>
                    <input type="hidden" name="action" value="submit-duty">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <div class="duty-submission-form-grid">
                        <div class="duty-submission-details">
                            <div class="form-row">
                                <label for="submission-duty-date">Duty Date</label>
                                <input id="submission-duty-date" type="date" name="duty_date" aria-describedby="submission-duty-date-hint" required>
                                <small id="submission-duty-date-hint">Choose the date you completed this duty.</small>
                            </div>
                            <div class="form-row">
                                <label for="submission-duty-type">Duty Type</label>
                                <input id="submission-duty-type" type="text" name="duty_type" maxlength="100" placeholder="e.g. Office assistance" aria-describedby="submission-duty-type-hint" required>
                                <small id="submission-duty-type-hint">Briefly describe the work you performed.</small>
                            </div>
                            <div class="form-row">
                                <label for="submission-claimed-hours">Claimed Hours</label>
                                <input id="submission-claimed-hours" type="number" name="claimed_hours" min="0.01" max="8" step="0.01" placeholder="e.g. 3.00" aria-describedby="submission-claimed-hours-hint" required>
                                <small id="submission-claimed-hours-hint">Maximum 8 claimed hours per Monday–Sunday week, across all submissions.</small>
                            </div>
                        </div>
                        <div class="form-row duty-submission-files-field">
                            <label for="submission-proof-files">Proof files</label>
                            <div class="duty-proof-dropzone" data-proof-dropzone data-max-files="5">
                                <input id="submission-proof-files" class="duty-proof-file-input" type="file" name="proof_files[]" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" aria-describedby="submission-proof-files-instructions submission-proof-files-list" multiple required>
                                <label class="duty-proof-dropzone-label" for="submission-proof-files">
                                    <span class="duty-proof-dropzone-icon" aria-hidden="true"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                                    <span class="duty-proof-dropzone-title">Drag and drop your files here</span>
                                    <span class="duty-proof-dropzone-prompt">or <span>browse files</span> from your device</span>
                                    <span id="submission-proof-files-instructions" class="duty-proof-dropzone-limit">PDF, JPG, or PNG · Up to 5 files · 5 MB each</span>
                                </label>
                                <ul id="submission-proof-files-list" class="duty-proof-file-list" aria-live="polite" hidden></ul>
                                <p class="duty-proof-file-error" data-proof-file-error role="alert" hidden></p>
                            </div>
                        </div>
                    </div>
                    <div class="duty-submission-form-actions">
                        <p>Your submission will be sent to your Coordinator for review.</p>
                        <button type="submit">
                            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                            Submit Duty Proof
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <p class="requirement-form-note"><?= $dutyTransactionsLocked
                    ? 'No duty hours are required for this period. Duty submissions and proof files are available for viewing only.'
                    : ($selectedPeriod && !$scholarPeriodContext['can_transact']
                        ? 'This previous period is closed. Duty submissions and proof files are available for viewing only.'
                        : 'No renewal work period is currently available for duty submissions.') ?></p>
            <?php endif; ?>
        </section>

        <section class="records-card duty-submission-card" aria-labelledby="duty-submissions-title">
            <div class="records-head">
                <div>
                    <h2 id="duty-submissions-title">My Duty Submissions</h2>
                    <p class="sub-small">Pending and reviewed submissions. Only approved hours count toward your total.</p>
                </div>
                <div class="duty-records-actions">
                    <label class="duty-record-filter">
                        <span>Show</span>
                        <select id="duty-submission-date-filter" aria-label="Filter duty submissions by date">
                            <option value="all">All</option>
                            <option value="latest">Latest</option>
                            <option value="last-week">Last week</option>
                            <option value="last-month">Last month</option>
                        </select>
                    </label>
                    <span class="record-count" id="duty-submission-count" aria-live="polite"><?= e(count($submissions)) ?> submissions</span>
                </div>
            </div>
            <div class="table-scroll duty-submissions-table-scroll scholar-duty-submissions-scroll">
                <table class="data-table duty-records-table">
                    <thead>
                        <tr>
                            <th>Submitted</th>
                            <th>Duty Date</th>
                            <th>Claimed Hours</th>
                            <th>Period</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $submission): ?>
                            <tr class="data-row duty-submission-row" tabindex="0" data-duty-submission-row data-duty-date="<?= e($submission['duty_date']) ?>" data-popout-dialog="duty-submission-dialog-<?= e($submission['id']) ?>" aria-haspopup="dialog">
                                <td><?= e((new DateTime($submission['submitted_at']))->format('M j, Y')) ?></td>
                                <td><?= e($submission['duty_date']) ?></td>
                                <td><?= e(number_format((float) $submission['claimed_hours'], 2)) ?></td>
                                <td><?= e($submission['academic_year'] . ' - ' . $submission['semester']) ?></td>
                                <td>
                                    <span class="status-pill status-<?= e($submission['status']) ?>"><?= e(ucfirst($submission['status'])) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$submissions): ?>
                            <tr><td colspan="5">You have not submitted any duty proof yet.</td></tr>
                        <?php endif; ?>
                        <?php if ($submissions): ?>
                            <tr id="duty-submission-filter-empty" hidden><td colspan="5">No duty submissions found for this date range.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php foreach ($submissions as $submission): ?>
                <dialog class="duty-submission-dialog" id="duty-submission-dialog-<?= e($submission['id']) ?>" aria-labelledby="duty-submission-dialog-title-<?= e($submission['id']) ?>">
                    <div class="duty-submission-dialog-head">
                        <div>
                            <span class="duty-submission-dialog-eyebrow">Duty submission details</span>
                            <h2 id="duty-submission-dialog-title-<?= e($submission['id']) ?>">Duty on <?= e((new DateTime($submission['duty_date']))->format('M j, Y')) ?></h2>
                        </div>
                        <button class="duty-submission-dialog-close" type="button" aria-label="Close submission details">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                    <?php
                    $isApproved = $submission['status'] === 'approved';
                    $isLocked = $isApproved || !$scholarPeriodContext['can_transact'] || $dutyTransactionsLocked;
                    $editFileInputId = 'edit-submission-proof-files-' . $submission['id'];
                    $remainingFileSlots = max(0, 5 - count($submission['files']));
                    ?>
                    <form id="duty-submission-edit-form-<?= e($submission['id']) ?>" method="post" enctype="multipart/form-data" class="duty-submission-edit-form<?= $isLocked ? ' is-locked' : '' ?>"<?= $isLocked ? ' aria-disabled="true"' : '' ?>>
                            <input type="hidden" name="action" value="update-duty-submission">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="submission_id" value="<?= e($submission['id']) ?>">
                            <h3><?= $isApproved
                                ? 'Approved submission'
                                : ($dutyTransactionsLocked
                                    ? 'Duty transactions locked'
                                    : ($isLocked ? 'Previous-period submission' : 'Edit submission')) ?></h3>
                            <p class="duty-submission-edit-note">
                                <?= $isApproved
                                    ? 'This submission has been approved and is locked from changes and deletion.'
                                    : ($dutyTransactionsLocked
                                        ? 'No duty hours are required for this period. This submission and its proof files are available for viewing only.'
                                        : ($isLocked
                                            ? 'This period has ended. This submission and its proof files are available for viewing only.'
                                            : 'Review the details below. Saving changes sends this submission to your Coordinator for review.')) ?>
                            </p>
                            <div class="duty-submission-edit-summary">
                                <div>
                                    <span class="duty-submission-detail-label">Submitted</span>
                                    <p><?= e((new DateTime($submission['submitted_at']))->format('M j, Y \a\t g:i A')) ?></p>
                                </div>
                                <div>
                                    <span class="duty-submission-detail-label">Period</span>
                                    <p><?= e($submission['academic_year'] . ' - ' . $submission['semester']) ?></p>
                                </div>
                                <div>
                                    <span class="duty-submission-detail-label">Status</span>
                                    <p><span class="status-pill status-<?= e($submission['status']) ?>"><?= e(ucfirst($submission['status'])) ?></span></p>
                                </div>
                            </div>
                            <?php if ($submission['status'] === 'rejected' && $submission['rejection_reason']): ?>
                                <div class="duty-submission-rejection-detail">
                                    <span class="duty-submission-detail-label">Coordinator's Review Note</span>
                                    <p><?= e($submission['rejection_reason']) ?></p>
                                </div>
                            <?php endif; ?>
                            <div class="duty-submission-edit-layout">
                                <div class="duty-submission-edit-fields">
                                    <div class="form-row">
                                        <label for="edit-submission-duty-date-<?= e($submission['id']) ?>">Duty Date</label>
                                        <input id="edit-submission-duty-date-<?= e($submission['id']) ?>" type="date" name="duty_date" value="<?= e($submission['duty_date']) ?>" required <?= $isLocked ? 'disabled' : '' ?>>
                                    </div>
                                    <div class="form-row">
                                        <label for="edit-submission-duty-type-<?= e($submission['id']) ?>">Duty Type</label>
                                        <input id="edit-submission-duty-type-<?= e($submission['id']) ?>" type="text" name="duty_type" maxlength="100" value="<?= e($submission['duty_type']) ?>" required <?= $isLocked ? 'disabled' : '' ?>>
                                    </div>
                                    <div class="form-row">
                                        <label for="edit-submission-claimed-hours-<?= e($submission['id']) ?>">Claimed Hours</label>
                                        <input id="edit-submission-claimed-hours-<?= e($submission['id']) ?>" type="number" name="claimed_hours" min="0.01" max="8" step="0.01" value="<?= e(number_format((float) $submission['claimed_hours'], 2, '.', '')) ?>" required <?= $isLocked ? 'disabled' : '' ?>>
                                        <small>Weekly total cannot exceed 8 hours, Monday through Sunday.</small>
                                    </div>
                                </div>
                                <div class="duty-submission-edit-proof-column">
                                    <span class="duty-submission-edit-section-label">Proof Files</span>
                                    <div class="duty-proof-dropzone duty-proof-dropzone-compact duty-submission-edit-dropzone<?= $isLocked ? ' duty-proof-dropzone-locked' : '' ?>" aria-label="Proof files"<?= $isLocked ? '' : ' data-proof-dropzone data-max-files="5"' ?>>
                                    <?php if (!$isLocked): ?>
                                        <input id="<?= e($editFileInputId) ?>" class="duty-proof-file-input" type="file" name="proof_files[]" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" aria-describedby="<?= e($editFileInputId) ?>-instructions <?= e($editFileInputId) ?>-list" multiple>
                                        <label class="duty-proof-dropzone-label" for="<?= e($editFileInputId) ?>">
                                            <i class="fa-solid fa-cloud-arrow-up duty-proof-dropzone-icon" aria-hidden="true"></i>
                                            <span class="duty-proof-dropzone-title">Drag and drop your files here</span>
                                            <span class="duty-proof-dropzone-prompt">or <span>browse files</span> to add more</span>
                                            <span id="<?= e($editFileInputId) ?>-instructions" class="duty-proof-dropzone-limit" data-proof-file-limit>
                                                <?= $remainingFileSlots > 0
                                                    ? e($remainingFileSlots . ' additional file' . ($remainingFileSlots === 1 ? '' : 's') . ' available · 5 MB each')
                                                    : 'Remove an existing proof file to add another · 5 MB each' ?>
                                            </span>
                                        </label>
                                    <?php endif; ?>
                                    <ul id="<?= e($editFileInputId) ?>-list" class="duty-proof-file-list duty-submission-proof-list duty-submission-proof-list-in-dropzone" aria-label="Proof files" aria-live="polite">
                                        <?php foreach ($submission['files'] as $file): ?>
                                            <li data-existing-file-id="<?= e($file['id']) ?>">
                                                <i class="fa-regular fa-file-lines duty-proof-file-icon" aria-hidden="true"></i>
                                                <a class="duty-proof-file-name" href="<?= BASE_URL ?>public/duty-proof.php?id=<?= e($file['id']) ?>" target="_blank" rel="noopener"><?= e($file['original_filename']) ?></a>
                                                <small><?= e(number_format((int) $file['file_size'] / (1024 * 1024), 2)) ?> MB</small>
                                                <?php if (!$isLocked): ?>
                                                    <button class="duty-proof-file-remove" type="button" data-remove-existing-proof data-file-id="<?= e($file['id']) ?>" aria-label="Remove <?= e($file['original_filename']) ?>">×</button>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php if (!$isLocked): ?>
                                        <p class="duty-proof-file-error" data-proof-file-error role="alert" hidden></p>
                                    <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                    </form>
                    <div class="duty-submission-dialog-actions">
                        <button class="duty-submission-save-button" type="submit" form="duty-submission-edit-form-<?= e($submission['id']) ?>" <?= $isLocked ? 'disabled' : '' ?>>
                            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                            Save Changes
                        </button>
                        <?php if (!$isLocked): ?>
                            <form method="post" data-confirm-duty-delete>
                                <input type="hidden" name="action" value="delete-duty-submission">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="submission_id" value="<?= e($submission['id']) ?>">
                                <button class="duty-submission-delete-button" type="submit">
                                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                    Delete Submission
                                </button>
                            </form>
                        <?php else: ?>
                            <button class="duty-submission-delete-button" type="button" disabled>
                                <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                Delete Submission
                            </button>
                        <?php endif; ?>
                    </div>
                </dialog>
            <?php endforeach; ?>
        </section>
    </section>
</main>
<dialog class="logout-confirm-dialog" id="duty-delete-confirm-dialog" aria-labelledby="duty-delete-confirm-title" aria-describedby="duty-delete-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></div>
    <h2 id="duty-delete-confirm-title">Delete submission?</h2>
    <p id="duty-delete-confirm-description">This will permanently delete the duty submission and its proof files. This action cannot be undone.</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-duty-delete-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-duty-delete-confirm>Delete Submission</button>
    </div>
</dialog>
<dialog class="logout-confirm-dialog" id="duty-edit-confirm-dialog" aria-labelledby="duty-edit-confirm-title" aria-describedby="duty-edit-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i></div>
    <h2 id="duty-edit-confirm-title">Save these changes?</h2>
    <p id="duty-edit-confirm-description">Saving will send your updated duty submission to your Coordinator for review.</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-duty-edit-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-duty-edit-confirm>Save Changes</button>
    </div>
</dialog>
<dialog class="logout-confirm-dialog duty-submit-confirm-dialog" id="duty-submit-confirm-dialog" aria-labelledby="duty-submit-confirm-title" aria-describedby="duty-submit-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i></div>
    <h2 id="duty-submit-confirm-title">Submit duty proof?</h2>
    <p id="duty-submit-confirm-description">Are you sure you want to submit this duty proof to your Coordinator for review?</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-duty-submit-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-duty-submit-confirm>Submit Duty Proof</button>
    </div>
</dialog>
<?php
$pageScripts = [
    'public/assets/js/duty-proof-dropzone.js',
    'public/assets/js/duty-records-filter.js',
    'public/assets/js/duty-submission-popouts.js?v=' . filemtime(__DIR__ . '/../assets/js/duty-submission-popouts.js'),
];
require __DIR__ . '/../../app/views/components/footer.php';
?>
