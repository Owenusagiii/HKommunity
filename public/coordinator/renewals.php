<?php

require_once __DIR__ . '/../../middleware/coordinator.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/models/Coordinator.php';
require_once __DIR__ . '/../../app/models/Renewal.php';
require_once __DIR__ . '/../../app/models/RenewalPeriod.php';
require_once __DIR__ . '/../../app/models/Notification.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

$coordinator = (new Coordinator($pdo))->findByUserId((int) $_SESSION['user_id']);

if (!$coordinator) {
    http_response_code(403);
    exit('Coordinator profile not found.');
}

$model = new Renewal($pdo);
$periodModel = new RenewalPeriod($pdo);
$notifications = new Notification($pdo);
$studentId = trim((string) ($_GET['student_id'] ?? ''));
$activePeriod = $periodModel->findActiveForYearLevel($coordinator['year_level']);
$previousPeriod = $activePeriod
    ? $model->previousPeriod((int) $activePeriod['id'])
    : null;
$promotionCandidates = $activePeriod
    ? $model->getPromotionCandidates($coordinator['year_level'], (int) $activePeriod['id'])
    : [];
$eligiblePromotionCandidates = [];
foreach ($promotionCandidates as $candidate) {
    if ($candidate['eligible'] && $candidate['current_renewal_id'] === null) {
        $eligiblePromotionCandidates[(int) $candidate['id']] = $candidate;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = false;
    if (post('action') === 'promote') {
        $scholarId = (int) post('scholar_id');
        $stmt = $pdo->prepare(
            'SELECT user_id, year_level FROM scholars WHERE id = :id'
        );
        $stmt->execute(['id' => $scholarId]);
        $target = $stmt->fetch();

        $ok = isset($eligiblePromotionCandidates[$scholarId])
            && $target
            && $target['year_level'] === $coordinator['year_level']
            && $model->promoteScholar(
                $scholarId,
                $coordinator['year_level'],
                (int) $_SESSION['user_id']
            );

        if ($ok) {
            $notifications->createNotification(
                (int) $target['user_id'],
                'Renewal Cycle Started',
                'Your Coordinator promoted you into a new renewal cycle.',
                'renewal'
            );
        }
        flash($ok ? 'success' : 'error', $ok ? 'Scholar promoted into the active renewal cycle.' : 'Scholar is not eligible for promotion.');
    } elseif (post('action') === 'promote_selected') {
        $postedScholarIds = $_POST['scholar_ids'] ?? [];
        $selectedScholarIds = [];
        if (is_array($postedScholarIds)) {
            foreach ($postedScholarIds as $postedScholarId) {
                if (is_scalar($postedScholarId) && ctype_digit((string) $postedScholarId) && (int) $postedScholarId > 0) {
                    $selectedScholarIds[] = (int) $postedScholarId;
                }
            }
            $selectedScholarIds = array_values(array_unique($selectedScholarIds));
        }
        $promotedCount = 0;

        foreach ($selectedScholarIds as $scholarId) {
            if (!isset($eligiblePromotionCandidates[$scholarId])) {
                continue;
            }

            $candidate = $eligiblePromotionCandidates[$scholarId];
            if (!$model->promoteScholar($scholarId, $coordinator['year_level'], (int) $_SESSION['user_id'])) {
                continue;
            }

            $notifications->createNotification(
                (int) $candidate['user_id'],
                'Renewal Cycle Started',
                'Your Coordinator promoted you into a new renewal cycle.',
                'renewal'
            );
            $promotedCount++;
        }

        if ($promotedCount > 0) {
            $message = $promotedCount . ' scholar' . ($promotedCount === 1 ? '' : 's') . ' promoted into the active renewal cycle.';
            $notPromotedCount = count($selectedScholarIds) - $promotedCount;
            if ($notPromotedCount > 0) {
                $message .= ' ' . $notPromotedCount . ' selected scholar' . ($notPromotedCount === 1 ? ' was' : 's were') . ' not promoted because eligibility may have changed.';
            }
            flash('success', $message);
        } else {
            flash('error', 'No eligible scholars were selected for promotion.');
        }
    } else {
        $id = (int) post('id');
        $status = post('status');
        $errorMessage = 'Unable to update renewal.';

        $stmt = $pdo->prepare(
            'SELECT sc.user_id, sc.year_level
             FROM renewals rn
             JOIN scholars sc ON sc.id = rn.scholar_id
             WHERE rn.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $target = $stmt->fetch();

        $allowed = ['in_progress', 'for_review', 'approved', 'rejected', 'completed'];
        if ($target && $target['year_level'] === $coordinator['year_level'] && in_array($status, $allowed, true)) {
            $completingRenewal = $status === 'completed';
            if ($completingRenewal && !$model->hasMetRequiredDutyHours($id)) {
                $errorMessage = 'Renewal cannot be completed until the scholar meets the required duty hours for this period.';
            } elseif ($completingRenewal && !$model->hasApprovedRequirements($id)) {
                $errorMessage = 'Renewal cannot be completed until all assigned requirements are approved.';
            } else {
                $ok = $model->setStatus($id, $status, (int) $_SESSION['user_id']);
                if (!$ok && $completingRenewal) {
                    if (!$model->hasMetRequiredDutyHours($id)) {
                        $errorMessage = 'Renewal cannot be completed until the scholar meets the required duty hours for this period.';
                    } elseif (!$model->hasApprovedRequirements($id)) {
                        $errorMessage = 'Renewal cannot be completed until all assigned requirements are approved.';
                    }
                }
            }
        }

        if ($ok) {
            $notifications->createNotification(
                (int) $target['user_id'],
                'Renewal Updated',
                'Your renewal status was updated by your Coordinator.',
                'renewal'
            );
        }

        $successMessage = $status === 'approved'
            ? 'Renewal approved as an exemption; the scholar is eligible for the next renewal cycle.'
            : 'Renewal updated.';
        flash($ok ? 'success' : 'error', $ok ? $successMessage : $errorMessage);
    }

    redirectTo(BASE_URL . 'public/coordinator/renewals.php');
}

$items = $model->getForCoordinatorYear(
    $coordinator['year_level'],
    $studentId !== '' ? $studentId : null
);
$scholarRenewals = [];
foreach ($items as $item) {
    $scholarId = (int) $item['scholar_id'];
    if (!isset($scholarRenewals[$scholarId])) {
        $scholarRenewals[$scholarId] = [
            'scholar' => $item,
            'records' => [],
        ];
    }
    $scholarRenewals[$scholarId]['records'][] = $item;
}
$pageTitle = 'Renewals';
require __DIR__ . '/../../app/views/components/header.php';
?>
<main>
    <section class="duty-records-content">
        <div class="page-head duty-page-head">
            <div>
                <h1>Renewals</h1>
            </div>
            <div class="duty-info-banner">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>Search by student ID to manage renewal status and history.</span>
            </div>
        </div>

        <?php require __DIR__ . '/../../app/views/components/flash-message.php'; ?>

        <section class="records-card coordinator-promotion-card" aria-labelledby="promotion-title">
            <div class="records-head">
                <div>
                    <h2 id="promotion-title">Promote Eligible Scholars</h2>
                    <p class="sub-small">Coordinator review starts each scholar's next renewal cycle.</p>
                </div>
                <?php if ($activePeriod): ?>
                    <div class="coordinator-renewals-tools">
                        <span class="coordinator-toolbar-count"><?= e(count($promotionCandidates)) ?> scholars</span>
                        <label class="visually-hidden" for="promotion_eligibility_filter">Filter by eligibility</label>
                        <select id="promotion_eligibility_filter" class="coordinator-promotion-filter" data-promotion-eligibility-filter>
                            <option value="all">All Status</option>
                            <option value="eligible">Eligible</option>
                            <option value="incomplete">Incomplete</option>
                            <option value="promoted">Promoted</option>
                        </select>
                        <div class="coordinator-renewals-search">
                            <label class="requirement-search">
                                <span class="visually-hidden">Search eligible scholars by student ID number</span>
                                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                <input type="search" id="promotion_student_id" placeholder="Search student ID" autocomplete="off" aria-controls="promotion-table" data-promotion-search>
                            </label>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$activePeriod): ?>
                <p class="sub-small">No active renewal period is configured for <?= e($coordinator['year_level']) ?>.</p>
            <?php else: ?>
                <?php if ($previousPeriod): ?>
                    <p class="sub-small">
                        Eligibility is based on <?= e($previousPeriod['academic_year'] . ' - ' . $previousPeriod['semester']) ?>:
                        all assigned requirements must be approved and the required duty hours completed, unless the previous renewal was approved as an exemption.
                    </p>
                <?php endif; ?>
                <div class="table-scroll coordinator-promotion-table-scroll">
                    <table class="data-table coordinator-promotion-table" id="promotion-table">
                        <thead>
                            <tr>
                                <th class="coordinator-promotion-actions-heading"></th>
                                <th class="coordinator-promotion-scholar-heading">
                                    <input type="checkbox" data-promotion-select-all aria-label="Select all eligible scholars">
                                    <span>Name</span>
                                </th>
                                <th>Requirements</th>
                                <th>Duty Hours</th>
                                <th>Eligibility</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($promotionCandidates as $candidate): ?>
                                <?php
                                $approvedCount = (int) $candidate['assigned_count'] - (int) $candidate['unapproved_count'];
                                $alreadyPromoted = $candidate['current_renewal_id'] !== null;
                                $eligibilityStatus = $alreadyPromoted
                                    ? 'promoted'
                                    : ($candidate['eligible']
                                        ? 'eligible'
                                        : 'incomplete');
                                ?>
                                <tr data-promotion-row data-student-id="<?= e($candidate['student_id_number']) ?>" data-eligibility="<?= e($eligibilityStatus) ?>">
                                    <td class="coordinator-promotion-actions">
                                        <?php if (!$alreadyPromoted && $candidate['eligible']): ?>
                                            <input type="checkbox" name="scholar_ids[]" value="<?= e($candidate['id']) ?>" form="promotion-bulk-form" data-promotion-checkbox aria-label="Select <?= e($candidate['student_id_number']) ?> for promotion">
                                        <?php endif; ?>
                                    </td>
                                    <td class="coordinator-promotion-scholar-cell">
                                        <span class="coordinator-promotion-scholar-name"><?= e(nameCaps($candidate)) ?></span>
                                        <small class="coordinator-promotion-scholar-id"><?= e($candidate['student_id_number']) ?></small>
                                    </td>
                                    <td><?= $previousPeriod
                                        ? e($approvedCount) . ' / ' . e($candidate['assigned_count']) . ' approved'
                                        : 'Initial cycle' ?></td>
                                    <td><?= $previousPeriod
                                        ? e(number_format((float) $candidate['rendered_hours'], 2)) . ' / ' . e(number_format((float) $candidate['previous_required_hours'], 2))
                                        : 'Initial cycle' ?></td>
                                    <td>
                                        <?php if ($alreadyPromoted): ?>
                                            <span class="status-pill status-approved">Promoted</span>
                                        <?php elseif ($candidate['eligible']): ?>
                                            <span class="status-pill status-approved">
                                                <?= ($candidate['previous_renewal_status'] ?? null) === 'approved'
                                                    ? 'Eligible (approved exemption)'
                                                    : 'Eligible' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="status-pill status-pending">Incomplete</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$promotionCandidates): ?>
                                <tr class="promotion-no-scholars"><td colspan="5">No scholars are assigned to your year level.</td></tr>
                            <?php else: ?>
                                <tr class="promotion-search-empty" hidden>
                                    <td colspan="5">No scholars match the selected filters.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <form method="post" class="coordinator-promotion-bulk-form" id="promotion-bulk-form">
                    <input type="hidden" name="action" value="promote_selected">
                    <button type="submit" data-promotion-submit>Promote selected</button>
                </form>
            <?php endif; ?>
        </section>

        <div class="records-card coordinator-renewals-card">
            <div class="records-head">
                <div>
                    <h2>Renewal Records</h2>
                    <p class="sub-small">All renewal applications in your assigned year level</p>
                </div>
                <div class="coordinator-renewals-tools">
                    <span class="coordinator-toolbar-count"><?= e(count($scholarRenewals)) ?> scholars</span>
                    <label class="visually-hidden" for="renewal_status_filter">Filter renewals by status</label>
                    <select id="renewal_status_filter" class="coordinator-renewals-status-filter" aria-controls="renewals-table">
                        <option value="">All statuses</option>
                        <?php foreach (Renewal::statusLabels() as $status => $label): ?>
                            <option value="<?= e($status) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <form method="get" class="coordinator-renewals-search">
                        <label class="requirement-search">
                            <span class="visually-hidden">Search renewals by student ID number</span>
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <input type="search" id="student_id" name="student_id" value="<?= e($studentId) ?>" placeholder="Search by student ID" autocomplete="off" aria-controls="renewals-table">
                        </label>
                    </form>
                </div>
            </div>

            <div class="table-scroll coordinator-renewals-table-scroll">
                <table class="data-table coordinator-renewals-table" id="renewals-table">
                    <thead>
                        <tr>
                            <th>Scholar</th>
                            <th>Renewal Records</th>
                            <th>Current Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scholarRenewals as $scholarId => $scholarRenewalGroup): ?>
                            <?php $scholar = $scholarRenewalGroup['scholar']; ?>
                            <?php $currentRenewal = $scholarRenewalGroup['records'][0]; ?>
                            <tr
                                class="coordinator-popout-row"
                                tabindex="0"
                                data-student-id="<?= e($scholar['student_id_number']) ?>"
                                data-renewal-status="<?= e($currentRenewal['status']) ?>"
                                data-popout-dialog="scholar-renewal-dialog-<?= e($scholarId) ?>"
                                aria-haspopup="dialog"
                                aria-controls="scholar-renewal-dialog-<?= e($scholarId) ?>"
                            >
                                <td class="coordinator-renewals-scholar-cell">
                                    <strong class="coordinator-renewals-scholar-name"><?= e(nameCaps($scholar)) ?></strong>
                                    <small class="coordinator-renewals-scholar-id"><?= e($scholar['student_id_number']) ?></small>
                                </td>
                                <td><?= e(count($scholarRenewalGroup['records'])) ?></td>
                                <td>
                                    <span class="status-pill status-<?= e($currentRenewal['status']) ?>">
                                        <?= e(Renewal::statusLabels()[$currentRenewal['status']] ?? ucfirst(str_replace('_', ' ', $currentRenewal['status']))) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$items): ?>
                            <tr><td colspan="3">No renewals found.</td></tr>
                        <?php else: ?>
                            <tr class="renewals-search-empty" hidden>
                                <td colspan="3">No scholars match the selected filters.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php foreach ($scholarRenewals as $scholarId => $scholarRenewalGroup): ?>
                <?php $scholar = $scholarRenewalGroup['scholar']; ?>
                <dialog class="coordinator-record-dialog scholar-report-dialog coordinator-duty-review-drawer coordinator-renewal-drawer coordinator-fill-table-drawer" id="scholar-renewal-dialog-<?= e($scholarId) ?>" aria-labelledby="scholar-renewal-dialog-title-<?= e($scholarId) ?>">
                    <div class="scholar-report-dialog-head">
                        <div>
                            <span class="scholar-report-dialog-eyebrow">Scholar renewal records</span>
                            <h2 id="scholar-renewal-dialog-title-<?= e($scholarId) ?>"><?= e(nameCaps($scholar)) ?></h2>
                            <span class="coordinator-dialog-student-id"><?= e($scholar['student_id_number']) ?></span>
                        </div>
                        <button type="button" class="scholar-report-dialog-close" aria-label="Close scholar renewal records">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="scholar-report-dialog-content coordinator-scholar-duty-content">
                        <section class="duty-submission-review-card coordinator-scholar-duty-card">
                            <div class="coordinator-scholar-duty-card-head">
                                <div>
                                    <h3>Renewal records</h3>
                                    <p class="duty-submission-review-note">
                                        <span data-renewal-record-count><?= e(count($scholarRenewalGroup['records'])) ?> record<?= count($scholarRenewalGroup['records']) === 1 ? '' : 's' ?></span>
                                    </p>
                                </div>
                                <?php
                                $scholarAcademicYears = [];
                                foreach ($scholarRenewalGroup['records'] as $renewalRecord) {
                                    $scholarAcademicYears[$renewalRecord['academic_year']] = $renewalRecord['academic_year'];
                                }
                                ?>
                                <label class="duty-record-filter coordinator-renewals-year-filter">
                                    <span>Academic year</span>
                                    <select aria-label="Filter renewal records by academic year" data-renewal-academic-year-filter>
                                        <option value="all">All</option>
                                        <?php foreach ($scholarAcademicYears as $academicYear): ?>
                                            <option value="<?= e($academicYear) ?>"><?= e($academicYear) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </div>
                            <div class="table-scroll coordinator-scholar-duty-table-scroll">
                                <table class="data-table coordinator-renewals-detail-table">
                                    <thead>
                                        <tr>
                                            <th>Period</th>
                                            <th>Required Hours</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($scholarRenewalGroup['records'] as $item): ?>
                                            <tr
                                                data-renewal-record-row
                                                data-academic-year="<?= e($item['academic_year']) ?>"
                                            >
                                                <td>
                                                    <?= e($item['academic_year'] . ' - ' . $item['semester']) ?>
                                                </td>
                                                <td><?= e(number_format((float) $item['required_duty_hours'], 2)) ?></td>
                                                <td>
                                                    <form
                                                        method="post"
                                                        id="renewal-status-form-<?= e($item['id']) ?>"
                                                        data-confirm-renewal-status
                                                    >
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                                        <select name="status" required aria-label="Renewal status for <?= e($item['academic_year'] . ' ' . $item['semester']) ?>">
                                                            <?php foreach (['in_progress', 'for_review', 'approved', 'rejected', 'completed'] as $status): ?>
                                                                <option value="<?= e($status) ?>" <?= $item['status'] === $status ? 'selected' : '' ?>>
                                                                    <?= e(ucwords(str_replace('_', ' ', $status))) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </form>
                                                </td>
                                                <td>
                                                    <button type="submit" form="renewal-status-form-<?= e($item['id']) ?>">Update</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </dialog>
            <?php endforeach; ?>
        </div>
    </section>
</main>
<dialog class="logout-confirm-dialog renewal-status-confirm-dialog" id="renewal-status-confirm-dialog" aria-labelledby="renewal-status-confirm-title" aria-describedby="renewal-status-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-circle-question" aria-hidden="true"></i></div>
    <h2 id="renewal-status-confirm-title">Confirm status update</h2>
    <p id="renewal-status-confirm-description">Update this renewal status?</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-renewal-status-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-renewal-status-confirm>Confirm update</button>
    </div>
</dialog>
<dialog class="logout-confirm-dialog coordinator-promotion-confirm-dialog" id="promotion-confirm-dialog" aria-labelledby="promotion-confirm-title" aria-describedby="promotion-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></div>
    <h2 id="promotion-confirm-title">Promote selected scholars?</h2>
    <p id="promotion-confirm-description">Selected eligible scholars will be started in the active renewal cycle and notified.</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-promotion-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-promotion-confirm>Promote Scholars</button>
    </div>
</dialog>
<?php
$pageScripts = [
    'public/assets/js/coordinator-table-popouts.js',
    'public/assets/js/renewals-search.js?v=' . filemtime(__DIR__ . '/../assets/js/renewals-search.js') . '-' . filesize(__DIR__ . '/../assets/js/renewals-search.js'),
];
require __DIR__ . '/../../app/views/components/footer.php';
?>
