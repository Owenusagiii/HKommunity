<?php

require_once __DIR__ . '/../../middleware/scholar.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/models/Scholar.php';
require_once __DIR__ . '/../../app/models/ScholarRequirement.php';
require_once __DIR__ . '/../../app/models/Notification.php';
require_once __DIR__ . '/../../app/models/Coordinator.php';
require_once __DIR__ . '/../../app/models/RenewalPeriod.php';
require_once __DIR__ . '/../../app/models/ScholarPeriodContext.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

$scholar = (new Scholar($pdo))->findByUserId((int) $_SESSION['user_id']);

if (!$scholar) {
    http_response_code(403);
    exit('Scholar profile not found.');
}

$model = new ScholarRequirement($pdo);
$notifications = new Notification($pdo);
$scholarPeriodContext = ScholarPeriodContext::resolve($pdo, $scholar);
$selectedPeriod = $scholarPeriodContext['selected'];
$availableWorkPeriod = (new RenewalPeriod($pdo))->findWorkPeriodForScholar(
    (int) $scholar['id'],
    $scholar['year_level']
);
$workPeriod = $scholarPeriodContext['can_transact']
    && $availableWorkPeriod
    && $selectedPeriod
    && (int) $availableWorkPeriod['id'] === (int) $selectedPeriod['id']
        ? $selectedPeriod
        : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'submit') {
    if (!isValidCsrfToken(post('csrf_token'))) {
        flash('error', 'Unable to verify your request. Please try again.');
        redirectTo(BASE_URL . 'public/scholar/requirements.php');
    }

    if (!$scholarPeriodContext['can_transact']) {
        flash('error', 'Previous-period transactions are locked and can only be viewed.');
        redirectTo(BASE_URL . 'public/scholar/requirements.php');
    }
    if (!$workPeriod) {
        flash('error', 'There is no active renewal work period available for requirement submissions.');
        redirectTo(BASE_URL . 'public/scholar/requirements.php');
    }

    $id = (int) post('id');
    $link = trim((string) post('gdrive_link'));
    $ok = $model->submit($id, (int) $scholar['id'], $link, (int) $_SESSION['user_id']);

    if ($ok) {
        $stmt = $pdo->prepare(
            'SELECT user_id
             FROM coordinators
             WHERE year_level = :year_level
             LIMIT 1'
        );
        $stmt->execute(['year_level' => $scholar['year_level']]);
        if ($coordinatorUserId = $stmt->fetchColumn()) {
            $notifications->createNotification(
                (int) $coordinatorUserId,
                'Requirement Submitted',
                nameCaps($scholar) . ' submitted a requirement.',
                'requirement'
            );
        }
    }

    flash($ok ? 'success' : 'error', $ok ? 'Requirement submitted.' : 'Please provide a valid Google Drive link and an assignable requirement.');
    redirectTo(BASE_URL . 'public/scholar/requirements.php');
}

$items = array_values(array_filter(
    $model->allForScholar((int) $scholar['id']),
    static fn (array $item): bool => $selectedPeriod
        && (int) $item['renewal_period_id'] === (int) $selectedPeriod['id']
));
$pageTitle = 'My Requirements';
$pageClass = 'scholar-full-height-page';
require __DIR__ . '/../../app/views/components/header.php';
?>
<main>
    <section class="duty-records-content">
        <div class="page-head duty-page-head">
            <div>
                <h1>My Requirements</h1>
            </div>
            <div class="duty-info-banner">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>Requirements are reviewed by your coordinator before approval.</span>
            </div>
        </div>

        <?php require __DIR__ . '/../../app/views/components/flash-message.php'; ?>
        <?php if ($selectedPeriod && !$scholarPeriodContext['can_transact']): ?>
            <div class="scholar-period-read-only" role="status">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                Viewing <?= e($selectedPeriod['academic_year'] . ' · ' . $selectedPeriod['semester']) ?> history. Requirement transactions are locked.
            </div>
        <?php endif; ?>

        <div class="records-card requirement-records-card">
            <div class="records-head">
                <div>
                    <h2>Assigned Requirements</h2>
                    <p class="sub-small"><?= $selectedPeriod
                        ? 'Requirements for ' . e($selectedPeriod['academic_year'] . ' · ' . $selectedPeriod['semester'])
                        : 'Select an academic period to view requirements.' ?></p>
                </div>
                <span class="record-count"><?= e(count($items)) ?> items</span>
            </div>

            <div class="table-scroll scholar-requirements-table-scroll">
                <table class="data-table requirement-records-table">
                    <thead>
                        <tr>
                            <th>Requirement</th>
                            <th>Period</th>
                            <th>Status</th>
                            <th>Submitted Requirement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $requirementDialogId = 'scholar-requirement-dialog-' . $item['id'];
                            $canSubmitRequirement = in_array($item['status'], ['assigned', 'rejected'], true)
                                && $workPeriod
                                && (int) $item['renewal_period_id'] === (int) $workPeriod['id'];
                            ?>
                            <tr
                                class="data-row scholar-requirement-row"
                                tabindex="0"
                                data-requirement-dialog="<?= e($requirementDialogId) ?>"
                                aria-haspopup="dialog"
                            >
                                <td><?= e($item['name']) ?></td>
                                <td><?= e($item['academic_year'] . ' - ' . $item['semester']) ?></td>
                                <td>
                                    <span class="status-pill status-<?= e($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span>
                                </td>
                                <td>
                                    <span class="requirement-row-action">
                                        <?= $canSubmitRequirement ? 'Submit requirement' : ($item['gdrive_link'] ? 'View submission' : 'View details') ?>
                                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$items): ?>
                            <tr><td colspan="4">No requirements have been assigned to you.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php foreach ($items as $item): ?>
                <?php
                $requirementDialogId = 'scholar-requirement-dialog-' . $item['id'];
                $canSubmitRequirement = in_array($item['status'], ['assigned', 'rejected'], true)
                    && $workPeriod
                    && (int) $item['renewal_period_id'] === (int) $workPeriod['id'];
                ?>
                <dialog
                    class="scholar-requirement-dialog"
                    id="<?= e($requirementDialogId) ?>"
                    aria-labelledby="<?= e($requirementDialogId) ?>-title"
                >
                    <div class="scholar-requirement-dialog-head">
                        <div>
                            <span class="scholar-requirement-dialog-eyebrow">Requirement details</span>
                            <h2 id="<?= e($requirementDialogId) ?>-title"><?= e($item['name']) ?></h2>
                        </div>
                        <button type="button" class="scholar-requirement-dialog-close" aria-label="Close requirement details">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="scholar-requirement-dialog-content">
                        <h3 class="scholar-requirement-dialog-content-title">Requirement details</h3>
                        <p class="scholar-requirement-dialog-content-note">Review the details, status, and coordinator feedback for this requirement.</p>
                        <div class="scholar-requirement-detail-grid">
                            <div>
                                <span>Required duty hours</span>
                                <strong><?= (float) $item['required_duty_hours'] <= 0
                                    ? 'Not required · Complete'
                                    : e(number_format((float) $item['required_duty_hours'], 2)) . ' hours' ?></strong>
                            </div>
                            <div>
                                <span>Assigned</span>
                                <strong><?= e((new DateTime($item['assigned_at']))->format('M j, Y \a\t g:i A')) ?></strong>
                            </div>
                            <div>
                                <span>Reviewed</span>
                                <strong><?= e($item['reviewed_at'] ? (new DateTime($item['reviewed_at']))->format('M j, Y \a\t g:i A') : 'Not reviewed') ?></strong>
                            </div>
                            <div>
                                <span>Last updated</span>
                                <strong><?= e(isset($item['updated_at']) && $item['updated_at'] ? (new DateTime($item['updated_at']))->format('M j, Y \a\t g:i A') : 'Not updated') ?></strong>
                            </div>
                        </div>
                        <section class="scholar-requirement-detail-section">
                            <h3>Description</h3>
                            <p><?= !empty($item['description']) ? nl2br(e($item['description'])) : 'No additional description provided.' ?></p>
                        </section>
                        <?php if ($item['rejection_reason']): ?>
                            <section class="scholar-requirement-detail-section is-rejected">
                                <h3>Coordinator feedback</h3>
                                <p><?= nl2br(e($item['rejection_reason'])) ?></p>
                            </section>
                        <?php endif; ?>
                        <?php if ($item['gdrive_link'] && !$canSubmitRequirement): ?>
                            <section class="scholar-requirement-detail-section">
                                <h3>Submitted requirement</h3>
                                <a href="<?= e($item['gdrive_link']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-green requirement-submission-button">
                                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                    Open Google Drive Link
                                </a>
                            </section>
                        <?php endif; ?>
                        <?php if ($canSubmitRequirement): ?>
                            <form method="post" class="requirement-submission-form scholar-requirement-submit-form" data-confirm-requirement-submit>
                                <input type="hidden" name="action" value="submit">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                <div class="scholar-requirement-submit-heading">
                                    <span class="scholar-requirement-submit-icon"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i></span>
                                    <div>
                                        <h3><?= $item['status'] === 'rejected' ? 'Resubmit requirement' : 'Submit requirement' ?></h3>
                                        <p>Share a Google Drive link that your coordinator can access.</p>
                                    </div>
                                </div>
                                <label for="<?= e($requirementDialogId) ?>-link">Google Drive link</label>
                                <div class="scholar-requirement-submit-controls">
                                    <div class="scholar-requirement-link-field">
                                        <i class="fa-solid fa-link" aria-hidden="true"></i>
                                        <input id="<?= e($requirementDialogId) ?>-link" type="url" name="gdrive_link" value="<?= e($item['gdrive_link']) ?>" placeholder="https://drive.google.com/..." required>
                                    </div>
                                    <button type="submit" class="duty-weekly-submit-button"><?= $item['status'] === 'rejected' ? 'Resubmit link' : 'Submit link' ?></button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </dialog>
            <?php endforeach; ?>
        </div>
    </section>
</main>
<dialog class="logout-confirm-dialog scholar-requirement-confirm-dialog" id="scholar-requirement-confirm-dialog" aria-labelledby="scholar-requirement-confirm-title" aria-describedby="scholar-requirement-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i></div>
    <h2 id="scholar-requirement-confirm-title">Submit requirement?</h2>
    <p id="scholar-requirement-confirm-description">Are you sure you want to submit this Google Drive link for review?</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-requirement-submit-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-requirement-submit-confirm>Submit link</button>
    </div>
</dialog>
<?php
$pageScripts = ['public/assets/js/scholar-requirement-popouts.js?v=' . filemtime(__DIR__ . '/../assets/js/scholar-requirement-popouts.js')];
require __DIR__ . '/../../app/views/components/footer.php';
?>
