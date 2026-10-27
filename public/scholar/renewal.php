<?php

require_once __DIR__ . '/../../middleware/scholar.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/models/Scholar.php';
require_once __DIR__ . '/../../app/models/Renewal.php';
require_once __DIR__ . '/../../app/models/RenewalPeriod.php';
require_once __DIR__ . '/../../app/models/ScholarRequirement.php';
require_once __DIR__ . '/../../app/models/ScholarPeriodContext.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

$scholar = (new Scholar($pdo))->findByUserId((int) $_SESSION['user_id']);

if (!$scholar) {
    http_response_code(403);
    exit('Scholar profile not found.');
}

$renewalModel = new Renewal($pdo);
$periodModel = new RenewalPeriod($pdo);
$allRenewals = $renewalModel->getForScholar((int) $scholar['id']);
$scholarPeriodContext = ScholarPeriodContext::resolve($pdo, $scholar);
$activePeriod = $scholarPeriodContext['current'];
$workPeriod = $periodModel->findWorkPeriodForScholar((int) $scholar['id'], $scholar['year_level']);
$progressPeriod = $scholarPeriodContext['selected'];
$renewals = array_values(array_filter(
    $allRenewals,
    static fn (array $renewal): bool => $progressPeriod
        && (int) $renewal['renewal_period_id'] === (int) $progressPeriod['id']
));
$activeRenewal = null;
if ($progressPeriod) {
    foreach ($renewals as $renewal) {
        if ((int) $renewal['renewal_period_id'] === (int) $progressPeriod['id']) {
            $activeRenewal = $renewal;
            break;
        }
    }
}
$requiredDutyHours = (float) ($progressPeriod['required_duty_hours'] ?? 0);

$dutySummaryStmt = $pdo->prepare(
    'SELECT COALESCE(SUM(hours), 0)
     FROM duty_records
     WHERE scholar_id = :scholar_id
       AND renewal_period_id = :renewal_period_id'
);
$dutySummaryStmt->execute([
    'scholar_id' => (int) $scholar['id'],
    'renewal_period_id' => (int) ($progressPeriod['id'] ?? 0),
]);
$totalDutyHours = (float) $dutySummaryStmt->fetchColumn();

$requirementModel = new ScholarRequirement($pdo);
$periodRequirements = $progressPeriod
    ? array_values(array_filter(
        $requirementModel->allForScholar((int) $scholar['id']),
        static fn ($row) => (int) ($row['renewal_period_id'] ?? 0) === (int) $progressPeriod['id']
    ))
    : [];
$requiredItems = count($periodRequirements);
$approvedItems = count(array_filter($periodRequirements, static fn ($row) => ($row['status'] ?? '') === 'approved'));
$dutyHoursTone = $requiredDutyHours <= 0
    ? 'green'
    : ($totalDutyHours >= $requiredDutyHours ? 'green' : ($totalDutyHours > 0 ? 'orange' : 'red'));
$requirementsTone = $requiredItems <= 0
    ? 'orange'
    : ($approvedItems >= $requiredItems ? 'green' : ($approvedItems > 0 ? 'orange' : 'red'));

$renewalStatusMap = [
    'not_started' => ['label' => 'Awaiting Coordinator', 'message' => 'Your Coordinator will start your renewal cycle after reviewing the previous semester.', 'percent' => 0, 'icon' => 'fa-regular fa-circle', 'tone' => 'not-started'],
    'in_progress' => ['label' => 'Processing', 'message' => 'Submit all requirements and complete your verified duty hours.', 'percent' => 35, 'icon' => 'fa-solid fa-hourglass-half', 'tone' => 'in-progress'],
    'for_review' => ['label' => 'For Review', 'message' => 'Your application is currently being reviewed by the admin team.', 'percent' => 70, 'icon' => 'fa-solid fa-magnifying-glass', 'tone' => 'for-review'],
    'approved' => ['label' => 'Approved', 'message' => 'Your Coordinator approved an exemption. You are eligible for the next renewal cycle, even if the usual duty-hour or requirement targets were not met.', 'percent' => 100, 'icon' => 'fa-solid fa-circle-check', 'tone' => 'approved'],
    'rejected' => ['label' => 'Rejected', 'message' => 'Please review the feedback and resubmit your renewal.', 'percent' => 30, 'icon' => 'fa-solid fa-circle-xmark', 'tone' => 'rejected'],
    'completed' => ['label' => 'Completed', 'message' => 'Your renewal has been finalized successfully.', 'percent' => 100, 'icon' => 'fa-solid fa-award', 'tone' => 'completed'],
];
$currentRenewalStatus = $activeRenewal['status'] ?? 'not_started';
$currentSummary = $renewalStatusMap[$currentRenewalStatus] ?? $renewalStatusMap['not_started'];
$formSubmitted = $activeRenewal !== null;
$requirementsReviewed = in_array($currentRenewalStatus, ['for_review', 'approved', 'completed'], true);
$progressNote = 'Your application is being reviewed.';
if ($currentRenewalStatus === 'approved') {
    $progressNote = 'Your Coordinator approved an exemption for next-cycle eligibility.';
} elseif ($currentRenewalStatus === 'completed') {
    $progressNote = 'Your application is complete.';
} elseif ($currentRenewalStatus === 'not_started') {
    $progressNote = 'Your Coordinator will promote eligible scholars into the active cycle.';
}

$pageTitle = 'Renewal Status';
$pageClass = 'scholar-full-height-page';
require __DIR__ . '/../../app/views/components/header.php';
?>
<main>
    <section class="duty-records-content">
        <div class="page-head duty-page-head">
            <div>
                <h1>Renewal Status</h1>
            </div>
            <div class="duty-info-banner">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>Renewal requests are reviewed after confirming your requirements and duty hours.</span>
            </div>
        </div>

        <?php require __DIR__ . '/../../app/views/components/flash-message.php'; ?>
        <?php if ($progressPeriod && !$scholarPeriodContext['can_transact']): ?>
            <div class="scholar-period-read-only" role="status">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                Viewing <?= e($progressPeriod['academic_year'] . ' · ' . $progressPeriod['semester']) ?> history. Renewal transactions are locked.
            </div>
        <?php endif; ?>

        <div class="records-card start-renewal-card">
            <div class="records-head">
                <div>
                    <h2><?= $scholarPeriodContext['can_transact'] ? 'Current Renewal Cycle' : 'Renewal Cycle History' ?></h2>
                    <p class="sub-small"><?= $scholarPeriodContext['can_transact'] ? 'Your Coordinator starts your cycle after reviewing eligibility.' : 'This period is available for viewing only.' ?></p>
                </div>
            </div>
            <?php if (!$activePeriod): ?>
                <p class="sub-small">A renewal cycle is not available right now.</p>
            <?php elseif (!$scholarPeriodContext['can_transact']): ?>
                <p class="sub-small">This period has ended. Its renewal status and transactions are available for viewing only.</p>
            <?php elseif (!empty($workPeriod['catch_up'])): ?>
                <p class="sub-small">Remaining work from the previous period is locked. Select that period from the header to view its history.</p>
            <?php else: ?>
                <?php if (!$activeRenewal): ?>
                    <p class="sub-small">Your Coordinator will start your initial or next cycle after review.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="records-card renewal-summary-card renewal-tone-<?= e($currentSummary['tone']) ?>">
            <div class="renewal-summary-status">
                <div class="renewal-summary-icon">
                    <i class="<?= e($currentSummary['icon']) ?>" aria-hidden="true"></i>
                </div>
                <div class="renewal-summary-copy">
                    <span><?= $scholarPeriodContext['can_transact'] ? 'CURRENT STATUS' : 'PERIOD STATUS' ?></span>
                    <h2><?= e($currentSummary['label']) ?></h2>
                    <p><?= e($currentSummary['message']) ?></p>
                </div>
            </div>

            <div class="renewal-progress-wrap">
                <h3>Renewal progress</h3>
                <div class="renewal-progress-steps">
                    <div class="renewal-step <?= $formSubmitted ? 'done' : 'pending' ?>">
                        <div class="step-icon"><i class="<?= $formSubmitted ? 'fa-solid fa-clipboard-check' : 'fa-regular fa-clipboard' ?>" aria-hidden="true"></i></div>
                        <div class="step-copy">
                            <p>Cycle Started by Coordinator</p>
                            <small><?= $activeRenewal ? e((new DateTime($activeRenewal['created_at']))->format('M j, Y')) : 'Awaiting promotion' ?></small>
                        </div>
                    </div>
                    <div class="renewal-step <?= $requirementsReviewed ? 'done' : 'pending' ?>">
                        <div class="step-icon"><i class="<?= $requirementsReviewed ? 'fa-solid fa-circle-check' : 'fa-regular fa-circle' ?>" aria-hidden="true"></i></div>
                        <div class="step-copy">
                            <p>Requirements Reviewed</p>
                            <small><?= e($requirementsReviewed ? 'Completed' : 'Awaiting review') ?></small>
                        </div>
                    </div>
                </div>
                <p class="renewal-progress-note">
                    <?= e($progressNote) ?>
                </p>
            </div>

            <div class="renewal-summary-metrics">
                <div class="renewal-summary-item renewal-metric-<?= e($dutyHoursTone) ?>">
                    <div class="summary-item-head">
                        <i class="fa-solid fa-clock" aria-hidden="true"></i>
                        <span>Duty Hours</span>
                    </div>
                    <strong><?= $requiredDutyHours <= 0
                        ? 'Complete · Not required'
                        : e(number_format($totalDutyHours, 2)) . ' / ' . e(number_format((float) $requiredDutyHours, 2)) ?></strong>
                </div>

                <div class="renewal-summary-item renewal-metric-<?= e($requirementsTone) ?>">
                    <div class="summary-item-head">
                        <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                        <span>Requirements</span>
                    </div>
                    <strong><?= e($approvedItems) ?> / <?= e($requiredItems ?: 0) ?></strong>
                </div>
            </div>
        </div>

        <div class="records-card renewal-records-card">
            <div class="records-head">
                <div>
                    <h2>Renewal History</h2>
                    <p class="sub-small"><?= $progressPeriod
                        ? 'Renewal records for ' . e($progressPeriod['academic_year'] . ' · ' . $progressPeriod['semester'])
                        : 'Select a period to view renewal records.' ?></p>
                </div>
                <span class="record-count"><?= e(count($renewals)) ?> records</span>
            </div>

            <div class="table-scroll scholar-renewal-history-scroll">
                <table class="data-table renewal-records-table">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Required Hours</th>
                            <th>Created At</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($renewals as $item): ?>
                            <tr>
                                <td><?= e($item['academic_year'] . ' - ' . $item['semester']) ?></td>
                                <td><?= e(number_format((float) $item['required_duty_hours'], 2)) ?></td>
                                <td><?= e((new DateTime($item['created_at']))->format('M j, Y')) ?></td>
                                <td>
                                    <span class="status-pill status-<?= e(strtolower(str_replace(' ', '_', $item['status']))) ?>"><?= e(ucwords(str_replace('_', ' ', $item['status']))) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$renewals): ?>
                            <tr><td colspan="4">No renewal records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../../app/views/components/footer.php'; ?>
