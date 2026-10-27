<?php

require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/models/AcademicYear.php';
require_once __DIR__ . '/../../app/models/Semester.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

$model = new AcademicYear($pdo);
$semesterModel = new Semester($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'create') {
        $academicYear = trim((string) post('academic_year'));
        $valid = preg_match('/^\d{4}-\d{4}$/', $academicYear) === 1;
        $ok = $valid && $model->create($academicYear);
        flash($ok ? 'success' : 'error', $ok ? 'Academic year created.' : 'Invalid or duplicate academic year.');
    } elseif ($action === 'create_semester') {
        $semester = post('semester');
        $ok = in_array($semester, ['1st Semester', '2nd Semester'], true)
            && $semesterModel->create((int) post('academic_year_id'), $semester);
        flash($ok ? 'success' : 'error', $ok ? 'Semester created.' : 'Unable to create semester. It may already exist.');
    } elseif ($action === 'status') {
        $status = post('status');
        $type = post('type');
        $ok = in_array($status, ['active', 'inactive'], true)
            && ($type === 'semester'
                ? $semesterModel->setStatus((int) post('id'), $status)
                : $model->setStatus((int) post('id'), $status));
        flash($ok ? 'success' : 'error', $ok ? 'Status updated.' : 'Unable to update status.');
    } elseif ($action === 'delete') {
        $id = (int) post('id');
        $type = post('type');
        if (!in_array($type, ['year', 'semester'], true) || $id < 1) {
            flash('error', 'Unable to delete record.');
        } elseif ($type === 'year' && $model->hasSemesters($id)) {
            flash('error', 'This academic year cannot be deleted because it has semesters.');
        } elseif ($type === 'semester' && $semesterModel->hasRenewalPeriods($id)) {
            flash('error', 'This semester cannot be deleted because it has renewal periods.');
        } else {
            $ok = $type === 'semester'
                ? $semesterModel->delete($id)
                : $model->delete($id);
            flash($ok ? 'success' : 'error', $ok ? ucfirst($type) . ' deleted.' : 'Unable to delete record.');
        }
    }

    redirectTo(BASE_URL . 'public/admin/academic-years.php');
}

$items = $model->all();
$semesters = $semesterModel->all();
$pageTitle = 'Academic Years & Semesters';
require __DIR__ . '/../../app/views/components/header.php';
?>
<main>
    <section class="duty-records-content admin-academic-page">
        <div class="page-head duty-page-head">
            <div>
                <h1>Academic Years &amp; Semesters</h1>
            </div>
            <div class="duty-info-banner">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>Periods start inactive. Activating a semester also activates its academic year and deactivates other semesters; activating a year alone deactivates all semesters.</span>
            </div>
        </div>

        <?php require __DIR__ . '/../../app/views/components/flash-message.php'; ?>

        <div class="records-card admin-users-card admin-periods-card period-create-grid" role="group" aria-label="Create academic year or semester">
            <form method="post" class="period-create-form admin-academic-create-form">
                <input type="hidden" name="action" value="create">
                <div class="records-head">
                    <div>
                        <h2><span class="admin-users-section-icon"><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i></span>Add Academic Year</h2>
                        <p class="sub-small">Create a new school year.</p>
                    </div>
                </div>
                <div class="form-row">
                    <label for="academic_year">Academic Year</label>
                    <input id="academic_year" name="academic_year" placeholder="2026-2027" required>
                </div>
                <button
                    type="submit"
                    class="btn btn-green"
                    data-confirm-admin-action
                    data-confirm-variant="success"
                    data-confirm-title="Add academic year?"
                    data-confirm-description="Are you sure you want to add this academic year?"
                    data-confirm-submit-label="Add Academic Year"
                >Add Academic Year</button>
            </form>

            <form method="post" class="period-create-form admin-academic-create-form">
                <input type="hidden" name="action" value="create_semester">
                <div class="records-head">
                    <div>
                        <h2><span class="admin-users-section-icon"><i class="fa-solid fa-calendar-week" aria-hidden="true"></i></span>Add Semester</h2>
                        <p class="sub-small">Attach a semester to an academic year.</p>
                    </div>
                </div>
                <div class="semester-create-fields">
                    <div class="form-row">
                        <label for="academic_year_id">Academic Year</label>
                        <select id="academic_year_id" name="academic_year_id" required>
                            <option value="">Select Academic Year</option>
                            <?php foreach ($items as $year): ?>
                                <option value="<?= e($year['id']) ?>"><?= e($year['academic_year']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <label for="semester">Semester</label>
                        <select id="semester" name="semester" required>
                            <option value="">Select Semester</option>
                            <option value="1st Semester">1st Semester</option>
                            <option value="2nd Semester">2nd Semester</option>
                        </select>
                    </div>
                </div>
                <button
                    type="submit"
                    class="btn btn-green"
                    data-confirm-admin-action
                    data-confirm-variant="success"
                    data-confirm-title="Add semester?"
                    data-confirm-description="Are you sure you want to add this semester?"
                    data-confirm-submit-label="Add Semester"
                >Add Semester</button>
            </form>
        </div>

        <div class="records-card admin-users-card admin-periods-card">
            <div class="records-head admin-periods-head">
                <div>
                    <h2 class="admin-users-section-title"><span class="admin-users-section-icon"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span>Academic Year Records</h2>
                    <p class="sub-small">Manage academic years and their active status.</p>
                </div>
                <span class="record-count"><?= e(count($items)) ?> years</span>
            </div>

            <div class="table-scroll admin-academic-table-scroll">
                <table class="data-table admin-periods-table admin-years-table">
                    <thead>
                        <tr>
                            <th>Academic Year</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php $hasSemesters = $model->hasSemesters((int) $item['id']); ?>
                            <tr>
                                <td><?= e($item['academic_year']) ?></td>
                                <td><span class="status-pill status-<?= e($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span></td>
                                <td>
                                    <form method="post" class="inline-form admin-academic-status-form" <?= $item['status'] === 'active' ? 'data-confirm-academic-deactivation="year"' : '' ?>>
                                        <input type="hidden" name="action" value="status">
                                        <input type="hidden" name="type" value="year">
                                        <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                        <input type="hidden" name="status" value="<?= $item['status'] === 'active' ? 'inactive' : 'active' ?>">
                                        <button
                                            type="submit"
                                            class="<?= $item['status'] === 'active' ? 'admin-academic-deactivate-button' : '' ?>"
                                            <?= $item['status'] === 'inactive' ? 'data-confirm-admin-action data-confirm-variant="success" data-confirm-title="Activate academic year?" data-confirm-description="Are you sure you want to activate this academic year?" data-confirm-submit-label="Activate Academic Year"' : '' ?>
                                        ><?= $item['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                                    </form>
                                    <form method="post" class="inline-form admin-academic-delete-form">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="type" value="year">
                                        <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                        <button
                                            type="submit"
                                            class="admin-academic-delete-button"
                                            data-confirm-admin-action
                                            data-confirm-variant="danger"
                                            data-confirm-title="Delete academic year?"
                                            data-confirm-description="<?= $hasSemesters ? 'This academic year has semesters and cannot be deleted.' : 'This will permanently delete this academic year.' ?>"
                                            data-confirm-submit-label="Delete Academic Year"
                                            <?= $hasSemesters ? 'disabled title="This academic year has semesters."' : '' ?>
                                        ><i class="fa-solid fa-trash" aria-hidden="true"></i> Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$items): ?>
                            <tr><td colspan="3">No academic years found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="records-card admin-users-card admin-periods-card">
            <div class="records-head admin-periods-head">
                <div>
                    <h2 class="admin-users-section-title"><span class="admin-users-section-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span>Semester Records</h2>
                    <p class="sub-small">Manage semesters linked to each academic year.</p>
                </div>
                <span class="record-count"><?= e(count($semesters)) ?> semesters</span>
            </div>

            <div class="table-scroll admin-academic-table-scroll">
                <table class="data-table admin-periods-table admin-semesters-table">
                    <thead>
                        <tr>
                            <th>Academic Year</th>
                            <th>Semester</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($semesters as $item): ?>
                            <?php $hasRenewalPeriods = $semesterModel->hasRenewalPeriods((int) $item['id']); ?>
                            <tr>
                                <td><?= e($item['academic_year']) ?></td>
                                <td><?= e($item['semester']) ?></td>
                                <td><span class="status-pill status-<?= e($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span></td>
                                <td>
                                    <form method="post" class="inline-form admin-academic-status-form" <?= $item['status'] === 'active' ? 'data-confirm-academic-deactivation="semester"' : '' ?>>
                                        <input type="hidden" name="action" value="status">
                                        <input type="hidden" name="type" value="semester">
                                        <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                        <input type="hidden" name="status" value="<?= $item['status'] === 'active' ? 'inactive' : 'active' ?>">
                                        <button
                                            type="submit"
                                            class="<?= $item['status'] === 'active' ? 'admin-academic-deactivate-button' : '' ?>"
                                            <?= $item['status'] === 'inactive' ? 'data-confirm-admin-action data-confirm-variant="success" data-confirm-title="Activate semester?" data-confirm-description="Are you sure you want to activate this semester?" data-confirm-submit-label="Activate Semester"' : '' ?>
                                        ><?= $item['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                                    </form>
                                    <form method="post" class="inline-form admin-academic-delete-form">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="type" value="semester">
                                        <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                        <button
                                            type="submit"
                                            class="admin-academic-delete-button"
                                            data-confirm-admin-action
                                            data-confirm-variant="danger"
                                            data-confirm-title="Delete semester?"
                                            data-confirm-description="<?= $hasRenewalPeriods ? 'This semester has renewal periods and cannot be deleted.' : 'This will permanently delete this semester.' ?>"
                                            data-confirm-submit-label="Delete Semester"
                                            <?= $hasRenewalPeriods ? 'disabled title="This semester has renewal periods."' : '' ?>
                                        ><i class="fa-solid fa-trash" aria-hidden="true"></i> Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$semesters): ?>
                            <tr><td colspan="4">No semesters found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>
<dialog class="logout-confirm-dialog admin-academic-status-confirm-dialog" id="admin-academic-deactivation-confirm-dialog" aria-labelledby="admin-academic-deactivation-confirm-title" aria-describedby="admin-academic-deactivation-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div>
    <h2 id="admin-academic-deactivation-confirm-title">Deactivate?</h2>
    <p id="admin-academic-deactivation-confirm-description">Are you sure you want to deactivate this period?</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-academic-deactivation-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-academic-deactivation-confirm>Deactivate</button>
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
    'public/assets/js/admin-academic-deactivation-confirmation.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-academic-deactivation-confirmation.js'),
    'public/assets/js/admin-action-confirmation.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-action-confirmation.js')
];
require __DIR__ . '/../../app/views/components/footer.php';
?>
