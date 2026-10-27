<?php

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../app/models/RegistrationImport.php';
require_once __DIR__ . '/../../app/models/ScholarRegistration.php';

$model = new RegistrationImport($pdo);
$registrationModel = new ScholarRegistration($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = postString('status');
    $importId = (int) ($_POST['id'] ?? 0);

    if (!in_array($status, ['approved', 'rejected'], true) || $importId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid import action.'], 400);
    }

    $ok = $model->updateImportStatus($importId, $status);

    if ($ok && $status === 'approved') {
        $model->updateExistingScholarYearLevels($importId);

        $stmt = $pdo->query(
            "SELECT id FROM scholar_registrations WHERE status = 'pending'"
        );

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $registrationId) {
            $registrationModel->verifyRegistration((int) $registrationId);
        }
    }

    jsonResponse([
        'success' => $ok,
        'message' => $ok ? 'Import status updated.' : 'Unable to update import status.'
    ], $ok ? 200 : 400);
}

jsonResponse([
    'success' => true,
    'items' => $model->all()
]);
