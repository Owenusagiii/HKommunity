<?php

require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/models/QuickLink.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

$model = new QuickLink($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    $title = trim((string) post('title'));
    $description = trim((string) post('description'));
    $url = trim((string) post('url'));

    if ($action === 'create') {
        $ok = $title !== '' && validUrl($url)
            && $model->create($title, $description !== '' ? $description : null, $url, (int) $_SESSION['user_id']);
        flash($ok ? 'success' : 'error', $ok ? 'Quick link created.' : 'Please enter a valid title and URL.');
    } elseif ($action === 'update') {
        $ok = $title !== '' && validUrl($url)
            && $model->update((int) post('id'), $title, $description !== '' ? $description : null, $url);
        flash($ok ? 'success' : 'error', $ok ? 'Quick link updated.' : 'Unable to update quick link.');
    } elseif ($action === 'status') {
        $status = post('status');
        $ok = in_array($status, ['active', 'inactive'], true)
            && $model->setStatus((int) post('id'), $status);
        flash($ok ? 'success' : 'error', $ok ? 'Quick link status updated.' : 'Unable to update quick link.');
    } elseif ($action === 'delete') {
        $ok = $model->delete((int) post('id'));
        flash($ok ? 'success' : 'error', $ok ? 'Quick link deleted.' : 'Unable to delete quick link.');
    }

    redirectTo(BASE_URL . 'public/admin/quick-links.php');
}

$items = $model->allAdmin();
$pageTitle = 'Quick Links';
require __DIR__ . '/../../app/views/components/header.php';
?>
<main class="admin-quick-links-main">
    <section class="duty-records-content admin-users-page admin-quick-links-page">
        <div class="page-head duty-page-head">
            <div>
                <h1>Quick Links</h1>
            </div>
            <div class="duty-info-banner">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>Open a link record to edit its details, visit the URL, or change its status.</span>
            </div>
        </div>

        <?php require __DIR__ . '/../../app/views/components/flash-message.php'; ?>

        <div class="admin-quick-links-layout">
        <section class="records-card admin-users-card" aria-labelledby="create-quick-link-title">
            <div class="records-head">
                <div>
                    <h2 class="admin-users-section-title" id="create-quick-link-title">
                        <span class="admin-users-section-icon"><i class="fa-solid fa-link" aria-hidden="true"></i></span>
                        Create Quick Link
                    </h2>
                    <p class="sub-small">Add a useful resource to the Scholar portal.</p>
                </div>
            </div>

            <form method="post" class="admin-quick-link-create-form">
                <input type="hidden" name="action" value="create">
                <div class="admin-create-fields-card">
                    <div class="form-row">
                        <label for="title">Title</label>
                        <input id="title" name="title" maxlength="150" required>
                    </div>
                    <div class="form-row">
                        <label for="description">Description <span class="optional-label">Optional</span></label>
                        <textarea id="description" name="description" maxlength="255" rows="3"></textarea>
                    </div>
                    <div class="form-row">
                        <label for="url">URL</label>
                        <input id="url" name="url" type="url" placeholder="https://example.com" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-green">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    Create Quick Link
                </button>
            </form>
        </section>

        <section class="records-card admin-users-card" aria-labelledby="quick-link-records-title">
            <div class="records-head">
                <div>
                    <h2 class="admin-users-section-title" id="quick-link-records-title">
                        <span class="admin-users-section-icon"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></span>
                        Quick Link Records
                    </h2>
                    <p class="sub-small">Shared links and their current status</p>
                </div>
                <div class="admin-quick-links-tools">
                    <span class="record-count"><?= e(count($items)) ?> quick links</span>
                </div>
            </div>

            <div class="table-scroll">
                <table class="data-table admin-role-table admin-quick-links-table" id="admin-quick-links-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr
                                class="admin-popout-row"
                                tabindex="0"
                                data-admin-dialog="admin-quick-link-<?= e($item['id']) ?>"
                                aria-haspopup="dialog"
                                aria-controls="admin-quick-link-<?= e($item['id']) ?>"
                            >
                                <td><?= e($item['title']) ?></td>
                                <td><span class="status-pill status-<?= e($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span></td>
                                <td><?= e((new DateTime($item['created_at']))->format('M j, Y \a\t g:i A')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$items): ?>
                            <tr><td colspan="3">No quick links found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php foreach ($items as $item): ?>
                <dialog class="admin-record-dialog scholar-requirement-dialog admin-record-drawer" id="admin-quick-link-<?= e($item['id']) ?>" aria-labelledby="admin-quick-link-title-<?= e($item['id']) ?>">
                    <div class="scholar-requirement-dialog-head">
                        <div>
                            <span class="scholar-requirement-dialog-eyebrow">Quick link</span>
                            <h2 id="admin-quick-link-title-<?= e($item['id']) ?>"><?= e($item['title']) ?></h2>
                        </div>
                        <button type="button" class="scholar-requirement-dialog-close" aria-label="Close quick link details"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    </div>
                    <div class="scholar-requirement-dialog-content admin-user-detail-content admin-record-drawer-content">
                        <section class="admin-user-detail-card">
                            <div class="admin-user-detail-card-heading">
                                <h3>Quick link details</h3>
                                <p>Review the destination, description, and current status.</p>
                            </div>
                            <div class="scholar-requirement-detail-grid">
                                <div><span>Status</span><strong><span class="status-pill status-<?= e($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span></strong></div>
                                <div><span>Created</span><strong><?= e((new DateTime($item['created_at']))->format('M j, Y \a\t g:i A')) ?></strong></div>
                                <div class="admin-record-drawer-description is-description"><span>Description</span><strong><?= nl2br(e($item['description'] ?: 'No description provided.')) ?></strong></div>
                                <div class="admin-record-drawer-description"><span>URL</span><strong><a class="admin-record-url-link" href="<?= e($item['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($item['url']) ?></a></strong></div>
                            </div>
                        </section>
                        <section class="coordinator-report-actions admin-record-drawer-edit-section" aria-labelledby="edit-quick-link-title-<?= e($item['id']) ?>">
                            <h3 id="edit-quick-link-title-<?= e($item['id']) ?>"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Edit quick link</h3>
                            <div class="coordinator-report-action-row admin-record-drawer-action-row">
                                <form method="post" id="admin-quick-link-edit-form-<?= e($item['id']) ?>" class="admin-record-edit-form admin-record-drawer-form">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                    <div class="form-row">
                                        <label for="quick-link-title-<?= e($item['id']) ?>">Title</label>
                                        <input id="quick-link-title-<?= e($item['id']) ?>" name="title" value="<?= e($item['title']) ?>" maxlength="150" required>
                                    </div>
                                    <div class="form-row">
                                        <label for="quick-link-description-<?= e($item['id']) ?>">Description</label>
                                        <textarea id="quick-link-description-<?= e($item['id']) ?>" name="description" maxlength="255" rows="3"><?= e($item['description']) ?></textarea>
                                    </div>
                                    <div class="form-row">
                                        <label for="quick-link-url-<?= e($item['id']) ?>">URL</label>
                                        <input id="quick-link-url-<?= e($item['id']) ?>" type="url" name="url" value="<?= e($item['url']) ?>" required>
                                    </div>
                                </form>
                            </div>
                        </section>
                        <form method="post" class="admin-quick-link-status-form" id="admin-quick-link-status-form-<?= e($item['id']) ?>" hidden>
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                            <input type="hidden" name="status" value="<?= $item['status'] === 'active' ? 'inactive' : 'active' ?>">
                        </form>
                        <form method="post" id="admin-quick-link-delete-form-<?= e($item['id']) ?>" hidden>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                        </form>
                        <div class="admin-quick-link-status-actions">
                            <button
                                type="submit"
                                form="admin-quick-link-edit-form-<?= e($item['id']) ?>"
                                class="btn admin-record-save-button"
                                data-confirm-admin-record-save="quick link"
                            >
                                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                                Save Changes
                            </button>
                            <button
                                type="submit"
                                form="admin-quick-link-status-form-<?= e($item['id']) ?>"
                                class="btn <?= $item['status'] === 'active' ? 'btn-danger admin-record-deactivate-button' : 'btn-green' ?>"
                                <?= $item['status'] === 'active' ? 'data-confirm-quick-link-deactivation' : '' ?>
                                <?= $item['status'] === 'inactive' ? 'data-confirm-admin-action data-confirm-variant="success" data-confirm-title="Activate quick link?" data-confirm-description="Are you sure you want to activate this quick link?" data-confirm-submit-label="Activate Quick Link"' : '' ?>
                            >
                                <?php if ($item['status'] === 'active'): ?>
                                    <i class="fa-solid fa-ban" aria-hidden="true"></i>
                                <?php endif; ?>
                                <?= $item['status'] === 'active' ? 'Deactivate' : 'Activate Quick Link' ?>
                            </button>
                            <button
                                type="submit"
                                form="admin-quick-link-delete-form-<?= e($item['id']) ?>"
                                class="btn btn-danger admin-record-delete-button"
                                data-confirm-admin-action
                                data-confirm-variant="danger"
                                data-confirm-title="Delete quick link?"
                                data-confirm-description="This will permanently delete this quick link. This action cannot be undone."
                                data-confirm-submit-label="Delete Quick Link"
                            >
                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                Delete Quick Link
                            </button>
                        </div>
                    </div>
                </dialog>
            <?php endforeach; ?>
        </section>
        </div>
    </section>
</main>
<dialog class="logout-confirm-dialog admin-quick-link-deactivation-confirm-dialog" id="admin-quick-link-deactivation-confirm-dialog" aria-labelledby="admin-quick-link-deactivation-confirm-title" aria-describedby="admin-quick-link-deactivation-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div>
    <h2 id="admin-quick-link-deactivation-confirm-title">Deactivate quick link?</h2>
    <p id="admin-quick-link-deactivation-confirm-description">Are you sure you want to deactivate this quick link?</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-quick-link-deactivation-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-quick-link-deactivation-confirm>Deactivate Quick Link</button>
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
    'public/assets/js/admin-quick-link-deactivation-confirmation.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-quick-link-deactivation-confirmation.js'),
    'public/assets/js/admin-action-confirmation.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-action-confirmation.js')
];
require __DIR__ . '/../../app/views/components/footer.php';
?>
