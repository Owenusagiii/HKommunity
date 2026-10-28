<?php

require_once __DIR__ . '/../../middleware/scholar.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/models/Scholar.php';
require_once __DIR__ . '/../../app/models/DutyRecord.php';
require_once __DIR__ . '/../../app/models/RenewalPeriod.php';
require_once __DIR__ . '/../../app/models/ScholarPeriodContext.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

$scholar = (new Scholar($pdo))->findByUserId((int) $_SESSION['user_id']);

if (!$scholar) {
    http_response_code(403);
    exit('Scholar profile not found.');
}

$model = new DutyRecord($pdo);
$periodModel = new RenewalPeriod($pdo);
$scholarPeriodContext = ScholarPeriodContext::resolve($pdo, $scholar);
$workPeriod = $periodModel->findWorkPeriodForScholar((int) $scholar['id'], $scholar['year_level']);

$selectedPeriod = $scholarPeriodContext['selected'];
$items = $selectedPeriod
    ? $model->all((int) $scholar['id'], null, null, (int) $selectedPeriod['id'])
    : [];
$progressPeriod = $selectedPeriod;
$totalHours = $progressPeriod
    ? $model->totalHours((int) $scholar['id'], (int) $progressPeriod['id'])
    : 0.0;
$requiredHours = (float) ($progressPeriod['required_duty_hours'] ?? 0);
$dutyHoursNotRequired = $progressPeriod !== null && $requiredHours <= 0;
$progress = $requiredHours > 0
    ? min(100, (int) round(($totalHours / $requiredHours) * 100))
    : ($dutyHoursNotRequired ? 100 : 0);
$remainingHours = max(0, $requiredHours - $totalHours);
$dutyHoursComplete = $requiredHours > 0 && $totalHours >= $requiredHours;
$canSubmitDuty = $scholarPeriodContext['can_transact']
    && $workPeriod
    && $selectedPeriod
    && (int) $workPeriod['id'] === (int) $selectedPeriod['id']
    && !$dutyHoursComplete;
$progressTone = $progress >= 100 ? 'progress-green' : ($progress >= 50 ? 'progress-yellow' : 'progress-orange');

$pageTitle = 'My Duty Records';
$pageClass = 'scholar-full-height-page';
require __DIR__ . '/../../app/views/components/header.php';
?>
<main>
    <section class="duty-records-content">
        <div class="page-head duty-page-head">
            <div>
                <h1>Duty Hours</h1>
            </div>
            <div class="duty-info-banner">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>Duty records are validated by your coordinator.</span>
            </div>
        </div>

        <div class="progress-banner duty-progress-banner <?= e($progressTone) ?>">
            <div class="progress-circle">
                <div class="progress-num"><?= e(number_format($totalHours, 2)) ?></div>
                <div class="progress-label"><?= $dutyHoursNotRequired ? 'No hours required' : 'of ' . e(number_format($requiredHours, 2)) . ' hours' ?></div>
            </div>
            <div class="progress-info">
                <div class="progress-head">
                    <h2><?= $dutyHoursNotRequired ? 'Duty Hours Complete' : 'Recorded Duty Hours' ?></h2>
                    <span class="progress-percent"><?= e($progress) ?>%</span>
                </div>
                <div class="progress-bar"><span style="width: <?= e($progress) ?>%;"></span></div>
                <div class="progress-footline">
                    <p class="progress-caption"><?= $dutyHoursNotRequired ? 'No duty hours required' : e(number_format($totalHours, 2)) . ' hours recorded' ?></p>
                    <p class="progress-pending"><?= $dutyHoursNotRequired ? 'Complete' : e(number_format($remainingHours, 2)) . ' hours remaining' ?></p>
                </div>
                <?php if ($canSubmitDuty): ?>
                    <div class="duty-record-submit-action">
                        <a class="duty-weekly-submit-button" href="<?= BASE_URL ?>public/scholar/duty-submission.php">
                            Submit Weekly Duty
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($selectedPeriod && !$scholarPeriodContext['can_transact']): ?>
            <div class="scholar-period-read-only" role="status">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                Viewing <?= e($selectedPeriod['academic_year'] . ' · ' . $selectedPeriod['semester']) ?> history. Duty transactions are locked.
            </div>
        <?php elseif ($selectedPeriod && (float) ($selectedPeriod['required_duty_hours'] ?? 0) <= 0): ?>
            <div class="scholar-period-read-only" role="status">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                No duty hours are required for this period. Duty transactions are locked.
            </div>
        <?php elseif ($dutyHoursComplete): ?>
            <div class="scholar-period-read-only" role="status">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                You have completed the required duty hours for this period. Duty submissions are locked.
            </div>
        <?php endif; ?>
        <div class="records-card duty-records-card">
            <div class="records-head">
                <div>
                    <h2>Duty Records</h2>
                    <p class="sub-small"><?= $selectedPeriod ? e($selectedPeriod['academic_year'] . ' · ' . $selectedPeriod['semester']) : 'No period selected' ?></p>
                </div>
                <div class="duty-records-actions">
                    <span class="record-count" id="duty-record-count" aria-live="polite"><?= e(count($items)) ?> records</span>
                    <label class="duty-record-filter">
                        <span>Show</span>
                        <select id="duty-record-date-filter" aria-label="Filter duty records by date">
                            <option value="all">All</option>
                            <option value="latest">Latest</option>
                            <option value="last-week">Last week</option>
                            <option value="last-month">Last month</option>
                        </select>
                    </label>
                </div>
            </div>

            <div class="table-scroll scholar-duty-table-scroll">
                <table class="data-table duty-records-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Duty Type</th>
                            <th>Hours</th>
                            <th>Recorded at</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr data-duty-record-row data-duty-date="<?= e($item['duty_date']) ?>">
                                <td><?= e($item['duty_date']) ?></td>
                                <td><?= e($item['duty_type']) ?></td>
                                <td><?= e(number_format((float) $item['hours'], 2)) ?></td>
                                <td><?= e((new DateTime($item['created_at']))->format('M j, Y \a\t g:i A')) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$items): ?>
                            <tr><td colspan="4">No duty records found.</td></tr>
                        <?php endif; ?>
                        <?php if ($items): ?>
                            <tr id="duty-record-filter-empty" hidden><td colspan="4">No duty records found for this date range.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>
<?php
$pageScripts = ['public/assets/js/duty-records-filter.js'];
require __DIR__ . '/../../app/views/components/footer.php';
?>
