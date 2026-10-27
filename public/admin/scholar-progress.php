<?php

require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/models/Renewal.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

$academicYearsStmt = $pdo->query(
    'SELECT id, academic_year FROM academic_years ORDER BY academic_year DESC'
);
$academicYears = $academicYearsStmt->fetchAll(PDO::FETCH_ASSOC);
$semestersStmt = $pdo->query(
    "SELECT sm.id AS semester_id, sm.academic_year_id, sm.semester, ay.academic_year
     FROM semesters sm
     JOIN academic_years ay ON ay.id = sm.academic_year_id
     ORDER BY ay.academic_year DESC,
              FIELD(sm.semester, '1st Semester', '2nd Semester') DESC"
);
$semesters = $semestersStmt->fetchAll(PDO::FETCH_ASSOC);
$activePeriodStmt = $pdo->query(
    "SELECT sm.id AS semester_id, sm.academic_year_id, sm.semester, ay.academic_year
     FROM semesters sm
     JOIN academic_years ay ON ay.id = sm.academic_year_id
     WHERE sm.status = 'active' AND ay.status = 'active'
     ORDER BY ay.academic_year DESC,
              FIELD(sm.semester, '1st Semester', '2nd Semester') DESC
     LIMIT 1"
);
$activePeriod = $activePeriodStmt->fetch(PDO::FETCH_ASSOC) ?: null;
$requestedSemesterId = filter_var($_GET['semester_id'] ?? null, FILTER_VALIDATE_INT);
$selectedSemester = null;

if (is_int($requestedSemesterId)) {
    foreach ($semesters as $semester) {
        if ((int) $semester['semester_id'] === $requestedSemesterId) {
            $selectedSemester = $semester;
            break;
        }
    }
}

$selectedSemester ??= $activePeriod;
$selectedSemester ??= $semesters[0] ?? null;
$selectedPeriodLabel = $selectedSemester
    ? $selectedSemester['academic_year'] . ' · ' . $selectedSemester['semester']
    : 'No semester selected';

$scholarsStmt = $pdo->prepare(
    "SELECT sc.id, sc.student_id_number, sc.first_name, sc.middle_name,
            sc.last_name, sc.suffix, sc.year_level, u.phinmaed_email,
            rp.id AS renewal_period_id, rp.required_duty_hours,
            COALESCE(req.assigned_count, 0) AS assigned_requirement_count,
            COALESCE(req.approved_count, 0) AS approved_requirement_count,
            COALESCE(hours.rendered_hours, 0) AS rendered_duty_hours
     FROM scholars sc
     JOIN users u ON u.id = sc.user_id
     LEFT JOIN renewal_periods rp
        ON rp.year_level = sc.year_level
       AND rp.semester_id = :semester_id
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
     WHERE u.role = 'scholar' AND u.status = 'active'
     ORDER BY sc.last_name, sc.first_name, sc.student_id_number"
);
$scholarsStmt->execute(['semester_id' => (int) ($selectedSemester['semester_id'] ?? 0)]);
$scholars = $scholarsStmt->fetchAll(PDO::FETCH_ASSOC);

$requirementDetailsStmt = $pdo->prepare(
    "SELECT sr.id AS scholar_requirement_id, sr.scholar_id, sr.status,
            sr.gdrive_link, sr.rejection_reason,
            r.name, r.description, sm.semester, ay.academic_year
     FROM scholar_requirements sr
     JOIN requirements r ON r.id = sr.requirement_id
     JOIN renewal_periods rp ON rp.id = sr.renewal_period_id
     JOIN semesters sm ON sm.id = rp.semester_id
     JOIN academic_years ay ON ay.id = sm.academic_year_id
     WHERE sm.id = :semester_id
     ORDER BY r.name"
);
$requirementDetailsStmt->execute(['semester_id' => (int) ($selectedSemester['semester_id'] ?? 0)]);
$requirementsByScholar = [];
foreach ($requirementDetailsStmt->fetchAll(PDO::FETCH_ASSOC) as $requirement) {
    $requirementsByScholar[(int) $requirement['scholar_id']][] = $requirement;
}

$dutyDetailsStmt = $pdo->prepare(
    "SELECT dr.id AS duty_record_id, dr.scholar_id, dr.duty_date,
            dr.duty_type, dr.hours, dr.created_at
     FROM duty_records dr
     JOIN renewal_periods rp ON rp.id = dr.renewal_period_id
     WHERE rp.semester_id = :semester_id
     ORDER BY dr.duty_date DESC, dr.id DESC"
);
$dutyDetailsStmt->execute(['semester_id' => (int) ($selectedSemester['semester_id'] ?? 0)]);
$dutyRecordsByScholar = [];
$dutyRecordIds = [];
foreach ($dutyDetailsStmt->fetchAll(PDO::FETCH_ASSOC) as $dutyRecord) {
    $dutyRecordsByScholar[(int) $dutyRecord['scholar_id']][] = $dutyRecord;
    $dutyRecordIds[(int) $dutyRecord['duty_record_id']] = true;
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

$completedRequirements = 0;
$completedDutyHours = 0;
$completedBoth = 0;
$yearLevelColors = [
    '1st Year' => '#4CAF50',
    '2nd Year' => '#F44336',
    '3rd Year' => '#2196F3',
    '4th Year' => '#FFC107',
    '5th Year' => '#9C27B0',
];
$yearLevelProgress = array_fill_keys(
    array_keys($yearLevelColors),
    ['scholars' => 0, 'complete' => 0]
);
$activeScholarCount = count($scholars);

foreach ($scholars as $scholar) {
    $yearLevel = (string) $scholar['year_level'];
    $hasRequirements = (int) $scholar['assigned_requirement_count'] > 0;
    $requirementsComplete = $hasRequirements
        && (int) $scholar['approved_requirement_count'] >= (int) $scholar['assigned_requirement_count'];
    $hasDutyTarget = $scholar['renewal_period_id'] !== null;
    $dutyComplete = $hasDutyTarget
        && (float) $scholar['rendered_duty_hours'] >= (float) $scholar['required_duty_hours'];
    $fullyComplete = $requirementsComplete && $dutyComplete;

    $completedRequirements += (int) $requirementsComplete;
    $completedDutyHours += (int) $dutyComplete;
    $completedBoth += (int) $fullyComplete;

    if (isset($yearLevelProgress[$yearLevel])) {
        $yearLevelProgress[$yearLevel]['scholars']++;
        $yearLevelProgress[$yearLevel]['complete'] += (int) $fullyComplete;
    }
}
$progressMetrics = [
    [
        'label' => 'Requirements Complete',
        'value' => $completedRequirements,
        'description' => 'Scholars with all assigned requirements approved',
    ],
    [
        'label' => 'Duty Hours Complete',
        'value' => $completedDutyHours,
        'description' => 'Scholars who rendered their required duty hours',
    ],
    [
        'label' => 'Fully Complete',
        'value' => $completedBoth,
        'description' => 'Scholars who completed both requirements and duty hours',
    ],
];
foreach ($progressMetrics as &$progressMetric) {
    $progressMetric['percentage'] = $activeScholarCount > 0
        ? (int) round(($progressMetric['value'] / $activeScholarCount) * 100)
        : 0;
}
unset($progressMetric);

$pageTitle = 'Management';
require __DIR__ . '/../../app/views/components/header.php';
?>
<main class="admin-scholar-progress-main">
    <section class="duty-records-content admin-users-page admin-scholar-progress-page">
        <div class="page-head duty-page-head">
            <div>
                <h1>Management</h1>
            </div>
            <div class="duty-info-banner">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>
                    <?= $selectedSemester
                        ? 'Showing progress for ' . e($selectedSemester['academic_year'] . ' · ' . $selectedSemester['semester']) . '.'
                        : 'No academic semesters are available to display.' ?>
                </span>
            </div>
        </div>

        <section class="admin-year-progress-card admin-scholar-progress-year-card" aria-labelledby="scholar-progress-year-summary-title">
            <div class="admin-scholar-progress-records-intro">
                <h2 class="admin-users-section-title">
                    <span class="admin-users-section-icon"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i></span>
                    Scholar Progress Records
                </h2>
            </div>
            <div class="admin-year-progress-head">
                <h2 id="scholar-progress-year-summary-title">Year-Level Completion</h2>
                <label class="admin-scholar-progress-period-select">
                    <span>Academic Year &amp; Semester</span>
                    <select
                        id="scholar-progress-semester"
                        name="semester_id"
                        aria-label="Academic Year and Semester"
                        required
                        onchange="window.location.href = `?semester_id=${encodeURIComponent(this.value)}`"
                    >
                        <?php foreach ($semesters as $semester): ?>
                            <option
                                value="<?= e($semester['semester_id']) ?>"
                                <?= $selectedSemester && (int) $selectedSemester['semester_id'] === (int) $semester['semester_id'] ? 'selected' : '' ?>
                            >
                                <?= e($semester['academic_year'] . ' · ' . $semester['semester']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <h2 class="admin-year-progress-section-title">Overall</h2>
            <div class="admin-year-progress-metrics" aria-label="Overall scholar progress">
                <?php foreach ($progressMetrics as $progressMetric): ?>
                    <div class="admin-year-progress-metric">
                        <div class="admin-year-progress-metric-head">
                            <div>
                                <h3><?= e($progressMetric['label']) ?></h3>
                                <p><?= e($progressMetric['description']) ?></p>
                            </div>
                            <strong>
                                <?= e($progressMetric['value']) ?>
                                /
                                <?= e($activeScholarCount) ?>
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
            <div class="admin-year-progress-grid">
                <?php foreach ($yearLevelColors as $yearLevel => $yearColor): ?>
                    <?php
                    $scholarCount = $yearLevelProgress[$yearLevel]['scholars'];
                    $completedCount = $yearLevelProgress[$yearLevel]['complete'];
                    $completionPercentage = $scholarCount > 0
                        ? (int) round(($completedCount / $scholarCount) * 100)
                        : 0;
                    ?>
                    <article class="admin-year-progress-item">
                        <div
                            class="admin-year-progress-circle"
                            role="img"
                            aria-label="<?= e($yearLevel . ': ' . $completionPercentage . '% complete') ?>"
                            style="--year-progress: <?= e($completionPercentage) ?>; --year-progress-color: <?= e($yearColor) ?>;"
                        >
                            <span><?= e($completionPercentage) ?>%</span>
                        </div>
                        <h3><?= e($yearLevel) ?></h3>
                        <p><strong><?= e($completedCount) ?> / <?= e($scholarCount) ?></strong> scholars complete</p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="records-card admin-users-card admin-scholar-progress-records" aria-labelledby="scholar-progress-records-title">
            <div class="records-head">
                <div>
                    <h2 class="admin-users-section-title" id="scholar-progress-records-title">
                        <span class="admin-users-section-icon"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i></span>
                        Scholar Progress Records
                    </h2>
                    <p class="sub-small">Approved requirements and duty records for the selected semester.</p>
                </div>
                <div class="admin-scholar-progress-tools">
                    <span class="record-count"><span data-scholar-progress-count><?= e(count($scholars)) ?></span> scholars</span>
                </div>
            </div>

            <div class="admin-scholar-progress-period-filter" id="admin-scholar-progress-period-filter">
                <div class="form-row">
                    <label for="scholar-progress-year-level">Year Level</label>
                    <select id="scholar-progress-year-level" data-scholar-progress-year-filter>
                        <option value="">All year levels</option>
                        <?php foreach (allowedYearLevels() as $yearLevel): ?>
                            <option value="<?= e($yearLevel) ?>"><?= e($yearLevel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label for="scholar-progress-status">Status</label>
                    <select id="scholar-progress-status" data-scholar-progress-status-filter>
                        <option value="">All statuses</option>
                        <option value="complete">Complete</option>
                        <option value="in-progress">In Progress</option>
                        <option value="not-ready">Not Ready</option>
                    </select>
                </div>
                <div class="form-row">
                    <label for="scholar-progress-id-filter">Student ID</label>
                    <div class="requirement-search admin-scholar-progress-search">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input
                            id="scholar-progress-id-filter"
                            type="search"
                            aria-label="Search by student ID number"
                            placeholder="Search by student ID"
                            data-scholar-progress-id-filter
                        >
                    </div>
                </div>
            </div>

            <div class="table-scroll admin-scholar-progress-table-scroll">
                <table class="data-table admin-scholar-progress-table">
                    <thead>
                        <tr>
                            <th>Scholar</th>
                            <th>Year Level</th>
                            <th>Requirements</th>
                            <th>Duty Hours</th>
                            <th>Overall Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scholars as $scholar): ?>
                            <?php
                            $assignedCount = (int) $scholar['assigned_requirement_count'];
                            $approvedCount = (int) $scholar['approved_requirement_count'];
                            $requiredHours = (float) ($scholar['required_duty_hours'] ?? 0);
                            $renderedHours = (float) $scholar['rendered_duty_hours'];
                            $hasRequirements = $assignedCount > 0;
                            $hasDutyTarget = $scholar['renewal_period_id'] !== null;
                            $requirementsComplete = $hasRequirements && $approvedCount >= $assignedCount;
                            $dutyComplete = $hasDutyTarget && $renderedHours >= $requiredHours;
                            $requirementsProgress = $hasRequirements
                                ? (int) min(100, round(($approvedCount / $assignedCount) * 100))
                                : 0;
                            $dutyProgress = $hasDutyTarget && $requiredHours > 0
                                ? (int) min(100, round(($renderedHours / $requiredHours) * 100))
                                : ($hasDutyTarget ? 100 : 0);
                            $scholarName = strtoupper(trim(implode(', ', array_filter([
                                trim((string) $scholar['last_name']),
                                trim((string) $scholar['first_name']),
                                trim((string) $scholar['middle_name']),
                                trim((string) $scholar['suffix']),
                            ], static fn (string $part): bool => $part !== ''))));
                            $progressStatus = $requirementsComplete && $dutyComplete
                                ? 'complete'
                                : ((!$hasRequirements || !$hasDutyTarget) ? 'not-ready' : 'in-progress');
                            ?>
                            <tr
                                data-scholar-progress-row
                                data-student-id="<?= e($scholar['student_id_number']) ?>"
                                data-year-level="<?= e($scholar['year_level']) ?>"
                                data-progress-status="<?= e($progressStatus) ?>"
                                data-requirements-complete="<?= $requirementsComplete ? '1' : '0' ?>"
                                data-duty-complete="<?= $dutyComplete ? '1' : '0' ?>"
                            >
                                <td>
                                    <strong class="admin-scholar-progress-name"><?= e($scholarName) ?></strong>
                                    <small><?= e($scholar['student_id_number']) ?></small>
                                </td>
                                <td><?= e($scholar['year_level']) ?></td>
                                <td
                                    class="admin-scholar-progress-clickable-cell"
                                    role="button"
                                    tabindex="0"
                                        data-management-dialog="management-requirements-<?= e($scholar['id']) ?>"
                                        aria-haspopup="dialog"
                                        aria-controls="management-requirements-<?= e($scholar['id']) ?>"
                                        aria-label="View <?= e($assignedCount) ?> requirements for <?= e($scholarName) ?>"
                                    >
                                        <?php if ($hasRequirements): ?>
                                            <div class="admin-scholar-progress-value"><?= e($approvedCount) ?> of <?= e($assignedCount) ?> approved</div>
                                            <div class="admin-scholar-progress-bar" role="progressbar" aria-label="<?= e($scholarName) ?> requirement progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= e($requirementsProgress) ?>">
                                                <span class="<?= $requirementsComplete ? 'is-complete' : '' ?>" style="width: <?= e($requirementsProgress) ?>%"></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="admin-scholar-progress-muted"><?= $hasDutyTarget ? 'No requirements assigned' : 'No active period' ?></span>
                                        <?php endif; ?>
                                </td>
                                <td
                                    class="admin-scholar-progress-clickable-cell"
                                    role="button"
                                    tabindex="0"
                                        data-management-dialog="management-duty-<?= e($scholar['id']) ?>"
                                        aria-haspopup="dialog"
                                        aria-controls="management-duty-<?= e($scholar['id']) ?>"
                                        aria-label="View duty records for <?= e($scholarName) ?>"
                                    >
                                        <?php if ($hasDutyTarget): ?>
                                            <div class="admin-scholar-progress-value"><?= e(number_format($renderedHours, 2)) ?> / <?= e(number_format($requiredHours, 2)) ?> hours</div>
                                            <div class="admin-scholar-progress-bar" role="progressbar" aria-label="<?= e($scholarName) ?> duty-hour progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= e($dutyProgress) ?>">
                                                <span class="<?= $dutyComplete ? 'is-complete' : '' ?>" style="width: <?= e($dutyProgress) ?>%"></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="admin-scholar-progress-muted">No active period</span>
                                        <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($requirementsComplete && $dutyComplete): ?>
                                        <span class="status-pill status-active">Complete</span>
                                    <?php elseif (!$hasRequirements || !$hasDutyTarget): ?>
                                        <span class="status-pill status-pending">Not ready</span>
                                    <?php else: ?>
                                        <span class="status-pill status-inactive">In progress</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr data-scholar-progress-empty hidden><td colspan="5">No scholars match the current filters or student ID search.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <?php foreach ($scholars as $scholar): ?>
            <?php
            $scholarId = (int) $scholar['id'];
            $dialogName = strtoupper(trim(implode(', ', array_filter([
                trim((string) $scholar['last_name']),
                trim((string) $scholar['first_name']),
                trim((string) $scholar['middle_name']),
                trim((string) $scholar['suffix']),
            ], static fn (string $part): bool => $part !== ''))));
            $scholarRequirements = $requirementsByScholar[$scholarId] ?? [];
            $scholarDutyRecords = $dutyRecordsByScholar[$scholarId] ?? [];
            $approvedRequirementCount = count(array_filter(
                $scholarRequirements,
                static fn (array $requirement): bool => $requirement['status'] === 'approved'
            ));
            $requiredDutyHours = (float) ($scholar['required_duty_hours'] ?? 0);
            $renderedDutyHours = (float) $scholar['rendered_duty_hours'];
            $dutyCompletionPercent = $scholar['renewal_period_id'] !== null
                ? ($requiredDutyHours > 0
                    ? (int) min(100, round(($renderedDutyHours / $requiredDutyHours) * 100))
                    : 100)
                : 0;
            ?>
            <dialog
                class="coordinator-record-dialog scholar-report-dialog coordinator-duty-review-drawer admin-management-detail-dialog"
                id="management-requirements-<?= e($scholarId) ?>"
                aria-labelledby="management-requirements-title-<?= e($scholarId) ?>"
            >
                <div class="scholar-report-dialog-head">
                    <div>
                        <span class="scholar-report-dialog-eyebrow">Requirements</span>
                        <h2 id="management-requirements-title-<?= e($scholarId) ?>"><?= e($dialogName) ?></h2>
                        <span class="coordinator-dialog-student-id"><?= e($scholar['student_id_number']) ?></span>
                    </div>
                    <button type="button" class="scholar-report-dialog-close" aria-label="Close requirements">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="scholar-report-dialog-content">
                    <section class="duty-submission-review-card coordinator-scholar-duty-card">
                        <div class="coordinator-scholar-duty-card-head">
                            <div>
                                <h3>Assigned Requirements</h3>
                                <p class="duty-submission-review-note">
                                    <?= e(count($scholarRequirements)) ?> <?= count($scholarRequirements) === 1 ? 'record' : 'records' ?>
                                    · <?= e($approvedRequirementCount) ?> approved
                                    · <?= e($selectedPeriodLabel) ?>
                                </p>
                            </div>
                        </div>
                        <?php if ($scholarRequirements): ?>
                            <div class="table-scroll admin-management-dialog-table-scroll">
                                <table class="data-table admin-management-dialog-table">
                                    <thead>
                                        <tr>
                                            <th>Requirement</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($scholarRequirements as $requirement): ?>
                                            <?php $requirementDetailsId = 'management-requirement-details-' . $scholarId . '-' . (int) $requirement['scholar_requirement_id']; ?>
                                            <tr
                                                class="coordinator-assigned-requirement-row"
                                                data-management-expand-row
                                                tabindex="0"
                                                aria-expanded="false"
                                                aria-controls="<?= e($requirementDetailsId) ?>"
                                            >
                                                <td><?= e($requirement['name']) ?></td>
                                                <td><span class="status-pill status-<?= e($requirement['status']) ?>"><?= e(ucfirst((string) $requirement['status'])) ?></span></td>
                                            </tr>
                                            <tr class="coordinator-assigned-requirement-details" id="<?= e($requirementDetailsId) ?>" hidden>
                                                <td colspan="2">
                                                    <div class="coordinator-requirement-expanded-content">
                                                        <section>
                                                            <h4>Description</h4>
                                                            <?php if (trim((string) $requirement['description']) !== ''): ?>
                                                                <p><?= nl2br(e($requirement['description'])) ?></p>
                                                            <?php else: ?>
                                                                <p class="coordinator-requirement-no-submission">No description provided.</p>
                                                            <?php endif; ?>
                                                        </section>
                                                        <section>
                                                            <h4>Review</h4>
                                                            <?php if (trim((string) ($requirement['rejection_reason'] ?? '')) !== ''): ?>
                                                                <p class="coordinator-requirement-rejection-reason"><strong>Feedback:</strong> <?= e($requirement['rejection_reason']) ?></p>
                                                            <?php else: ?>
                                                                <p class="requirement-no-action">No review feedback.</p>
                                                            <?php endif; ?>
                                                        </section>
                                                        <section>
                                                            <h4>Submission</h4>
                                                            <?php if (trim((string) ($requirement['gdrive_link'] ?? '')) !== ''): ?>
                                                                <a href="<?= e($requirement['gdrive_link']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-green requirement-submission-button">
                                                                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                                                    Open submission
                                                                </a>
                                                            <?php else: ?>
                                                                <p class="coordinator-requirement-no-submission">No submission has been provided.</p>
                                                            <?php endif; ?>
                                                        </section>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>No requirements are assigned for this semester.</p>
                        <?php endif; ?>
                    </section>
                </div>
            </dialog>

            <dialog
                class="coordinator-record-dialog scholar-report-dialog coordinator-duty-review-drawer admin-management-detail-dialog"
                id="management-duty-<?= e($scholarId) ?>"
                aria-labelledby="management-duty-title-<?= e($scholarId) ?>"
            >
                <div class="scholar-report-dialog-head">
                    <div>
                        <span class="scholar-report-dialog-eyebrow">Duty Records</span>
                        <h2 id="management-duty-title-<?= e($scholarId) ?>"><?= e($dialogName) ?></h2>
                        <span class="coordinator-dialog-student-id"><?= e($scholar['student_id_number']) ?></span>
                    </div>
                    <button type="button" class="scholar-report-dialog-close" aria-label="Close duty records">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="scholar-report-dialog-content">
                    <section class="duty-submission-review-card coordinator-scholar-duty-card">
                        <div class="coordinator-scholar-duty-card-head">
                            <div>
                                <h3>Duty Records</h3>
                                <p class="duty-submission-review-note">
                                    <span data-management-duty-count><?= e(count($scholarDutyRecords)) ?></span>
                                    <span data-management-duty-count-label><?= count($scholarDutyRecords) === 1 ? 'record' : 'records' ?></span>
                                    · <?= e($selectedPeriodLabel) ?>
                                    · <?= e($dutyCompletionPercent) ?>% complete
                                </p>
                            </div>
                            <div class="coordinator-scholar-duty-filters">
                                <label class="duty-record-filter coordinator-scholar-duty-date-filter">
                                    <span>Date</span>
                                    <select data-management-duty-date-filter aria-label="Filter duty records by recorded date">
                                        <option value="all">All</option>
                                        <option value="latest">Latest</option>
                                        <option value="last-week">Last week</option>
                                        <option value="last-month">Last month</option>
                                    </select>
                                </label>
                            </div>
                        </div>
                        <div class="table-scroll admin-management-dialog-table-scroll">
                            <table class="data-table admin-management-dialog-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Duty Type</th>
                                        <th>Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($scholarDutyRecords as $dutyRecord): ?>
                                        <tr
                                            data-management-duty-row
                                            data-recorded-date="<?= e((new DateTime($dutyRecord['created_at']))->format('Y-m-d')) ?>"
                                            tabindex="0"
                                            aria-expanded="false"
                                            aria-controls="management-duty-files-<?= e($dutyRecord['duty_record_id']) ?>"
                                        >
                                            <td>
                                                <?= e($dutyRecord['duty_date']) ?>
                                                <?php $dutyFiles = $dutyFilesByRecord[(int) $dutyRecord['duty_record_id']] ?? []; ?>
                                                <div class="coordinator-scholar-duty-record-meta">
                                                    Recorded at: <?= e((new DateTime($dutyRecord['created_at']))->format('g:i A')) ?>
                                                </div>
                                            </td>
                                            <td><?= e($dutyRecord['duty_type']) ?></td>
                                            <td><?= e(number_format((float) $dutyRecord['hours'], 2)) ?></td>
                                        </tr>
                                        <tr
                                            class="coordinator-assigned-requirement-details admin-management-duty-details"
                                            id="management-duty-files-<?= e($dutyRecord['duty_record_id']) ?>"
                                            hidden
                                        >
                                            <td colspan="3">
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
                                    <tr class="coordinator-scholar-duty-no-matches" data-management-duty-empty hidden>
                                        <td colspan="3">No duty records match this date filter.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </dialog>
        <?php endforeach; ?>

        <section class="records-card admin-users-card admin-scholar-export-card" aria-labelledby="scholar-export-title">
            <div class="records-head">
                <div>
                    <h2 class="admin-users-section-title" id="scholar-export-title">
                        <span class="admin-users-section-icon"><i class="fa-solid fa-file-export" aria-hidden="true"></i></span>
                        Export Scholar Records
                    </h2>
                    <p class="sub-small">Download scholar details and renewal statuses for a selected academic period.</p>
                </div>
            </div>
            <form class="admin-scholar-export-form" method="post" action="<?= e(BASE_URL . 'admin/scholar-export') ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <div class="form-row">
                    <label for="scholar-export-academic-year">Academic Year</label>
                    <select
                        id="scholar-export-academic-year"
                        name="academic_year_id"
                        data-scholar-export-year
                        required
                        <?= $academicYears ? '' : 'disabled' ?>
                    >
                        <?php if (!$academicYears): ?>
                            <option value="">No academic years available</option>
                        <?php endif; ?>
                        <?php foreach ($academicYears as $academicYear): ?>
                            <option
                                value="<?= e($academicYear['id']) ?>"
                                <?= $selectedSemester && (int) $selectedSemester['academic_year_id'] === (int) $academicYear['id'] ? 'selected' : '' ?>
                            >
                                <?= e($academicYear['academic_year']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label for="scholar-export-semester">Semester</label>
                    <select
                        id="scholar-export-semester"
                        name="semester_id"
                        data-scholar-export-semester
                        required
                        <?= $semesters ? '' : 'disabled' ?>
                    >
                        <option value="" disabled <?= !$selectedSemester ? 'selected' : '' ?>>Select a semester</option>
                        <?php foreach ($semesters as $semester): ?>
                            <option
                                value="<?= e($semester['semester_id']) ?>"
                                data-academic-year-id="<?= e($semester['academic_year_id']) ?>"
                                <?= $selectedSemester && (int) $selectedSemester['semester_id'] === (int) $semester['semester_id'] ? 'selected' : '' ?>
                            >
                                <?= e($semester['semester']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label for="scholar-export-status">Renewal Status</label>
                    <select id="scholar-export-status" name="renewal_status" required>
                        <option value="all">All Statuses</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div class="form-row">
                    <label for="scholar-export-format">File Format</label>
                    <select id="scholar-export-format" name="format" required>
                        <option value="csv">CSV (.csv)</option>
                        <option value="xlsx">Excel (.xlsx)</option>
                    </select>
                </div>
                <button
                    class="btn btn-green"
                    type="submit"
                    data-confirm-admin-action
                    data-confirm-title="Export scholar records?"
                    data-confirm-description="Are you sure you want to export scholar records with the selected academic year, semester, renewal status, and file format?"
                    data-confirm-submit-label="Export"
                    <?= $semesters ? '' : 'disabled' ?>
                >
                    <i class="fa-solid fa-download" aria-hidden="true"></i>
                    Export
                </button>
            </form>
        </section>
        <dialog class="logout-confirm-dialog admin-record-save-confirm-dialog" id="admin-action-confirm-dialog" aria-labelledby="admin-action-confirm-title" aria-describedby="admin-action-confirm-description">
            <div class="logout-confirm-icon"><i class="fa-solid fa-circle-question" aria-hidden="true"></i></div>
            <h2 id="admin-action-confirm-title">Export scholar records?</h2>
            <p id="admin-action-confirm-description">Are you sure you want to export scholar records with the selected filters?</p>
            <div class="logout-confirm-actions">
                <button type="button" class="logout-confirm-cancel" data-admin-action-cancel>Cancel</button>
                <button type="button" class="logout-confirm-submit" data-admin-action-confirm>Export</button>
            </div>
        </dialog>
    </section>
</main>
<?php
$pageScripts = [
    'public/assets/js/admin-scholar-progress-filters.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-scholar-progress-filters.js'),
    'public/assets/js/admin-scholar-export-filters.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-scholar-export-filters.js'),
    'public/assets/js/admin-action-confirmation.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-action-confirmation.js'),
    'public/assets/js/admin-management-popouts.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-management-popouts.js'),
];
require __DIR__ . '/../../app/views/components/footer.php';
?>
