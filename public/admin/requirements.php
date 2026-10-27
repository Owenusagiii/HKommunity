<?php

require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/models/Requirement.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

$model = new Requirement($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    $name = trim((string) post('name'));
    $description = trim((string) post('description'));

    if ($action === 'create') {
        $ok = $name !== '' && $model->create($name, $description !== '' ? $description : null);
        flash($ok ? 'success' : 'error', $ok ? 'Requirement created.' : 'Unable to create requirement.');
    } elseif ($action === 'update') {
        $ok = $name !== '' && $model->update((int) post('id'), $name, $description !== '' ? $description : null);
        flash($ok ? 'success' : 'error', $ok ? 'Requirement updated.' : 'Unable to update requirement.');
    } elseif ($action === 'status') {
        $status = post('status');
        $ok = in_array($status, ['active', 'inactive'], true)
            && $model->setStatus((int) post('id'), $status);
        flash($ok ? 'success' : 'error', $ok ? 'Requirement status updated.' : 'Unable to update requirement status.');
    }

    redirectTo(BASE_URL . 'public/admin/requirements.php');
}

$items = $model->all();
$pageTitle = 'Requirements';
require __DIR__ . '/../../app/views/components/header.php';
?>
<main class="admin-requirements-main">
    <section class="duty-records-content admin-users-page admin-requirements-page">
        <div class="page-head duty-page-head">
            <div>
                <h1>Requirements</h1>
            </div>
            <div class="duty-info-banner">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>Open a requirement record to edit its details or change its status.</span>
            </div>
        </div>

        <?php require __DIR__ . '/../../app/views/components/flash-message.php'; ?>

        <div class="admin-requirements-layout">
        <section class="records-card admin-users-card admin-requirements-create-card" aria-labelledby="create-requirement-title">
            <div class="records-head">
                <div>
                    <h2 class="admin-users-section-title" id="create-requirement-title">
                        <span class="admin-users-section-icon"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span>
                        Create Requirement
                    </h2>
                    <p class="sub-small">Add a requirement that Coordinators can assign to scholars.</p>
                </div>
            </div>

            <form method="post" class="admin-requirement-create-form">
                <input type="hidden" name="action" value="create">
                <div class="admin-create-fields-card">
                    <div class="form-row">
                        <label for="name">Requirement Name</label>
                        <input id="name" name="name" maxlength="150" required>
                    </div>
                    <div class="form-row">
                        <label for="description">Description <span class="optional-label">Optional</span></label>
                        <textarea id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-green">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    Create Requirement
                </button>
            </form>
        </section>

        <section class="records-card admin-users-card admin-requirements-records-card" aria-labelledby="requirement-records-title">
            <div class="records-head">
                <div>
                    <h2 class="admin-users-section-title" id="requirement-records-title">
                        <span class="admin-users-section-icon"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i></span>
                        Requirement Records
                    </h2>
                    <p class="sub-small">Configured requirements and current status</p>
                </div>
                <div class="admin-requirements-tools">
                    <span class="record-count"><?= e(count($items)) ?> requirements</span>
                </div>
            </div>

            <div class="table-scroll">
                <table class="data-table admin-role-table" id="admin-requirements-table">
                    <thead>
                        <tr>
                            <th>Requirement</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr
                                class="admin-popout-row"
                                tabindex="0"
                                data-admin-dialog="admin-requirement-<?= e($item['id']) ?>"
                                aria-haspopup="dialog"
                                aria-controls="admin-requirement-<?= e($item['id']) ?>"
                            >
                                <td><?= e($item['name']) ?></td>
                                <td><span class="status-pill status-<?= e($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span></td>
                                <td><?= e((new DateTime($item['created_at']))->format('M j, Y \a\t g:i A')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$items): ?>
                            <tr><td colspan="3">No requirements found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php foreach ($items as $item): ?>
                <dialog class="admin-record-dialog scholar-requirement-dialog admin-record-drawer" id="admin-requirement-<?= e($item['id']) ?>" aria-labelledby="admin-requirement-title-<?= e($item['id']) ?>">
                    <div class="scholar-requirement-dialog-head">
                        <div>
                            <span class="scholar-requirement-dialog-eyebrow">Requirement</span>
                            <h2 id="admin-requirement-title-<?= e($item['id']) ?>"><?= e($item['name']) ?></h2>
                        </div>
                        <button type="button" class="scholar-requirement-dialog-close" aria-label="Close requirement details"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    </div>
                    <div class="scholar-requirement-dialog-content admin-user-detail-content admin-record-drawer-content">
                        <section class="admin-user-detail-card">
                            <div class="admin-user-detail-card-heading">
                                <h3>Requirement details</h3>
                                <p>Review the current requirement information and status.</p>
                            </div>
                            <div class="scholar-requirement-detail-grid">
                                <div><span>Status</span><strong><span class="status-pill status-<?= e($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span></strong></div>
                                <div><span>Created</span><strong><?= e((new DateTime($item['created_at']))->format('M j, Y \a\t g:i A')) ?></strong></div>
                                <div class="admin-record-drawer-description is-description"><span>Description</span><strong><?= nl2br(e($item['description'] ?: 'No description provided.')) ?></strong></div>
                            </div>
                        </section>
                        <section class="coordinator-report-actions admin-record-drawer-edit-section" aria-labelledby="edit-requirement-title-<?= e($item['id']) ?>">
                            <h3 id="edit-requirement-title-<?= e($item['id']) ?>"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Edit requirement</h3>
                            <div class="coordinator-report-action-row admin-record-drawer-action-row">
                                <form method="post" id="admin-requirement-edit-form-<?= e($item['id']) ?>" class="admin-record-edit-form admin-record-drawer-form">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                    <div class="form-row">
                                        <label for="requirement-name-<?= e($item['id']) ?>">Requirement Name</label>
                                        <input id="requirement-name-<?= e($item['id']) ?>" name="name" value="<?= e($item['name']) ?>" maxlength="150" required>
                                    </div>
                                    <div class="form-row">
                                        <label for="requirement-description-<?= e($item['id']) ?>">Description</label>
                                        <textarea id="requirement-description-<?= e($item['id']) ?>" name="description" rows="3"><?= e($item['description']) ?></textarea>
                                    </div>
                                </form>
                            </div>
                        </section>
                        <form method="post" class="admin-record-edit-form admin-requirement-status-form admin-record-drawer-form" id="admin-requirement-status-form-<?= e($item['id']) ?>" hidden>
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                            <input type="hidden" name="status" value="<?= $item['status'] === 'active' ? 'inactive' : 'active' ?>">
                        </form>
                        <div class="admin-quick-link-status-actions">
                            <button
                                type="submit"
                                form="admin-requirement-edit-form-<?= e($item['id']) ?>"
                                class="btn admin-record-save-button"
                                data-confirm-admin-record-save="requirement"
                            >
                                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                                Save Changes
                            </button>
                            <button
                                type="submit"
                                form="admin-requirement-status-form-<?= e($item['id']) ?>"
                                class="btn <?= $item['status'] === 'active' ? 'btn-danger admin-record-deactivate-button' : 'btn-green' ?>"
                                <?= $item['status'] === 'active' ? 'data-confirm-requirement-deactivation' : '' ?>
                                <?= $item['status'] === 'inactive' ? 'data-confirm-admin-action data-confirm-variant="success" data-confirm-title="Activate requirement?" data-confirm-description="Are you sure you want to activate this requirement?" data-confirm-submit-label="Activate Requirement"' : '' ?>
                            >
                                <?php if ($item['status'] === 'active'): ?>
                                    <i class="fa-solid fa-ban" aria-hidden="true"></i>
                                <?php endif; ?>
                                <?= $item['status'] === 'active' ? 'Deactivate' : 'Activate Requirement' ?>
                            </button>
                        </div>
                    </div>
                </dialog>
            <?php endforeach; ?>
        </section>
        </div>
    </section>
</main>
<dialog class="logout-confirm-dialog admin-requirement-deactivation-confirm-dialog" id="admin-requirement-deactivation-confirm-dialog" aria-labelledby="admin-requirement-deactivation-confirm-title" aria-describedby="admin-requirement-deactivation-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div>
    <h2 id="admin-requirement-deactivation-confirm-title">Deactivate requirement?</h2>
    <p id="admin-requirement-deactivation-confirm-description">Are you sure you want to deactivate this requirement?</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-requirement-deactivation-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-requirement-deactivation-confirm>Deactivate Requirement</button>
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
<dialog class="logout-confirm-dialog admin-record-save-confirm-dialog" id="admin-record-save-confirm-dialog" aria-labelledby="admin-record-save-confirm-title" aria-describedby="admin-record-save-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i></div>
    <h2 id="admin-record-save-confirm-title">Save changes?</h2>
    <p id="admin-record-save-confirm-description">Are you sure you want to save these changes?</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-admin-record-save-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-admin-record-save-confirm>Save Changes</button>
    </div>
</dialog>
<?php
$pageScripts = [
    'public/assets/js/admin-table-popouts.js',
    'public/assets/js/admin-record-save-confirmation.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-record-save-confirmation.js'),
    'public/assets/js/admin-requirement-deactivation-confirmation.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-requirement-deactivation-confirmation.js'),
    'public/assets/js/admin-action-confirmation.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-action-confirmation.js')
];
require __DIR__ . '/../../app/views/components/footer.php';
?>
