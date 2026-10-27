<?php

require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/models/Renewal.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

function rejectScholarExport(string $message, int $statusCode): never
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=utf-8');
    exit($message);
}

function safeExportFilenamePart(string $value): string
{
    $safeValue = preg_replace('/[^A-Za-z0-9._-]+/', '_', $value);
    return trim((string) $safeValue, '._-');
}

function csvSafeValue(mixed $value): string
{
    $text = (string) $value;
    return preg_match('/^[\x00-\x20]*[=+\-@]/', $text) === 1
        ? "'" . $text
        : $text;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rejectScholarExport('Method not allowed.', 405);
}

if (!isValidCsrfToken(post('csrf_token'))) {
    rejectScholarExport('Invalid request token. Reload the Management page and try again.', 400);
}

$academicYearId = filter_var(
    post('academic_year_id'),
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$semesterId = filter_var(
    post('semester_id'),
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$status = post('renewal_status');
$format = post('format');

if (!is_int($academicYearId) || !is_int($semesterId)) {
    rejectScholarExport('Select a valid academic year and semester.', 400);
}

if (!is_string($status) || ($status !== 'all' && !in_array($status, Renewal::allowedStatuses(), true))) {
    rejectScholarExport('Select a valid renewal status.', 400);
}

if (!is_string($format) || !in_array($format, ['csv', 'xlsx'], true)) {
    rejectScholarExport('Select a valid file format.', 400);
}

$periodStmt = $pdo->prepare(
    'SELECT ay.academic_year, sm.semester
     FROM semesters sm
     JOIN academic_years ay ON ay.id = sm.academic_year_id
     WHERE sm.id = :semester_id
       AND ay.id = :academic_year_id'
);
$periodStmt->execute([
    'semester_id' => $semesterId,
    'academic_year_id' => $academicYearId,
]);
$period = $periodStmt->fetch(PDO::FETCH_ASSOC);

if (!$period) {
    rejectScholarExport('The selected semester does not belong to that academic year.', 400);
}

$sql = "SELECT sc.first_name, sc.middle_name, sc.last_name, sc.suffix,
               sc.student_id_number, sc.year_level, c.course_name,
       rn.status AS renewal_status,
               rp.id AS renewal_period_id,
               ay.academic_year, sm.semester
        FROM scholars sc
        JOIN courses c ON c.course_id = sc.course_id
        JOIN semesters sm ON sm.id = :semester_id
        JOIN academic_years ay
          ON ay.id = sm.academic_year_id
         AND ay.id = :academic_year_id
        LEFT JOIN renewal_periods rp
          ON rp.semester_id = sm.id
         AND rp.year_level = sc.year_level
        LEFT JOIN renewals rn
          ON rn.scholar_id = sc.id
         AND rn.renewal_period_id = rp.id";
$parameters = [
    'semester_id' => $semesterId,
    'academic_year_id' => $academicYearId,
];

if ($status === 'all') {
    $sql .= " WHERE rn.status IN ('approved', 'rejected', 'completed')";
} else {
    if ($status === 'not_started') {
        $sql .= ' WHERE (rn.status = :renewal_status OR (rp.id IS NOT NULL AND rn.id IS NULL))';
    } else {
        $sql .= ' WHERE rn.status = :renewal_status';
    }
    $parameters['renewal_status'] = $status;
}

$sql .= ' ORDER BY sc.last_name, sc.first_name, sc.student_id_number';
$exportStmt = $pdo->prepare($sql);
$exportStmt->execute($parameters);
$scholars = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

$headers = [
    'Scholar Name',
    'ID Number',
    'Year Level',
    'Course',
    'Status (for renewal)',
    'A.Y.',
    'Semester',
];
$statusLabels = Renewal::statusLabels();
$rows = [];

foreach ($scholars as $scholar) {
    $scholarName = fullName($scholar);
    if ($scholar['renewal_period_id'] === null) {
        $displayStatus = 'No Renewal Period';
    } else {
        $renewalStatus = $scholar['renewal_status'] ?? 'not_started';
        if (!isset($statusLabels[$renewalStatus])) {
            rejectScholarExport('A renewal record contains an unsupported status.', 500);
        }
        $displayStatus = $statusLabels[$renewalStatus];
    }

    $rows[] = [
        $scholarName,
        (string) $scholar['student_id_number'],
        (string) $scholar['year_level'],
        (string) $scholar['course_name'],
        $displayStatus,
        (string) $scholar['academic_year'],
        (string) $scholar['semester'],
    ];
}

$statusFilename = $status === 'all'
    ? 'All_Statuses'
    : safeExportFilenamePart($statusLabels[$status]);
$filename = implode('_', [
    'HKommunity',
    'Scholars',
    safeExportFilenamePart((string) $period['academic_year']),
    safeExportFilenamePart((string) $period['semester']),
    $statusFilename,
]) . '.' . $format;

if ($format === 'xlsx') {
    $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
    if (!is_file($autoloadPath)) {
        rejectScholarExport('Excel export is unavailable because PhpSpreadsheet is not installed.', 500);
    }
    require_once $autoloadPath;
    if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
        rejectScholarExport('Excel export is unavailable because PhpSpreadsheet is not installed.', 500);
    }
}

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Content-Disposition: attachment; filename="' . $filename . '"');

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    $output = fopen('php://output', 'wb');
    if ($output === false) {
        rejectScholarExport('Unable to create the CSV export.', 500);
    }

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $headers, ',', '"', '\\');
    foreach ($rows as $row) {
        fputcsv($output, array_map('csvSafeValue', $row), ',', '"', '\\');
    }
    fclose($output);
    exit;
}

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Scholars');

foreach ($headers as $columnIndex => $header) {
    $sheet->setCellValueExplicit(
        \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1) . '1',
        $header,
        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
    );
}

foreach ($rows as $rowIndex => $row) {
    foreach ($row as $columnIndex => $value) {
        $sheet->setCellValueExplicit(
            \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1) . ($rowIndex + 2),
            $value,
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
        );
    }
}

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
$writer->save('php://output');
exit;
