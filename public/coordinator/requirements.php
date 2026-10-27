<?php

require_once __DIR__ . '/../../middleware/coordinator.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/models/Coordinator.php';
require_once __DIR__ . '/../../app/models/Scholar.php';
require_once __DIR__ . '/../../app/models/Requirement.php';
require_once __DIR__ . '/../../app/models/RenewalPeriod.php';
require_once __DIR__ . '/../../app/models/ScholarRequirement.php';
require_once __DIR__ . '/../../app/models/Notification.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

$coordinator = (new Coordinator($pdo))->findByUserId((int) $_SESSION['user_id']);

if (!$coordinator) {
    http_response_code(403);
    exit('Coordinator profile not found.');
}

$scholarModel = new Scholar($pdo);
$requirementModel = new Requirement($pdo);
$periodModel = new RenewalPeriod($pdo);
$assignmentModel = new ScholarRequirement($pdo);
$notifications = new Notification($pdo);
$activePeriod = $periodModel->findActiveForYearLevel($coordinator['year_level']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    $ok = false;

    if ($action === 'assign') {
        $postedScholarIds = $_POST['scholar_ids'] ?? [];
        $postedRequirementIds = $_POST['requirement_ids'] ?? [];
        $scholarIds = is_array($postedScholarIds)
            ? array_values(array_unique(array_filter(array_map('intval', $postedScholarIds), static fn ($id) => $id > 0)))
            : [];
        $requirementIds = is_array($postedRequirementIds)
            ? array_values(array_unique(array_filter(array_map('intval', $postedRequirementIds), static fn ($id) => $id > 0)))
            : [];
        $activeRequirementIds = array_map(
            static fn ($requirement) => (int) $requirement['id'],
            array_filter($requirementModel->all(), static fn ($requirement) => $requirement['status'] === 'active')
        );
        $requirementIds = array_values(array_intersect($requirementIds, $activeRequirementIds));
        $validScholars = [];
        foreach ($scholarIds as $scholarId) {
            $scholar = $scholarModel->findById($scholarId);
            if ($scholar && $scholar['year_level'] === $coordinator['year_level']) {
                $validScholars[] = $scholarId;
            }
        }

        if ($validScholars && $requirementIds && $activePeriod) {
            $count = 0;
            foreach ($validScholars as $scholarId) {
                $workPeriod = $periodModel->findWorkPeriodForScholar(
                    $scholarId,
                    $coordinator['year_level']
                );
                if (!$workPeriod) {
                    continue;
                }

                $assignedStmt = $pdo->prepare(
                    'SELECT requirement_id
                     FROM scholar_requirements
                     WHERE scholar_id = :scholar_id
                       AND renewal_period_id = :period_id'
                );
                $assignedStmt->execute([
                    'scholar_id' => $scholarId,
                    'period_id' => (int) $workPeriod['id'],
                ]);
                $assignedRequirementIds = array_map('intval', $assignedStmt->fetchAll(PDO::FETCH_COLUMN));
                if ($activeRequirementIds && count(array_intersect($activeRequirementIds, $assignedRequirementIds)) === count($activeRequirementIds)) {
                    continue;
                }

                $availableRequirementIds = array_values(array_diff($requirementIds, $assignedRequirementIds));
                if (!$availableRequirementIds) {
                    continue;
                }

                $assigned = $assignmentModel->assignMany(
                    [$scholarId],
                    $availableRequirementIds,
                    (int) $workPeriod['id'],
                    (int) $_SESSION['user_id']
                );
                $count += $assigned;

                if ($assigned > 0) {
                    $scholar = $scholarModel->findById($scholarId);
                    if ($scholar) {
                        $notifications->createNotification(
                            (int) $scholar['user_id'],
                            'New Requirement Assigned',
                            'A new renewal requirement was assigned to you.',
                            'requirement'
                        );
                    }
                }
            }
            $ok = $count > 0;
        }

        flash($ok ? 'success' : 'error', $ok ? 'Requirement assignment completed.' : 'No valid requirement assignments were made.');
    } elseif ($action === 'review') {
        $id = (int) post('id');
        $status = post('status');
        $reason = trim((string) post('reason'));

        $stmt = $pdo->prepare(
            'SELECT sc.user_id, sc.year_level, r.name
             FROM scholar_requirements sr
             JOIN scholars sc ON sc.id = sr.scholar_id
             JOIN requirements r ON r.id = sr.requirement_id
             WHERE sr.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $target = $stmt->fetch();

        $ok = $target
            && $target['year_level'] === $coordinator['year_level']
            && in_array($status, ['approved', 'rejected'], true)
            && $assignmentModel->review($id, $status, $reason, (int) $_SESSION['user_id']);

        if ($ok) {
            $message = 'Your requirement "' . $target['name'] . '" was ' . $status . '.';
            if ($status === 'rejected') {
                $message .= ' Reason: ' . $reason;
            }
            $notifications->createNotification(
                (int) $target['user_id'],
                'Requirement ' . ucfirst($status),
                $message,
                'requirement'
            );
        }

        flash($ok ? 'success' : 'error', $ok ? 'Requirement reviewed.' : 'Unable to review requirement.');
    }

    redirectTo(BASE_URL . 'public/coordinator/requirements.php');
}

$scholars = $scholarModel->search(null, $coordinator['year_level'], null, $coordinator['year_level']);
$scholars = array_values(array_filter(
    $scholars,
    fn ($scholar) => $periodModel->findWorkPeriodForScholar(
        (int) $scholar['id'],
        $coordinator['year_level']
    ) !== null
));
$requirements = array_values(array_filter($requirementModel->all(), static fn ($item) => $item['status'] === 'active'));
$activeRequirementIds = array_map(static fn ($requirement) => (int) $requirement['id'], $requirements);
$items = $assignmentModel->allForCoordinator($coordinator['year_level']);
$reviewableItemIds = [];
$scholarRequirementGroups = [];
$assignedRequirementIdsByScholarPeriod = [];
foreach ($items as $item) {
    $scholarId = (int) $item['scholar_id'];
    $periodId = (int) $item['renewal_period_id'];
    $assignedRequirementIdsByScholarPeriod[$scholarId][$periodId][] = (int) $item['requirement_id'];
    if (!isset($scholarRequirementGroups[$scholarId])) {
        $scholarRequirementGroups[$scholarId] = [
            'scholar' => $item,
            'items' => [],
        ];
    }
    $scholarRequirementGroups[$scholarId]['items'][] = $item;

    $workPeriod = $periodModel->findWorkPeriodForScholar(
        $scholarId,
        $coordinator['year_level']
    );
    if ($workPeriod && (int) $workPeriod['id'] === (int) $item['renewal_period_id']) {
        $reviewableItemIds[] = (int) $item['id'];
    }
}

$scholarAssignmentLocks = [];
foreach ($scholars as $scholar) {
    $scholarId = (int) $scholar['id'];
    $workPeriod = $periodModel->findWorkPeriodForScholar(
        $scholarId,
        $coordinator['year_level']
    );
    if ($workPeriod && $activeRequirementIds) {
        $assignedIds = $assignedRequirementIdsByScholarPeriod[$scholarId][(int) $workPeriod['id']] ?? [];
        $assignedCount = count(array_intersect($activeRequirementIds, $assignedIds));
        $scholarAssignmentLocks[$scholarId] = $assignedCount === count($activeRequirementIds);
    } else {
        $scholarAssignmentLocks[$scholarId] = false;
    }

    if (!isset($scholarRequirementGroups[$scholarId])) {
        $scholarRequirementGroups[$scholarId] = [
            'scholar' => $scholar,
            'items' => [],
        ];
    }
}

$pageTitle = 'Requirements';
require __DIR__ . '/../../app/views/components/header.php';
?>
<main>
    <section class="duty-records-content">
        <div class="page-head duty-page-head">
            <div>
                <h1>Requirements</h1>
            </div>
            <div class="duty-info-banner">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>Review submitted Google Drive links to approve or return a requirement.</span>
            </div>
        </div>

        <?php require __DIR__ . '/../../app/views/components/flash-message.php'; ?>

        <section class="records-card coordinator-requirements-card" aria-labelledby="assign-requirements-title">
            <div class="records-head">
                <div>
                    <h2 id="assign-requirements-title">Assign Requirements</h2>
                    <p class="sub-small">The system assigns work to the active cycle for promoted scholars or the prior cycle for catch-up.</p>
                </div>
            </div>

            <form method="post" class="requirement-assignment-form">
                <input type="hidden" name="action" value="assign">

                <div class="requirement-choice-grid">
                    <fieldset class="requirement-choice-group scholar-choice-group" data-choice-group="scholar_ids[]">
                        <legend>Scholars <span><?= e(count($scholars)) ?></span></legend>
                        <div class="requirement-choice-tools">
                            <label class="requirement-search">
                                <span class="visually-hidden">Search scholars by student ID number</span>
                                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                <input type="search" class="scholar-id-search" placeholder="Search student ID" autocomplete="off">
                            </label>
                            <button type="button" class="requirement-select-all" aria-pressed="false" <?= !$scholars ? 'disabled' : '' ?>>
                                Select all
                            </button>
                        </div>
                        <div class="requirement-choice-list">
                            <?php foreach ($scholars as $scholar): ?>
                                <label class="requirement-choice<?= !empty($scholarAssignmentLocks[(int) $scholar['id']]) ? ' requirement-choice-locked' : '' ?>" data-student-id="<?= e($scholar['student_id_number']) ?>">
                                    <input
                                        type="checkbox"
                                        name="scholar_ids[]"
                                        value="<?= e($scholar['id']) ?>"
                                        <?= !empty($scholarAssignmentLocks[(int) $scholar['id']]) ? 'disabled title="All active requirements are already assigned."' : '' ?>
                                    >
                                    <span>
                                        <?= e(nameCaps($scholar)) ?>
                                        <span class="requirement-choice-details">
                                            <span class="requirement-choice-student-id"><?= e($scholar['student_id_number']) ?></span>
                                            <?php if (!empty($scholarAssignmentLocks[(int) $scholar['id']])): ?>
                                                <span class="requirement-choice-lock-note">All requirements assigned</span>
                                            <?php endif; ?>
                                        </span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                            <?php if (!$scholars): ?>
                                <p class="requirement-form-note">No scholars have a current or prior renewal cycle to manage.</p>
                            <?php endif; ?>
                        </div>
                    </fieldset>

                    <fieldset class="requirement-choice-group" data-choice-group="requirement_ids[]">
                        <legend>Active Requirements <span><?= e(count($requirements)) ?></span></legend>
                        <div class="requirement-choice-tools">
                            <button type="button" class="requirement-select-all" aria-pressed="false" <?= !$requirements ? 'disabled' : '' ?>>
                                Select all
                            </button>
                        </div>
                        <div class="requirement-choice-list">
                            <?php foreach ($requirements as $requirement): ?>
                                <label class="requirement-choice">
                                    <input type="checkbox" name="requirement_ids[]" value="<?= e($requirement['id']) ?>">
                                    <span><?= e($requirement['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php if (!$requirements): ?>
                                <p class="requirement-form-note">No active requirements are available to assign.</p>
                            <?php endif; ?>
                        </div>
                    </fieldset>
                </div>

                <div class="requirement-assignment-actions">
                    <button type="submit" class="requirement-assign-button" <?= (!$scholars || !$requirements || !$activePeriod) ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        Assign Selected
                    </button>
                </div>
            </form>
        </section>

        <section class="records-card coordinator-requirements-card" aria-labelledby="assigned-requirements-title">
            <div class="records-head">
                <div>
                    <h2 id="assigned-requirements-title">Assigned Requirements</h2>
                    <p class="sub-small">All scholars and their requirement assignments for your year level</p>
                </div>
                <div class="assigned-requirements-tools">
                    <span class="coordinator-toolbar-count"><?= e(count($scholarRequirementGroups)) ?> scholars</span>
                    <label class="requirement-search assigned-requirements-search">
                        <span class="visually-hidden">Search assigned requirements by student ID number</span>
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" id="assigned-requirements-search" placeholder="Search by student ID" autocomplete="off" <?= !$scholarRequirementGroups ? 'disabled' : '' ?>>
                    </label>
                </div>
            </div>

            <div class="table-scroll coordinator-requirements-table-scroll">
                <table class="data-table coordinator-requirements-table">
                    <thead>
                        <tr>
                            <th>Scholar</th>
                            <th>Assigned Requirements</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scholarRequirementGroups as $scholarId => $group): ?>
                            <?php $scholar = $group['scholar']; ?>
                            <tr
                                class="coordinator-popout-row"
                                tabindex="0"
                                data-student-id="<?= e($scholar['student_id_number']) ?>"
                                data-popout-dialog="scholar-requirements-dialog-<?= e($scholarId) ?>"
                                aria-haspopup="dialog"
                                aria-controls="scholar-requirements-dialog-<?= e($scholarId) ?>"
                            >
                                <td>
                                    <span class="coordinator-requirements-scholar-name"><?= e(nameCaps($scholar)) ?></span>
                                    <small class="coordinator-requirements-scholar-id"><?= e($scholar['student_id_number']) ?></small>
                                </td>
                                <td><?= e(count($group['items'])) ?> assigned <?= count($group['items']) === 1 ? 'requirement' : 'requirements' ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$scholarRequirementGroups): ?>
                            <tr><td colspan="2">No eligible scholars found.</td></tr>
                        <?php else: ?>
                            <tr class="requirement-search-empty" hidden>
                                <td colspan="2">No assignments match that student ID.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php foreach ($scholarRequirementGroups as $scholarId => $group): ?>
                <?php $scholar = $group['scholar']; ?>
                <dialog class="coordinator-record-dialog scholar-report-dialog coordinator-duty-review-drawer coordinator-requirements-drawer" id="scholar-requirements-dialog-<?= e($scholarId) ?>" aria-labelledby="scholar-requirements-dialog-title-<?= e($scholarId) ?>">
                    <div class="scholar-report-dialog-head">
                        <div>
                            <span class="scholar-report-dialog-eyebrow">Scholar requirements</span>
                            <h2 id="scholar-requirements-dialog-title-<?= e($scholarId) ?>"><?= e(nameCaps($scholar)) ?></h2>
                            <span class="coordinator-dialog-student-id"><?= e($scholar['student_id_number']) ?></span>
                        </div>
                        <button type="button" class="scholar-report-dialog-close" aria-label="Close requirement details">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="scholar-report-dialog-content">
                        <section class="scholar-report-detail-section coordinator-assigned-requirements-section">
                            <div class="coordinator-assigned-requirements-panel">
                                <h3>Assigned Requirements</h3>
                                <p class="coordinator-assigned-requirements-note">Select a requirement to view its description, review status, and submission.</p>
                                <div class="table-scroll coordinator-assigned-requirements-scroll">
                                    <table class="data-table coordinator-assigned-requirements-table">
                                        <thead>
                                            <tr>
                                                <th>Requirement</th>
                                                <th>Period</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!$group['items']): ?>
                                                <tr>
                                                    <td colspan="3">No requirements assigned yet.</td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php foreach ($group['items'] as $item): ?>
                                                <tr
                                                    class="coordinator-assigned-requirement-row"
                                                    tabindex="0"
                                                    data-requirement-expand
                                                    aria-expanded="false"
                                                    aria-controls="requirement-details-<?= e($item['id']) ?>"
                                                >
                                                    <td><?= e($item['name']) ?></td>
                                                    <td><?= e($item['academic_year'] . ' - ' . $item['semester']) ?></td>
                                                    <td><span class="status-pill status-<?= e($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span></td>
                                                </tr>
                                                <tr class="coordinator-assigned-requirement-details" id="requirement-details-<?= e($item['id']) ?>" hidden>
                                                    <td colspan="3">
                                                        <div class="coordinator-requirement-expanded-content">
                                                            <div class="coordinator-requirement-expanded-actions">
                                                                <?php if ($item['rejection_reason']): ?>
                                                                    <p class="coordinator-requirement-rejection-reason"><strong>Rejection reason:</strong> <?= e($item['rejection_reason']) ?></p>
                                                                <?php endif; ?>
                                                                <div class="coordinator-requirement-actions<?= $item['status'] === 'submitted' && in_array((int) $item['id'], $reviewableItemIds, true) ? ' has-review-actions' : '' ?>">
                                                                    <?php if ($item['gdrive_link']): ?>
                                                                        <a href="<?= e($item['gdrive_link']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-green requirement-submission-button">
                                                                            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                                                            Open GDrive
                                                                        </a>
                                                                    <?php else: ?>
                                                                        <p class="coordinator-requirement-no-submission">No submission has been provided.</p>
                                                                    <?php endif; ?>
                                                                    <?php if ($item['status'] === 'submitted' && in_array((int) $item['id'], $reviewableItemIds, true)): ?>
                                                                        <form method="post" id="coordinator-requirement-review-form-<?= e($item['id']) ?>" class="coordinator-requirement-review-form" data-confirm-requirement-review>
                                                                            <input type="hidden" name="action" value="review">
                                                                            <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                                                            <input type="hidden" name="status" value="approved">
                                                                            <button type="submit" class="coordinator-requirement-approve-button">
                                                                                <i class="fa-solid fa-check" aria-hidden="true"></i> Approve
                                                                            </button>
                                                                            <button type="button" class="coordinator-requirement-reject-button" data-requirement-reject>
                                                                                <i class="fa-solid fa-xmark" aria-hidden="true"></i> Reject
                                                                            </button>
                                                                        </form>
                                                                    <?php elseif ($item['status'] === 'submitted'): ?>
                                                                        <p class="requirement-no-action">This requirement belongs to a closed period and cannot be reviewed.</p>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                    </div>
                </dialog>
            <?php endforeach; ?>
        </section>
    </section>
</main>
<dialog class="logout-confirm-dialog coordinator-requirement-confirm-dialog" id="coordinator-requirement-confirm-dialog" aria-labelledby="coordinator-requirement-confirm-title" aria-describedby="coordinator-requirement-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-circle-question" aria-hidden="true"></i></div>
    <h2 id="coordinator-requirement-confirm-title">Approve requirement?</h2>
    <p id="coordinator-requirement-confirm-description">Are you sure you want to approve this submitted requirement?</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-requirement-review-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-requirement-review-confirm>Confirm approval</button>
    </div>
</dialog>
<dialog class="logout-confirm-dialog coordinator-requirement-reject-dialog" id="coordinator-requirement-reject-dialog" aria-labelledby="coordinator-requirement-reject-title" aria-describedby="coordinator-requirement-reject-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div>
    <h2 id="coordinator-requirement-reject-title">Reject requirement?</h2>
    <p id="coordinator-requirement-reject-description">Enter a reason for rejection. The scholar will be able to see this feedback.</p>
    <form method="post" class="coordinator-requirement-rejection-form" data-requirement-rejection-form>
        <input type="hidden" name="action" value="review">
        <input type="hidden" name="id" value="">
        <input type="hidden" name="status" value="rejected">
        <label for="coordinator-requirement-rejection-reason">Reason for rejection</label>
        <textarea id="coordinator-requirement-rejection-reason" name="reason" rows="4" required></textarea>
        <div class="logout-confirm-actions">
            <button type="button" class="logout-confirm-cancel" data-requirement-rejection-cancel>Cancel</button>
            <button type="submit" class="logout-confirm-submit">Confirm rejection</button>
        </div>
    </form>
</dialog>
<?php
$pageScripts = [
    'public/assets/js/coordinator-table-popouts.js',
    'public/assets/js/requirements-select-all.js?v=' . filemtime(__DIR__ . '/../assets/js/requirements-select-all.js') . '-' . filesize(__DIR__ . '/../assets/js/requirements-select-all.js'),
];
require __DIR__ . '/../../app/views/components/footer.php';
?>
