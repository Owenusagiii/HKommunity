<?php

require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/models/Announcement.php';
require_once __DIR__ . '/../../app/models/Notification.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

$model = new Announcement($pdo);
$notifications = new Notification($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    $id = (int) post('id');
    $title = trim((string) post('title'));
    $content = trim((string) post('content'));
    $link = trim((string) post('link'));
    $audience = post('audience');

    if ($action === 'create') {
        $ok = $title !== ''
            && $content !== ''
            && in_array($audience, ['all', 'coordinators', 'scholars'], true)
            && ($link === '' || validUrl($link))
            && $model->create($title, $content, $link !== '' ? $link : null, $audience, (int) $_SESSION['user_id']);

        if ($ok) {
            foreach ($model->audienceUserIds($audience, (int) $_SESSION['user_id']) as $userId) {
                $notifications->createNotification(
                    $userId,
                    'New Announcement',
                    $title,
                    'announcement'
                );
            }
        }

        flash($ok ? 'success' : 'error', $ok ? 'Announcement created.' : 'Unable to create announcement.');
    } elseif ($action === 'update') {
        $ok = $id > 0
            && $title !== ''
            && $content !== ''
            && in_array($audience, ['all', 'coordinators', 'scholars'], true)
            && ($link === '' || validUrl($link))
            && $model->update($id, $title, $content, $link !== '' ? $link : null, $audience);

        flash($ok ? 'success' : 'error', $ok ? 'Announcement updated.' : 'Unable to update announcement.');
    } elseif ($action === 'status') {
        $status = post('status');
        $ok = $id > 0
            && in_array($status, ['active', 'inactive'], true)
            && $model->setStatus($id, $status);

        flash($ok ? 'success' : 'error', $ok ? 'Announcement status updated.' : 'Unable to update announcement.');
    } elseif ($action === 'delete') {
        $ok = $id > 0 && $model->delete($id);
        flash($ok ? 'success' : 'error', $ok ? 'Announcement deleted.' : 'Unable to delete announcement.');
    }

    redirectTo(BASE_URL . 'public/admin/announcements.php');
}

$items = $model->all();
$pageTitle = 'Announcements';
require __DIR__ . '/../../app/views/components/header.php';
?>
<main class="admin-announcements-main">
    <section class="duty-records-content admin-users-page admin-announcements-page">
        <div class="page-head duty-page-head">
            <div>
                <h1>Announcements</h1>
            </div>
            <div class="duty-info-banner">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>Open an announcement record to view or edit its content and change its status.</span>
            </div>
        </div>

        <?php require __DIR__ . '/../../app/views/components/flash-message.php'; ?>

        <div class="admin-announcements-layout">
        <section class="records-card admin-users-card" aria-labelledby="create-announcement-title">
            <div class="records-head">
                <div>
                    <h2 class="admin-users-section-title" id="create-announcement-title">
                        <span class="admin-users-section-icon"><i class="fa-solid fa-bullhorn" aria-hidden="true"></i></span>
                        Create Announcement
                    </h2>
                    <p class="sub-small">Create a message and choose who should receive it.</p>
                </div>
            </div>

            <form method="post" class="admin-announcement-create-form">
                <input type="hidden" name="action" value="create">
                <div class="admin-create-fields-card">
                    <div class="form-row">
                        <label for="title">Title</label>
                        <input id="title" name="title" maxlength="200" required>
                    </div>
                    <div class="form-row">
                        <label for="content">Content</label>
                        <textarea id="content" name="content" rows="4" required></textarea>
                    </div>
                    <div class="admin-announcement-form-grid">
                        <div class="form-row">
                            <label for="link">Link <span class="optional-label">Optional</span></label>
                            <input id="link" name="link" type="url" placeholder="https://example.com">
                        </div>
                        <div class="form-row">
                            <label for="audience">Audience</label>
                            <select id="audience" name="audience" required>
                                <option value="all">All</option>
                                <option value="coordinators">Coordinators</option>
                                <option value="scholars">Scholars</option>
                            </select>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-green">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                    Create Announcement
                </button>
            </form>
        </section>

        <section class="records-card admin-users-card" aria-labelledby="announcement-records-title">
            <div class="records-head">
                <div>
                    <h2 class="admin-users-section-title" id="announcement-records-title">
                        <span class="admin-users-section-icon"><i class="fa-solid fa-bullhorn" aria-hidden="true"></i></span>
                        Announcement Records
                    </h2>
                    <p class="sub-small">Published messages and their current status</p>
                </div>
                <span class="record-count"><?= e(count($items)) ?> announcements</span>
            </div>

            <div class="table-scroll">
                <table class="data-table admin-role-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Audience</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr class="admin-popout-row" tabindex="0" data-admin-dialog="admin-announcement-<?= e($item['id']) ?>" aria-haspopup="dialog" aria-controls="admin-announcement-<?= e($item['id']) ?>">
                                <td><?= e($item['title']) ?></td>
                                <td><?= e(ucfirst($item['audience'])) ?></td>
                                <td><span class="status-pill status-<?= e($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$items): ?>
                            <tr><td colspan="3">No announcements found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php foreach ($items as $item): ?>
                <dialog class="admin-record-dialog scholar-requirement-dialog admin-record-drawer" id="admin-announcement-<?= e($item['id']) ?>" aria-labelledby="admin-announcement-title-<?= e($item['id']) ?>">
                    <div class="scholar-requirement-dialog-head">
                        <div>
                            <span class="scholar-requirement-dialog-eyebrow">Announcement</span>
                            <h2 id="admin-announcement-title-<?= e($item['id']) ?>"><?= e($item['title']) ?></h2>
                        </div>
                        <button type="button" class="scholar-requirement-dialog-close" aria-label="Close announcement details"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    </div>
                    <div class="scholar-requirement-dialog-content admin-user-detail-content admin-record-drawer-content">
                        <section class="admin-user-detail-card">
                            <div class="admin-user-detail-card-heading">
                                <h3>Announcement details</h3>
                                <p>Review who can see this announcement and when it was created.</p>
                            </div>
                            <div class="scholar-requirement-detail-grid">
                                <div><span>Audience</span><strong><?= e(ucfirst($item['audience'])) ?></strong></div>
                                <div><span>Status</span><strong><span class="status-pill status-<?= e($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span></strong></div>
                                <div><span>Created</span><strong><?= e((new DateTime($item['created_at']))->format('M j, Y \a\t g:i A')) ?></strong></div>
                            </div>
                        </section>
                        <section class="admin-user-detail-card admin-record-drawer-preview">
                            <div class="admin-user-detail-card-heading">
                                <h3>Announcement content</h3>
                                <p>Preview the message as it will appear to recipients.</p>
                            </div>
                            <div class="admin-record-description"><?= nl2br(e($item['content'])) ?></div>
                        </section>
                        <?php if ($item['link']): ?>
                            <div class="admin-record-drawer-link"><a href="<?= e($item['link']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-green">Open Link</a></div>
                        <?php endif; ?>
                        <section class="coordinator-report-actions admin-record-drawer-edit-section" aria-labelledby="edit-announcement-title-<?= e($item['id']) ?>">
                            <h3 id="edit-announcement-title-<?= e($item['id']) ?>"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Edit announcement</h3>
                            <div class="coordinator-report-action-row admin-record-drawer-action-row">
                                <form method="post" id="admin-announcement-edit-form-<?= e($item['id']) ?>" class="admin-record-edit-form admin-record-drawer-form">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                    <div class="form-row">
                                        <label for="announcement-title-<?= e($item['id']) ?>">Title</label>
                                        <input id="announcement-title-<?= e($item['id']) ?>" name="title" value="<?= e($item['title']) ?>" maxlength="200" required>
                                    </div>
                                    <div class="form-row">
                                        <label for="announcement-content-<?= e($item['id']) ?>">Content</label>
                                        <textarea id="announcement-content-<?= e($item['id']) ?>" name="content" rows="4" required><?= e($item['content']) ?></textarea>
                                    </div>
                                    <div class="form-row">
                                        <label for="announcement-link-<?= e($item['id']) ?>">Link <span class="optional-label">Optional</span></label>
                                        <input id="announcement-link-<?= e($item['id']) ?>" type="url" name="link" value="<?= e($item['link']) ?>">
                                    </div>
                                    <div class="form-row">
                                        <label for="announcement-audience-<?= e($item['id']) ?>">Audience</label>
                                        <select id="announcement-audience-<?= e($item['id']) ?>" name="audience" required>
                                            <?php foreach (['all', 'coordinators', 'scholars'] as $audience): ?>
                                                <option value="<?= e($audience) ?>" <?= $item['audience'] === $audience ? 'selected' : '' ?>><?= e(ucfirst($audience)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </form>
                            </div>
                        </section>
                        <form method="post" class="admin-record-edit-form admin-announcement-status-form admin-record-drawer-form" id="admin-announcement-status-form-<?= e($item['id']) ?>" hidden>
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                            <input type="hidden" name="status" value="<?= $item['status'] === 'active' ? 'inactive' : 'active' ?>">
                        </form>
                        <form method="post" id="admin-announcement-delete-form-<?= e($item['id']) ?>" hidden>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                        </form>
                        <div class="admin-quick-link-status-actions">
                            <button
                                type="submit"
                                form="admin-announcement-edit-form-<?= e($item['id']) ?>"
                                class="btn admin-record-save-button"
                                data-confirm-admin-record-save="announcement"
                            >
                                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                                Save Changes
                            </button>
                            <button
                                type="submit"
                                form="admin-announcement-status-form-<?= e($item['id']) ?>"
                                class="btn <?= $item['status'] === 'active' ? 'btn-danger admin-record-deactivate-button' : 'btn-green' ?>"
                                <?= $item['status'] === 'active' ? 'data-confirm-announcement-deactivation' : '' ?>
                                <?= $item['status'] === 'inactive' ? 'data-confirm-admin-action data-confirm-variant="success" data-confirm-title="Activate announcement?" data-confirm-description="Are you sure you want to activate this announcement?" data-confirm-submit-label="Activate Announcement"' : '' ?>
                            >
                                <?php if ($item['status'] === 'active'): ?>
                                    <i class="fa-solid fa-ban" aria-hidden="true"></i>
                                <?php endif; ?>
                                <?= $item['status'] === 'active' ? 'Deactivate' : 'Activate Announcement' ?>
                            </button>
                            <button
                                type="submit"
                                form="admin-announcement-delete-form-<?= e($item['id']) ?>"
                                class="btn btn-danger admin-record-delete-button"
                                data-confirm-admin-action
                                data-confirm-variant="danger"
                                data-confirm-title="Delete announcement?"
                                data-confirm-description="This will permanently delete this announcement. This action cannot be undone."
                                data-confirm-submit-label="Delete Announcement"
                            >
                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                Delete Announcement
                            </button>
                        </div>
                    </div>
                </dialog>
            <?php endforeach; ?>
        </section>
        </div>
    </section>
</main>
<dialog class="logout-confirm-dialog admin-announcement-deactivation-confirm-dialog" id="admin-announcement-deactivation-confirm-dialog" aria-labelledby="admin-announcement-deactivation-confirm-title" aria-describedby="admin-announcement-deactivation-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div>
    <h2 id="admin-announcement-deactivation-confirm-title">Deactivate announcement?</h2>
    <p id="admin-announcement-deactivation-confirm-description">Are you sure you want to deactivate this announcement?</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-announcement-deactivation-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-announcement-deactivation-confirm>Deactivate Announcement</button>
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
    'public/assets/js/admin-announcement-deactivation-confirmation.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-announcement-deactivation-confirmation.js'),
    'public/assets/js/admin-action-confirmation.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-action-confirmation.js')
];
require __DIR__ . '/../../app/views/components/footer.php';
?>
