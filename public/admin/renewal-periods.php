<?php

require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/models/RenewalPeriod.php';
require_once __DIR__ . '/../../app/models/Semester.php';
require_once __DIR__ . '/../../app/models/AcademicYear.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

$model = new RenewalPeriod($pdo);
$semesterModel = new Semester($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'create') {
        $yearLevel = post('year_level');
        $hours = (float) post('required_duty_hours');
        $ok = in_array($yearLevel, allowedYearLevels(), true)
            && $hours >= 0
            && $model->create((int) post('semester_id'), $yearLevel, $hours);
        flash($ok ? 'success' : 'error', $ok ? 'Renewal period created.' : 'Unable to create renewal period.');
    } elseif ($action === 'update') {
        $ok = $model->update(
            (int) post('id'),
            (int) post('semester_id'),
            post('year_level'),
            (float) post('required_duty_hours')
        );
        flash($ok ? 'success' : 'error', $ok ? 'Renewal period updated.' : 'Unable to update renewal period.');
    } elseif ($action === 'status') {
        $status = post('status');
        $ok = (int) post('id') > 0
            && in_array($status, ['active', 'inactive'], true)
            && $model->setStatus((int) post('id'), $status);
        flash($ok ? 'success' : 'error', $ok ? 'Renewal period status updated.' : 'Unable to update renewal period.');
    } elseif ($action === 'delete') {
        $id = (int) post('id');
        if ($id > 0 && $model->hasLinkedRecords($id)) {
            flash('error', 'This renewal period cannot be deleted because it has linked scholar records.');
        } else {
            $ok = $id > 0 && $model->delete($id);
            flash($ok ? 'success' : 'error', $ok ? 'Renewal period deleted.' : 'Unable to delete renewal period.');
        }
    }

    redirectTo(BASE_URL . 'public/admin/renewal-periods.php');
}

$semesters = $semesterModel->all();
$items = $model->all();
$pageTitle = 'Renewal Periods';
require __DIR__ . '/../../app/views/components/header.php';
?>
<main>
    <section class="duty-records-content admin-users-page admin-renewal-period-page">
        <div class="page-head duty-page-head">
            <div>
                <h1>Renewal Periods</h1>
            </div>
            <div class="duty-info-banner">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>Renewal periods are available in the portals when their status and academic semester are active.</span>
            </div>
        </div>

        <?php require __DIR__ . '/../../app/views/components/flash-message.php'; ?>

        <section class="records-card admin-users-card renewal-period-create-card" aria-labelledby="create-renewal-period-title">
            <div class="records-head">
                <div>
                    <h2 class="admin-users-section-title" id="create-renewal-period-title">
                        <span class="admin-users-section-icon"><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i></span>
                        Create Renewal Period
                    </h2>
                    <p class="sub-small">Set the duty-hour target for a semester and year level. Enter 0 to lock duty transactions in both Scholar and Coordinator portals.</p>
                </div>
            </div>

            <form method="post" class="renewal-period-create-form">
                <input type="hidden" name="action" value="create">
                <div class="form-row">
                    <label for="semester_id">Academic Semester</label>
                    <select id="semester_id" name="semester_id" required>
                        <option value=""><?= $semesters ? 'Select Semester' : 'Create an academic semester first' ?></option>
                        <?php foreach ($semesters as $semester): ?>
                            <option value="<?= e($semester['id']) ?>">
                                <?= e($semester['academic_year'] . ' - ' . $semester['semester']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <label for="year_level">Year Level</label>
                    <select id="year_level" name="year_level" required>
                        <option value="">Select Year Level</option>
                        <?php foreach (allowedYearLevels() as $level): ?>
                            <option value="<?= e($level) ?>"><?= e($level) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <label for="required_duty_hours">Required Duty Hours</label>
                    <input id="required_duty_hours" name="required_duty_hours" type="number" min="0" max="999.99" step="0.01" placeholder="e.g. 55.00" required>
                </div>

                <button
                    type="submit"
                    class="btn btn-green"
                    data-confirm-admin-action
                    data-confirm-variant="success"
                    data-confirm-title="Create renewal period?"
                    data-confirm-description="Are you sure you want to create this renewal period?"
                    data-confirm-submit-label="Create Renewal Period"
                    <?= !$semesters ? 'disabled' : '' ?>
                >
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    Create Renewal Period
                </button>
            </form>
            <?php if (!$semesters): ?>
                <p class="requirement-form-note renewal-period-form-note">Add an academic semester before configuring renewal periods.</p>
            <?php endif; ?>
        </section>

        <section class="records-card admin-users-card renewal-period-records-card">
            <div class="records-head">
                <div>
                    <h2 class="admin-users-section-title">
                        <span class="admin-users-section-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span>
                        Renewal Period Records
                    </h2>
                    <p class="sub-small">Configured semester requirements by year level</p>
                </div>
                <span class="record-count"><?= e(count($items)) ?> periods</span>
            </div>

            <div class="table-scroll">
                <table class="data-table admin-role-table renewal-period-table">
                    <thead>
                        <tr>
                            <th>Academic Year</th>
                            <th>Semester</th>
                            <th>Year Level</th>
                            <th>Required Hours</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr
                                class="admin-popout-row"
                                tabindex="0"
                                data-admin-dialog="admin-renewal-period-<?= e($item['id']) ?>"
                                aria-haspopup="dialog"
                                aria-controls="admin-renewal-period-<?= e($item['id']) ?>"
                            >
                                <td><?= e($item['academic_year']) ?></td>
                                <td><?= e($item['semester']) ?></td>
                                <td><?= e($item['year_level']) ?></td>
                                <td><?= e(number_format((float) $item['required_duty_hours'], 2)) ?></td>
                                <td><span class="status-pill status-<?= e($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$items): ?>
                            <tr><td colspan="5">No renewal periods found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php foreach ($items as $item): ?>
                <?php $hasLinkedRecords = $model->hasLinkedRecords((int) $item['id']); ?>
                <dialog
                    class="admin-record-dialog scholar-requirement-dialog admin-record-drawer"
                    id="admin-renewal-period-<?= e($item['id']) ?>"
                    aria-labelledby="admin-renewal-period-title-<?= e($item['id']) ?>"
                >
                    <div class="scholar-requirement-dialog-head">
                        <div>
                            <span class="scholar-requirement-dialog-eyebrow">Renewal Period</span>
                            <h2 id="admin-renewal-period-title-<?= e($item['id']) ?>"><?= e($item['academic_year'] . ' · ' . $item['semester']) ?></h2>
                        </div>
                        <button type="button" class="scholar-requirement-dialog-close" aria-label="Close renewal period details">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="scholar-requirement-dialog-content admin-user-detail-content admin-record-drawer-content">
                        <section class="coordinator-report-actions admin-record-drawer-edit-section" aria-labelledby="edit-renewal-period-title-<?= e($item['id']) ?>">
                            <h3 id="edit-renewal-period-title-<?= e($item['id']) ?>"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Edit renewal period</h3>
                            <div class="coordinator-report-action-row admin-record-drawer-action-row">
                                <form method="post" id="admin-renewal-period-edit-form-<?= e($item['id']) ?>" class="admin-record-edit-form admin-record-drawer-form">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                    <div class="form-row">
                                        <label for="renewal-period-semester-<?= e($item['id']) ?>">Academic Semester</label>
                                        <select id="renewal-period-semester-<?= e($item['id']) ?>" name="semester_id" required>
                                            <?php foreach ($semesters as $semester): ?>
                                                <option value="<?= e($semester['id']) ?>" <?= (int) $item['semester_id'] === (int) $semester['id'] ? 'selected' : '' ?>>
                                                    <?= e($semester['academic_year'] . ' - ' . $semester['semester']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-row">
                                        <label for="renewal-period-year-level-<?= e($item['id']) ?>">Year Level</label>
                                        <select id="renewal-period-year-level-<?= e($item['id']) ?>" name="year_level" required>
                                            <?php foreach (allowedYearLevels() as $level): ?>
                                                <option value="<?= e($level) ?>" <?= $item['year_level'] === $level ? 'selected' : '' ?>><?= e($level) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-row">
                                        <label for="renewal-period-hours-<?= e($item['id']) ?>">Required Duty Hours</label>
                                        <input id="renewal-period-hours-<?= e($item['id']) ?>" name="required_duty_hours" type="number" min="0" max="999.99" step="0.01" value="<?= e(number_format((float) $item['required_duty_hours'], 2, '.', '')) ?>" required>
                                    </div>
                                </form>
                            </div>
                        </section>
                        <form
                            method="post"
                            id="admin-renewal-period-status-form-<?= e($item['id']) ?>"
                            <?= $item['status'] === 'active' ? 'data-confirm-renewal-period-deactivation' : '' ?>
                            hidden
                        >
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                            <input type="hidden" name="status" value="<?= $item['status'] === 'active' ? 'inactive' : 'active' ?>">
                        </form>
                        <form method="post" id="admin-renewal-period-delete-form-<?= e($item['id']) ?>" hidden>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                        </form>
                        <div class="admin-quick-link-status-actions">
                            <button
                                type="submit"
                                form="admin-renewal-period-edit-form-<?= e($item['id']) ?>"
                                class="btn admin-record-save-button"
                                data-confirm-admin-record-save="renewal period"
                            >
                                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                                Save Changes
                            </button>
                            <button
                                type="submit"
                                form="admin-renewal-period-status-form-<?= e($item['id']) ?>"
                                class="btn <?= $item['status'] === 'active' ? 'btn-danger admin-record-deactivate-button' : 'btn-green' ?>"
                                <?= $item['status'] === 'inactive' ? 'data-confirm-admin-action data-confirm-variant="success" data-confirm-title="Activate renewal period?" data-confirm-description="Are you sure you want to activate this renewal period?" data-confirm-submit-label="Activate Renewal Period"' : '' ?>
                            >
                                <?php if ($item['status'] === 'active'): ?>
                                    <i class="fa-solid fa-ban" aria-hidden="true"></i>
                                <?php endif; ?>
                                <?= $item['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                            </button>
                            <button
                                type="submit"
                                form="admin-renewal-period-delete-form-<?= e($item['id']) ?>"
                                class="btn btn-danger admin-record-delete-button"
                                data-confirm-admin-action
                                data-confirm-variant="danger"
                                data-confirm-title="Delete renewal period?"
                                data-confirm-description="<?= $hasLinkedRecords ? 'This renewal period has linked scholar records and cannot be deleted.' : 'This will permanently delete this renewal period. This action cannot be undone.' ?>"
                                data-confirm-submit-label="Delete Renewal Period"
                                <?= $hasLinkedRecords ? 'disabled title="This renewal period has linked scholar records." aria-describedby="renewal-period-delete-note-' . e($item['id']) . '"' : '' ?>
                            >
                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                Delete
                            </button>
                        </div>
                        <?php if ($hasLinkedRecords): ?>
                            <p class="requirement-form-note renewal-period-delete-note" id="renewal-period-delete-note-<?= e($item['id']) ?>">
                                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                This period has linked scholar records, so it cannot be deleted.
                            </p>
                        <?php endif; ?>
                    </div>
                </dialog>
            <?php endforeach; ?>
        </section>
    </section>
</main>
<dialog class="logout-confirm-dialog admin-renewal-period-deactivation-confirm-dialog" id="admin-renewal-period-deactivation-confirm-dialog" aria-labelledby="admin-renewal-period-deactivation-confirm-title" aria-describedby="admin-renewal-period-deactivation-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div>
    <h2 id="admin-renewal-period-deactivation-confirm-title">Deactivate renewal period?</h2>
    <p id="admin-renewal-period-deactivation-confirm-description">Are you sure you want to deactivate this renewal period?</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-renewal-period-deactivation-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-renewal-period-deactivation-confirm>Deactivate</button>
    </div>
</dialog>
<dialog class="logout-confirm-dialog admin-record-save-confirm-dialog" id="admin-record-save-confirm-dialog" aria-labelledby="admin-record-save-confirm-title" aria-describedby="admin-record-save-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i></div>
    <h2 id="admin-record-save-confirm-title">Save changes?</h2>
    <p id="admin-record-save-confirm-description">Are you sure you want to save these changes?</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-admin-record-save-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-admin-record-save-confirm>Save Changes</button>
    </div>
</dialog>
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
    'public/assets/js/admin-table-popouts.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-table-popouts.js'),
    'public/assets/js/admin-record-save-confirmation.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-record-save-confirmation.js'),
    'public/assets/js/admin-renewal-period-deactivation-confirmation.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-renewal-period-deactivation-confirmation.js'),
    'public/assets/js/admin-action-confirmation.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-action-confirmation.js')
];
require __DIR__ . '/../../app/views/components/footer.php';
?>
