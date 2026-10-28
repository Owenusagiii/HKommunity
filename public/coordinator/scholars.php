<?php

require_once __DIR__ . '/../../middleware/coordinator.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/models/Coordinator.php';
require_once __DIR__ . '/../../app/models/Scholar.php';
require_once __DIR__ . '/../../app/models/DutyRecord.php';
require_once __DIR__ . '/../../app/models/RenewalPeriod.php';
require_once __DIR__ . '/../../app/models/ScholarRequirement.php';
require_once __DIR__ . '/../../app/models/Notification.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

$coordinator = (new Coordinator($pdo))->findByUserId((int) $_SESSION['user_id']);

if (!$coordinator) {
    http_response_code(403);
    exit('Coordinator profile not found.');
}

$scholarRequirementModel = new ScholarRequirement($pdo);
$notificationModel = new Notification($pdo);
$dutyRecordModel = new DutyRecord($pdo);
$periodModel = new RenewalPeriod($pdo);
$activePeriod = $periodModel->findActiveForYearLevel($coordinator['year_level']);
$periodsStmt = $pdo->prepare(
    "SELECT rp.id, rp.required_duty_hours, sm.semester, ay.academic_year
     FROM renewal_periods rp
     JOIN semesters sm ON sm.id = rp.semester_id
     JOIN academic_years ay ON ay.id = sm.academic_year_id
     WHERE rp.year_level = :year_level
     ORDER BY ay.academic_year DESC,
              FIELD(sm.semester, '1st Semester', '2nd Semester') DESC"
);
$periodsStmt->execute(['year_level' => $coordinator['year_level']]);
$periods = $periodsStmt->fetchAll(PDO::FETCH_ASSOC);
$requestedPeriodId = filter_var($_GET['period_id'] ?? null, FILTER_VALIDATE_INT);
$selectedPeriod = null;
if (is_int($requestedPeriodId)) {
    foreach ($periods as $period) {
        if ((int) $period['id'] === $requestedPeriodId) {
            $selectedPeriod = $period;
            break;
        }
    }
}
$selectedPeriod ??= $activePeriod;
$selectedPeriod ??= $periods[0] ?? null;
$studentId = trim((string) ($_GET['student_id'] ?? ''));
$complianceFilter = (string) ($_GET['compliance_filter'] ?? 'default');
if (!in_array($complianceFilter, ['default', 'incomplete', 'complete'], true)) {
    $complianceFilter = 'default';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'review') {
    $requirementId = (int) post('id');
    $status = (string) post('status');
    $reason = trim((string) post('reason'));
    $targetStmt = $pdo->prepare(
        'SELECT sc.user_id, sc.year_level, sr.status AS requirement_status, r.name
         FROM scholar_requirements sr
         JOIN scholars sc ON sc.id = sr.scholar_id
         JOIN requirements r ON r.id = sr.requirement_id
         WHERE sr.id = :id'
    );
    $targetStmt->execute(['id' => $requirementId]);
    $target = $targetStmt->fetch();
    $ok = $target
        && $target['year_level'] === $coordinator['year_level']
        && $target['requirement_status'] === 'submitted'
        && in_array($status, ['approved', 'rejected'], true)
        && $scholarRequirementModel->review(
            $requirementId,
            $status,
            $reason,
            (int) $_SESSION['user_id']
        );

    if ($ok) {
        $message = 'Your requirement "' . $target['name'] . '" was ' . $status . '.';
        if ($status === 'rejected') {
            $message .= ' Reason: ' . $reason;
        }
        $notificationModel->createNotification(
            (int) $target['user_id'],
            'Requirement ' . ucfirst($status),
            $message,
            'requirement'
        );
    }

    flash($ok ? 'success' : 'error', $ok ? 'Requirement reviewed.' : 'Unable to review requirement.');
    $returnQuery = [];
    $returnPeriodId = filter_var(post('return_period_id'), FILTER_VALIDATE_INT);
    $returnPeriodIsValid = is_int($returnPeriodId)
        && $returnPeriodId > 0
        && array_filter($periods, static fn (array $period): bool => (int) $period['id'] === $returnPeriodId);
    if ($returnPeriodIsValid) {
        $returnQuery['period_id'] = $returnPeriodId;
    } elseif ($selectedPeriod) {
        $returnQuery['period_id'] = (int) $selectedPeriod['id'];
    }
    $returnStudentId = trim((string) post('return_student_id'));
    if ($returnStudentId !== '') {
        $returnQuery['student_id'] = $returnStudentId;
    } elseif ($studentId !== '') {
        $returnQuery['student_id'] = $studentId;
    }
    redirectTo(BASE_URL . 'public/coordinator/scholars.php' . ($returnQuery ? '?' . http_build_query($returnQuery) : ''));
}

$rows = (new Scholar($pdo))->search(
    $studentId !== '' ? $studentId : null,
    $coordinator['year_level'],
    null,
    $coordinator['year_level']
);
$scholarProgressById = [];
if ($selectedPeriod && $rows) {
    $scholarIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);
    $scholarPlaceholders = [];
    $scholarProgressParams = [
        'period_id' => (int) $selectedPeriod['id'],
        'duty_period_id' => (int) $selectedPeriod['id'],
        'requirements_period_id' => (int) $selectedPeriod['id'],
    ];
    foreach ($scholarIds as $index => $scholarId) {
        $parameter = 'scholar_' . $index;
        $scholarPlaceholders[] = ':' . $parameter;
        $scholarProgressParams[$parameter] = $scholarId;
    }

    $scholarProgressStmt = $pdo->prepare(
        'SELECT sc.id AS scholar_id,
                rp.required_duty_hours,
                COALESCE(hours.rendered_hours, 0) AS rendered_hours,
                COALESCE(req.assigned_count, 0) AS assigned_count,
                COALESCE(req.approved_count, 0) AS approved_count
         FROM scholars sc
         JOIN renewal_periods rp ON rp.id = :period_id
         LEFT JOIN (
             SELECT scholar_id, SUM(hours) AS rendered_hours
             FROM duty_records
             WHERE renewal_period_id = :duty_period_id
             GROUP BY scholar_id
         ) hours ON hours.scholar_id = sc.id
         LEFT JOIN (
             SELECT scholar_id, COUNT(*) AS assigned_count,
                    SUM(CASE WHEN status = \'approved\' THEN 1 ELSE 0 END) AS approved_count
             FROM scholar_requirements
             WHERE renewal_period_id = :requirements_period_id
             GROUP BY scholar_id
         ) req ON req.scholar_id = sc.id
         WHERE sc.id IN (' . implode(', ', $scholarPlaceholders) . ')'
    );
    $scholarProgressStmt->execute($scholarProgressParams);
    foreach ($scholarProgressStmt->fetchAll(PDO::FETCH_ASSOC) as $scholarProgress) {
        $scholarProgressById[(int) $scholarProgress['scholar_id']] = $scholarProgress;
    }
}
$dutyRecordsByScholar = [];
$dutyRecordIds = [];
foreach ($dutyRecordModel->forCoordinatorYear($coordinator['year_level']) as $record) {
    if (!$selectedPeriod || (int) $record['renewal_period_id'] !== (int) $selectedPeriod['id']) {
        continue;
    }

    $scholarId = (int) $record['scholar_id'];
    $dutyRecordIds[(int) $record['id']] = true;
    $dutyRecordsByScholar[$scholarId]['records'][] = $record;
    $dutyRecordsByScholar[$scholarId]['total_hours'] =
        ($dutyRecordsByScholar[$scholarId]['total_hours'] ?? 0.0) + (float) $record['hours'];
}
$dutyFilesByRecord = [];
if ($dutyRecordIds) {
    $recordPlaceholders = [];
    $recordParams = [];
    foreach (array_keys($dutyRecordIds) as $index => $dutyRecordId) {
        $parameter = 'record_' . $index;
        $recordPlaceholders[] = ':' . $parameter;
        $recordParams[$parameter] = $dutyRecordId;
    }

    $dutyFilesStmt = $pdo->prepare(
        'SELECT dr.id AS duty_record_id, dsf.id, dsf.original_filename, dsf.file_size
         FROM duty_records dr
         JOIN duty_submission_files dsf ON dsf.submission_id = dr.duty_submission_id
         WHERE dr.id IN (' . implode(', ', $recordPlaceholders) . ')
         ORDER BY dsf.id'
    );
    $dutyFilesStmt->execute($recordParams);
    foreach ($dutyFilesStmt->fetchAll(PDO::FETCH_ASSOC) as $dutyFile) {
        $dutyFilesByRecord[(int) $dutyFile['duty_record_id']][] = $dutyFile;
    }
}

$progressScholarCount = 0;
$progressCounts = [
    'requirements' => 0,
    'duty' => 0,
    'complete' => 0,
];
$progressMetrics = [];
if ($selectedPeriod) {
    $progressStmt = $pdo->prepare(
        "SELECT COUNT(*) AS scholar_count,
                COALESCE(SUM(
                    CASE WHEN COALESCE(req.assigned_count, 0) > 0
                              AND COALESCE(req.approved_count, 0) >= req.assigned_count
                         THEN 1 ELSE 0 END
                ), 0) AS requirements_complete,
                COALESCE(SUM(
                    CASE WHEN COALESCE(hours.rendered_hours, 0) >= rp.required_duty_hours
                         THEN 1 ELSE 0 END
                ), 0) AS duty_complete,
                COALESCE(SUM(
                    CASE WHEN COALESCE(req.assigned_count, 0) > 0
                              AND COALESCE(req.approved_count, 0) >= req.assigned_count
                              AND COALESCE(hours.rendered_hours, 0) >= rp.required_duty_hours
                         THEN 1 ELSE 0 END
                ), 0) AS fully_complete
         FROM scholars sc
         JOIN users u ON u.id = sc.user_id
         JOIN renewal_periods rp ON rp.id = :period_id
         LEFT JOIN (
             SELECT scholar_id, renewal_period_id, COUNT(*) AS assigned_count,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_count
             FROM scholar_requirements
             GROUP BY scholar_id, renewal_period_id
         ) req ON req.scholar_id = sc.id AND req.renewal_period_id = rp.id
         LEFT JOIN (
             SELECT scholar_id, renewal_period_id, SUM(hours) AS rendered_hours
             FROM duty_records
             GROUP BY scholar_id, renewal_period_id
         ) hours ON hours.scholar_id = sc.id AND hours.renewal_period_id = rp.id
         WHERE sc.year_level = :year_level
           AND u.role = 'scholar'
           AND u.status = 'active'"
    );
    $progressStmt->execute([
        'period_id' => (int) $selectedPeriod['id'],
        'year_level' => $coordinator['year_level'],
    ]);
    $progress = $progressStmt->fetch(PDO::FETCH_ASSOC);
    $progressScholarCount = (int) $progress['scholar_count'];
    $progressCounts['requirements'] = (int) $progress['requirements_complete'];
    $progressCounts['duty'] = (int) $progress['duty_complete'];
    $progressCounts['complete'] = (int) $progress['fully_complete'];
    $progressMetrics = [
        [
            'label' => 'Requirements Complete',
            'value' => $progressCounts['requirements'],
        ],
        [
            'label' => 'Duty Hours Complete',
            'value' => $progressCounts['duty'],
        ],
        [
            'label' => 'Fully Complete',
            'value' => $progressCounts['complete'],
        ],
    ];
    foreach ($progressMetrics as &$progressMetric) {
        $progressMetric['percentage'] = $progressScholarCount > 0
            ? (int) round(($progressMetric['value'] / $progressScholarCount) * 100)
            : 0;
    }
    unset($progressMetric);
}

$pageTitle = 'Scholars';
require __DIR__ . '/../../app/views/components/header.php';
?>
<main class="coordinator-scholars-main">
    <section class="duty-records-content coordinator-scholars-page">
        <div class="page-head duty-page-head">
            <div>
                <h1>Scholars</h1>
            </div>
            <div class="duty-info-banner">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>Search by student ID to view scholar details.</span>
            </div>
        </div>

        <section class="records-card coordinator-scholar-progress-card" aria-labelledby="coordinator-scholar-progress-title">
            <div class="records-head">
                <div>
                    <h2 id="coordinator-scholar-progress-title">Year-Level Progress</h2>
                    <p class="sub-small">
                        <?= e($coordinator['year_level']) ?> Scholars
                    </p>
                </div>
                <form method="get" class="admin-scholar-progress-period-select coordinator-scholar-period-select">
                    <label for="coordinator-scholar-period">Academic Year &amp; Semester</label>
                    <select id="coordinator-scholar-period" name="period_id" onchange="this.form.submit()" <?= !$periods ? 'disabled' : '' ?>>
                        <?php foreach ($periods as $period): ?>
                            <option
                                value="<?= e($period['id']) ?>"
                                <?= $selectedPeriod && (int) $selectedPeriod['id'] === (int) $period['id'] ? 'selected' : '' ?>
                            >
                                <?= e($period['academic_year'] . ' · ' . $period['semester']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($studentId !== ''): ?>
                        <input type="hidden" name="student_id" value="<?= e($studentId) ?>">
                    <?php endif; ?>
                    <?php if ($complianceFilter !== 'default'): ?>
                        <input type="hidden" name="compliance_filter" value="<?= e($complianceFilter) ?>">
                    <?php endif; ?>
                </form>
            </div>
            <?php if ($selectedPeriod): ?>
                <div class="admin-year-progress-metrics" aria-label="Scholar progress for <?= e($coordinator['year_level']) ?>">
                    <?php foreach ($progressMetrics as $progressMetric): ?>
                        <div class="admin-year-progress-metric">
                            <div class="admin-year-progress-metric-head">
                                <div>
                                    <h3><?= e($progressMetric['label']) ?></h3>
                                </div>
                                <strong>
                                    <?= e($progressMetric['value']) ?> / <?= e($progressScholarCount) ?>
                                    <span class="admin-year-progress-percentage"><?= e($progressMetric['percentage']) ?>%</span>
                                </strong>
                            </div>
                            <div
                                class="admin-year-progress-bar"
                                role="progressbar"
                                aria-label="<?= e($progressMetric['label']) ?>"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                aria-valuenow="<?= e($progressMetric['percentage']) ?>"
                            >
                                <span style="width: <?= e($progressMetric['percentage']) ?>%"></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="coordinator-scholar-progress-empty">
                    Progress metrics are unavailable because there is no active renewal period for this year level.
                </p>
            <?php endif; ?>
        </section>

        <div class="records-card coordinator-scholars-card coordinator-scholars-records-card">
            <div class="records-head">
                <div>
                    <h2>Scholar Records</h2>
                    <p class="sub-small"><?= e($coordinator['year_level']) ?> Scholars</p>
                </div>
                <div class="coordinator-scholars-tools">
                    <span class="coordinator-toolbar-count"><?= e(count($rows)) ?> scholars</span>
                    <form method="get" class="coordinator-scholars-search" id="scholars-search-form">
                        <?php if ($selectedPeriod): ?>
                            <input type="hidden" name="period_id" value="<?= e($selectedPeriod['id']) ?>">
                        <?php endif; ?>
                        <label class="visually-hidden" for="scholar-compliance-filter">Filter scholars by compliance</label>
                        <select id="scholar-compliance-filter" name="compliance_filter" class="coordinator-promotion-filter" aria-label="Filter scholars by compliance" <?= !$selectedPeriod ? 'disabled' : '' ?>>
                            <option value="default" <?= $complianceFilter === 'default' ? 'selected' : '' ?>>Default</option>
                            <option value="incomplete" <?= $complianceFilter === 'incomplete' ? 'selected' : '' ?>>Not Complete</option>
                            <option value="complete" <?= $complianceFilter === 'complete' ? 'selected' : '' ?>>Complete</option>
                        </select>
                        <label class="requirement-search">
                            <span class="visually-hidden">Search scholars by student ID number</span>
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <input type="search" id="student_id" name="student_id" value="<?= e($studentId) ?>" placeholder="Search by student ID" autocomplete="off" aria-controls="scholars-table">
                        </label>
                    </form>
                </div>
            </div>

            <div class="table-scroll coordinator-scholars-table-scroll">
                <table class="data-table coordinator-scholars-table" id="scholars-table">
                    <thead>
                        <tr>
                            <th>Scholar</th>
                            <th>Hours</th>
                            <th>Requirements</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $scholarProgress = $scholarProgressById[(int) $row['id']] ?? null;
                            $dutyComplete = $scholarProgress
                                && (float) $scholarProgress['rendered_hours'] >= (float) $scholarProgress['required_duty_hours'];
                            $requirementsComplete = $scholarProgress
                                && (int) $scholarProgress['assigned_count'] > 0
                                && (int) $scholarProgress['approved_count'] >= (int) $scholarProgress['assigned_count'];
                            ?>
                            <tr
                                class="coordinator-popout-row"
                                tabindex="0"
                                data-student-id="<?= e($row['student_id_number']) ?>"
                                data-popout-dialog="scholar-dialog-<?= e($row['id']) ?>"
                                aria-haspopup="dialog"
                                aria-controls="scholar-dialog-<?= e($row['id']) ?>"
                                data-duty-complete="<?= $dutyComplete ? 'true' : 'false' ?>"
                                data-requirements-complete="<?= $requirementsComplete ? 'true' : 'false' ?>"
                            >
                                <td class="coordinator-scholar-identity">
                                    <strong><?= e(nameCaps($row)) ?></strong>
                                    <small><?= e($row['student_id_number']) ?></small>
                                </td>
                                <td>
                                    <?php if ($scholarProgress): ?>
                                        <?= e(number_format((float) $scholarProgress['rendered_hours'], 2)) ?>
                                        / <?= e(number_format((float) $scholarProgress['required_duty_hours'], 2)) ?> hrs
                                    <?php else: ?>
                                        &mdash;
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($scholarProgress): ?>
                                        <?= e((int) $scholarProgress['approved_count']) ?>
                                        / <?= e((int) $scholarProgress['assigned_count']) ?> approved
                                    <?php else: ?>
                                        &mdash;
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-pill status-<?= e($row['status']) ?>">
                                        <?= e(ucfirst($row['status'])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$rows): ?>
                            <tr><td colspan="4">No scholars found.</td></tr>
                        <?php else: ?>
                            <tr class="scholars-search-empty" hidden>
                                <td colspan="4">No scholars match the selected filters.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php foreach ($rows as $row): ?>
                <?php
                $scholarId = (int) $row['id'];
                $scholarRequirements = $selectedPeriod
                    ? array_values(array_filter(
                        $scholarRequirementModel->allForScholar($scholarId),
                        static fn (array $requirement): bool => (int) $requirement['renewal_period_id'] === (int) $selectedPeriod['id']
                    ))
                    : [];
                $scholarWorkPeriod = $periodModel->findWorkPeriodForScholar(
                    $scholarId,
                    $coordinator['year_level']
                );
                $scholarDutyRecords = $dutyRecordsByScholar[$scholarId]['records'] ?? [];
                $scholarDutyTotalHours = $dutyRecordsByScholar[$scholarId]['total_hours'] ?? 0.0;
                ?>
                <dialog class="coordinator-record-dialog scholar-report-dialog coordinator-duty-review-drawer" id="scholar-dialog-<?= e($row['id']) ?>" aria-labelledby="scholar-dialog-title-<?= e($row['id']) ?>">
                    <div class="scholar-report-dialog-head">
                        <div>
                            <span class="scholar-report-dialog-eyebrow">Scholar details</span>
                            <h2 id="scholar-dialog-title-<?= e($row['id']) ?>"><?= e(nameCaps($row)) ?></h2>
                            <span class="coordinator-dialog-student-id"><?= e($row['student_id_number']) ?></span>
                        </div>
                        <button type="button" class="scholar-report-dialog-close" aria-label="Close scholar details">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="scholar-report-dialog-content coordinator-scholar-duty-content">
                        <section class="duty-submission-review-card coordinator-scholar-duty-card">
                            <div class="coordinator-scholar-duty-card-head">
                                <div>
                                    <h3>Duty records</h3>
                                    <p class="duty-submission-review-note">
                                        <?= e(count($scholarDutyRecords)) ?> record<?= count($scholarDutyRecords) === 1 ? '' : 's' ?>
                                        &middot; <?= e(number_format((float) $scholarDutyTotalHours, 2)) ?> total hours
                                    </p>
                                </div>
                                <div class="coordinator-scholar-duty-filters">
                                    <label class="duty-record-filter coordinator-scholar-duty-date-filter">
                                        <span>Date</span>
                                        <select id="scholar-duty-date-filter-<?= e($row['id']) ?>" data-scholar-duty-date-filter aria-label="Filter duty records by recorded date">
                                            <option value="all">All</option>
                                            <option value="latest">Latest</option>
                                            <option value="last-week">Last week</option>
                                            <option value="last-month">Last month</option>
                                        </select>
                                    </label>
                                </div>
                            </div>
                            <div class="table-scroll coordinator-scholar-duty-table-scroll">
                                <table class="data-table coordinator-scholar-duty-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Duty type</th>
                                            <th>Hours</th>
                                            <th>Period</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$scholarDutyRecords): ?>
                                            <tr>
                                                <td colspan="4">No duty records submitted yet.</td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php foreach ($scholarDutyRecords as $dutyRecord): ?>
                                            <?php $dutyFiles = $dutyFilesByRecord[(int) $dutyRecord['id']] ?? []; ?>
                                            <tr
                                                class="coordinator-assigned-requirement-row"
                                                tabindex="0"
                                                data-requirement-expand
                                                aria-expanded="false"
                                                aria-controls="scholar-duty-files-<?= e($dutyRecord['id']) ?>"
                                                data-recorded-date="<?= e((new DateTime($dutyRecord['created_at']))->format('Y-m-d')) ?>"
                                                data-period-id="<?= e($dutyRecord['renewal_period_id']) ?>"
                                            >
                                                <td>
                                                    <?= e($dutyRecord['duty_date']) ?>
                                                    <small class="coordinator-scholar-duty-record-meta">
                                                        Recorded <?= e((new DateTime($dutyRecord['created_at']))->format('M j, Y g:i A')) ?>
                                                        <?php if ($dutyRecord['updated_at']): ?>
                                                            <br>Updated <?= e((new DateTime($dutyRecord['updated_at']))->format('M j, Y g:i A')) ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </td>
                                                <td><?= e($dutyRecord['duty_type']) ?></td>
                                                <td><?= e(number_format((float) $dutyRecord['hours'], 2)) ?></td>
                                                <td><?= e($dutyRecord['academic_year'] . ' - ' . $dutyRecord['semester']) ?></td>
                                            </tr>
                                            <tr
                                                class="coordinator-assigned-requirement-details admin-management-duty-details"
                                                id="scholar-duty-files-<?= e($dutyRecord['id']) ?>"
                                                hidden
                                            >
                                                <td colspan="4">
                                                    <div class="coordinator-requirement-expanded-content">
                                                        <section>
                                                            <h4>Uploaded Files</h4>
                                                            <?php if ($dutyFiles): ?>
                                                                <ul class="duty-proof-file-list admin-management-duty-file-list" aria-label="Files uploaded for this duty record">
                                                                    <?php foreach ($dutyFiles as $dutyFile): ?>
                                                                        <li>
                                                                            <i class="fa-regular fa-file-lines duty-proof-file-icon" aria-hidden="true"></i>
                                                                            <a class="duty-proof-file-name" href="<?= BASE_URL ?>public/duty-proof.php?id=<?= e($dutyFile['id']) ?>" target="_blank" rel="noopener noreferrer"><?= e($dutyFile['original_filename']) ?></a>
                                                                            <small><?= e(number_format((int) $dutyFile['file_size'] / (1024 * 1024), 2)) ?> MB</small>
                                                                        </li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            <?php else: ?>
                                                                <p class="admin-management-duty-no-files">No files were uploaded for this duty record.</p>
                                                            <?php endif; ?>
                                                        </section>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="coordinator-scholar-duty-no-matches" hidden>
                                            <td colspan="4">No duty records match these filters.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                        <section class="scholar-report-detail-section coordinator-assigned-requirements-section">
                            <div class="coordinator-assigned-requirements-panel">
                                <h3>Assigned Requirements</h3>
                                <p class="coordinator-assigned-requirements-note">This scholar’s requirement list, current status, and submission link.</p>
                                <div class="table-scroll coordinator-assigned-requirements-scroll">
                                    <table class="data-table coordinator-assigned-requirements-table">
                                        <thead>
                                            <tr>
                                                <th>Requirement</th>
                                                <th>Status</th>
                                                <th>Period</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!$scholarRequirements): ?>
                                                <tr>
                                                    <td colspan="3">No requirements assigned yet.</td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php foreach ($scholarRequirements as $item): ?>
                                                <tr
                                                    class="coordinator-assigned-requirement-row"
                                                    tabindex="0"
                                                    data-requirement-expand
                                                    aria-expanded="false"
                                                    aria-controls="scholar-requirement-details-<?= e($item['id']) ?>"
                                                >
                                                    <td><?= e($item['name']) ?></td>
                                                    <td><span class="status-pill status-<?= e($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span></td>
                                                    <td><?= e($item['academic_year'] . ' - ' . $item['semester']) ?></td>
                                                </tr>
                                                <tr class="coordinator-assigned-requirement-details" id="scholar-requirement-details-<?= e($item['id']) ?>" hidden>
                                                    <td colspan="3">
                                                        <div class="coordinator-requirement-expanded-content">
                                                            <div class="coordinator-requirement-expanded-actions">
                                                                <?php if (!empty($item['rejection_reason'])): ?>
                                                                    <p class="coordinator-requirement-rejection-reason"><strong>Rejection reason:</strong> <?= e($item['rejection_reason']) ?></p>
                                                                <?php endif; ?>
                                                                <div class="coordinator-requirement-actions<?= $item['status'] === 'submitted' && $scholarWorkPeriod && (int) $scholarWorkPeriod['id'] === (int) $item['renewal_period_id'] ? ' has-review-actions' : '' ?>">
                                                                    <?php if (!empty($item['gdrive_link'])): ?>
                                                                        <a href="<?= e($item['gdrive_link']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-green requirement-submission-button">
                                                                            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                                                            Open GDrive
                                                                        </a>
                                                                    <?php else: ?>
                                                                        <p class="coordinator-requirement-no-submission">No submission has been provided.</p>
                                                                    <?php endif; ?>
                                                                    <?php if ($item['status'] === 'submitted' && $scholarWorkPeriod && (int) $scholarWorkPeriod['id'] === (int) $item['renewal_period_id']): ?>
                                                                        <form method="post" id="coordinator-requirement-review-form-<?= e($item['id']) ?>" class="coordinator-requirement-review-form" data-confirm-requirement-review>
                                                                            <input type="hidden" name="action" value="review">
                                                                            <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                                                            <input type="hidden" name="status" value="approved">
                                                                            <?php if ($selectedPeriod): ?><input type="hidden" name="return_period_id" value="<?= e($selectedPeriod['id']) ?>"><?php endif; ?>
                                                                            <?php if ($studentId !== ''): ?><input type="hidden" name="return_student_id" value="<?= e($studentId) ?>"><?php endif; ?>
                                                                            <button type="submit" class="coordinator-requirement-approve-button">
                                                                                <i class="fa-solid fa-check" aria-hidden="true"></i> Approve
                                                                            </button>
                                                                            <button type="button" class="coordinator-requirement-reject-button" data-requirement-reject>
                                                                                <i class="fa-solid fa-xmark" aria-hidden="true"></i> Reject
                                                                            </button>
                                                                        </form>
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
        </div>
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
        <?php if ($selectedPeriod): ?><input type="hidden" name="return_period_id" value="<?= e($selectedPeriod['id']) ?>"><?php endif; ?>
        <?php if ($studentId !== ''): ?><input type="hidden" name="return_student_id" value="<?= e($studentId) ?>"><?php endif; ?>
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
    'public/assets/js/scholars-search.js',
];
require __DIR__ . '/../../app/views/components/footer.php';
?>
