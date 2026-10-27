<?php
require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/controllers/RegistrationImportController.php';
require_once __DIR__ . '/../../app/models/RegistrationImport.php';
require_once __DIR__ . '/../../app/models/ScholarRegistration.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

$uploadDirectory = __DIR__ . '/../../uploads/registrations';

if (!is_dir($uploadDirectory)) {
    mkdir($uploadDirectory, 0775, true);
}

$importModel = new RegistrationImport($pdo);
$registrationModel = new ScholarRegistration($pdo);
$applyApprovedImport = static function (int $importId) use ($importModel, $registrationModel, $pdo): void {
    $importModel->updateExistingScholarYearLevels($importId);
    $stmt = $pdo->prepare(
        "SELECT id FROM scholar_registrations WHERE status = 'pending'"
    );
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $registrationId) {
        $registrationModel->verifyRegistration((int) $registrationId);
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'upload' && isset($_FILES['registration_file'])) {
        $file = $_FILES['registration_file'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $fileType = $extension === 'xlsx' ? 'xlsx' : ($extension === 'csv' ? 'csv' : '');

        if ($file['error'] !== UPLOAD_ERR_OK || $fileType === '') {
            flash('error', 'Please upload a valid CSV or XLSX file.');
        } else {
            $safeName = bin2hex(random_bytes(8)) . '.' . $fileType;
            $path = $uploadDirectory . DIRECTORY_SEPARATOR . $safeName;

            if (move_uploaded_file($file['tmp_name'], $path)) {
                $result = (new RegistrationImportController($pdo))->importFile(
                    $file['name'],
                    $fileType,
                    $path,
                    (int) $_SESSION['user_id']
                );

                @unlink($path);

                $message = $result['message'];

                if ($result['success']) {
                    $message .= ' Valid: ' . $result['valid_count'] .
                        ', Invalid: ' . $result['invalid_count'] . '.';
                }

                flash($result['success'] ? 'success' : 'error', $message);
            } else {
                flash('error', 'Unable to store the uploaded file.');
            }
        }
    } elseif ($action === 'status') {
        $status = post('status');
        $importId = (int) post('id');
        $currentImport = $importModel->getImportById($importId);
        $ok = $currentImport !== null
            && $currentImport['status'] !== 'approved'
            && in_array($status, ['approved', 'rejected'], true)
            && $importModel->updateImportStatus($importId, $status);

        if ($ok && $status === 'approved') {
            $applyApprovedImport($importId);
        }

        flash(
            $ok ? 'success' : 'error',
            $ok ? 'Import status updated.' : 'Unable to update import status.'
        );
    } elseif ($action === 'delete') {
        $importId = (int) post('id');
        if ($importModel->hasLinkedRegistrations($importId)) {
            flash('error', 'This import cannot be deleted because its registration records are linked to registrations.');
        } else {
            $ok = $importModel->deleteImport($importId);
            flash(
                $ok ? 'success' : 'error',
                $ok ? 'Import deleted.' : 'Unable to delete import. It may have become linked to a registration.'
            );
        }
    }

    redirectTo(BASE_URL . 'public/admin/registration-import.php');
}

$imports = $importModel->all();
$pageTitle = 'Registration Import';
require __DIR__ . '/../../app/views/components/header.php';
?>
<main>
    <section class="duty-records-content admin-users-page admin-import-page">
        <div class="page-head duty-page-head">
            <div>
                <h1>Registration Import</h1>
            </div>
            <div class="duty-info-banner">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>Import a CSV or XLSX file using the required registration columns.</span>
            </div>
        </div>

        <?php require __DIR__ . '/../../app/views/components/flash-message.php'; ?>

        <div class="admin-users-card admin-import-upload-card">
            <div class="admin-import-card-heading">
                <h2 class="admin-users-section-title"><span class="admin-users-section-icon"><i class="fa-solid fa-file-import" aria-hidden="true"></i></span>Upload Registration File</h2>
                <p class="sub-small">Select a CSV or XLSX file to import.</p>
            </div>

            <div class="admin-import-columns">
                <strong>Required columns</strong>
                <code>student_id_number, first_name, middle_name, last_name, suffix, year_level, hk_discount_type</code>
            </div>

            <form method="post" enctype="multipart/form-data" class="admin-import-upload-form">
                <input type="hidden" name="action" value="upload">
                <div class="form-row">
                    <label for="registration_file">CSV or XLSX File</label>
                    <div class="admin-import-file-controls">
                        <input type="file" id="registration_file" name="registration_file" accept=".csv,.xlsx" required>
                        <button
                            type="submit"
                            class="btn btn-green"
                            data-confirm-admin-action
                            data-confirm-variant="success"
                            data-confirm-title="Import registration file?"
                            data-confirm-description="Are you sure you want to import this registration file?"
                            data-confirm-submit-label="Import File"
                        ><i class="fa-solid fa-upload" aria-hidden="true"></i> Import File</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="records-card admin-users-card admin-import-history-card">
            <div class="records-head">
                <div>
                    <h2 class="admin-users-section-title"><span class="admin-users-section-icon"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span>Import History</h2>
                    <p class="sub-small">Previously uploaded registration files</p>
                </div>
                <div class="coordinator-scholars-tools admin-import-history-tools">
                    <span class="record-count" id="import-result-count" aria-live="polite"><?= e(count($imports)) ?> imports</span>
                    <form class="coordinator-scholars-search admin-import-history-search" data-admin-import-search>
                        <label class="visually-hidden" for="import_status_filter">Filter import history by status</label>
                        <select id="import_status_filter">
                            <option value="">All statuses</option>
                            <?php foreach (['pending', 'approved', 'rejected'] as $option): ?>
                                <option value="<?= e($option) ?>"><?= e(ucfirst($option)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="requirement-search">
                            <span class="visually-hidden">Search import history by file name</span>
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <input type="search" id="import_file_search" placeholder="Search by file name" autocomplete="off" aria-controls="admin-imports-table">
                        </label>
                    </form>
                </div>
            </div>

            <div class="table-scroll admin-import-table-scroll">
                <table class="data-table admin-import-table" id="admin-imports-table">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($imports as $item): ?>
                            <tr
                                class="admin-popout-row"
                                tabindex="0"
                                data-admin-import-row
                                data-search-file="<?= e($item['file_name']) ?>"
                                data-search-status="<?= e($item['status']) ?>"
                                data-admin-dialog="admin-import-<?= e($item['id']) ?>"
                                aria-haspopup="dialog"
                                aria-controls="admin-import-<?= e($item['id']) ?>"
                            >
                                <td><?= e($item['file_name']) ?></td>
                                <td><?= e(strtoupper($item['file_type'])) ?></td>
                                <td><span class="status-pill status-<?= e($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span></td>
                                <td><?= e(!empty($item['created_at']) ? (new DateTime($item['created_at']))->format('M j, Y \a\t g:i A') : 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <tr class="admin-import-search-empty" hidden><td colspan="4">No imports match your search.</td></tr>
                    </tbody>
                </table>
            </div>

            <?php foreach ($imports as $item): ?>
                <?php $hasLinkedRegistrations = $importModel->hasLinkedRegistrations((int) $item['id']); ?>
                <dialog class="admin-record-dialog scholar-requirement-dialog admin-registration-detail-dialog admin-import-detail-dialog" id="admin-import-<?= e($item['id']) ?>" aria-labelledby="admin-import-title-<?= e($item['id']) ?>">
                    <div class="scholar-requirement-dialog-head">
                        <div>
                            <span class="scholar-requirement-dialog-eyebrow">Registration import</span>
                            <h2 id="admin-import-title-<?= e($item['id']) ?>"><?= e($item['file_name']) ?></h2>
                            <span class="admin-registration-header-student-id"><?= e(strtoupper($item['file_type'])) ?> file</span>
                        </div>
                        <button type="button" class="scholar-requirement-dialog-close" aria-label="Close import details"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    </div>
                    <div class="scholar-requirement-dialog-content admin-user-detail-content admin-import-detail-content">
                        <section class="admin-user-detail-card admin-import-summary-card">
                            <div class="admin-user-detail-card-heading">
                                <h3>Import details</h3>
                                <p>Review the file status and import information below.</p>
                            </div>
                            <div class="scholar-requirement-detail-grid">
                                <div><span>File type</span><strong><?= e(strtoupper($item['file_type'])) ?></strong></div>
                                <div><span>Status</span><strong><span class="status-pill status-<?= e($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span></strong></div>
                                <div><span>Imported by</span><strong><?= e($item['phinmaed_email']) ?></strong></div>
                                <div><span>Created</span><strong><?= e(!empty($item['created_at']) ? (new DateTime($item['created_at']))->format('M j, Y \a\t g:i A') : 'N/A') ?></strong></div>
                            </div>
                        </section>
                        <?php if ($item['status'] === 'pending'): ?>
                            <div class="admin-record-edit-form admin-import-dialog-actions admin-import-review-card">
                                <div class="admin-import-actions">
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="action" value="status">
                                        <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                        <input type="hidden" name="status" value="approved">
                                        <button
                                            type="submit"
                                            class="btn btn-green"
                                            data-confirm-admin-action
                                            data-confirm-title="Approve this import?"
                                            data-confirm-description="Approving this import applies its valid registration records."
                                            data-confirm-submit-label="Approve Import"
                                            data-confirm-variant="success"
                                        >Approve Import</button>
                                    </form>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="action" value="status">
                                        <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                        <input type="hidden" name="status" value="rejected">
                                        <button
                                            type="submit"
                                            class="admin-import-reject-button"
                                            data-confirm-admin-action
                                            data-confirm-title="Reject this import?"
                                            data-confirm-description="This import will be marked as rejected and will not be processed."
                                            data-confirm-submit-label="Reject Import"
                                            data-confirm-variant="danger"
                                        >Reject Import</button>
                                    </form>
                                    <form method="post" class="admin-import-delete-form">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                        <button
                                            type="submit"
                                            class="admin-import-delete-button"
                                            data-confirm-admin-action
                                            data-confirm-title="Delete this import?"
                                            data-confirm-description="This permanently deletes the import and its unlinked registration rows. This action cannot be undone."
                                            data-confirm-submit-label="Delete Import"
                                            data-confirm-variant="danger"
                                            <?= $hasLinkedRegistrations ? 'disabled' : '' ?>
                                        >Delete Import</button>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <form method="post" class="admin-import-delete-form">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                <button
                                    type="submit"
                                    class="admin-import-delete-button"
                                    data-confirm-admin-action
                                    data-confirm-title="Delete this import?"
                                    data-confirm-description="This permanently deletes the import and its unlinked registration rows. This action cannot be undone."
                                    data-confirm-submit-label="Delete Import"
                                    data-confirm-variant="danger"
                                    <?= $hasLinkedRegistrations ? 'disabled' : '' ?>
                                >Delete Import</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </dialog>
            <?php endforeach; ?>
        </div>
    </section>
</main>
<dialog class="logout-confirm-dialog admin-record-save-confirm-dialog" id="admin-action-confirm-dialog" aria-labelledby="admin-action-confirm-title" aria-describedby="admin-action-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-circle-question" aria-hidden="true"></i></div>
    <h2 id="admin-action-confirm-title">Confirm action</h2>
    <p id="admin-action-confirm-description">Are you sure you want to continue?</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-admin-action-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-admin-action-confirm>Confirm</button>
    </div>
</dialog>
<?php
$pageScripts = [
    'public/assets/js/admin-import-search.js',
    'public/assets/js/admin-table-popouts.js',
    'public/assets/js/admin-action-confirmation.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-action-confirmation.js')
];
require __DIR__ . '/../../app/views/components/footer.php';
?>
