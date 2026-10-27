<?php

require_once __DIR__ . '/../../middleware/admin.php';

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/controllers/CoordinatorController.php';

header('Content-Type: application/json');

$coordinatorController = new CoordinatorController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $name = trim($_GET['name'] ?? '');
    $yearLevel = trim($_GET['year_level'] ?? '');

    if ($name !== '' && $yearLevel !== '') {

        $coordinators = $coordinatorController
            ->searchAndFilterCoordinators(
                $name,
                $yearLevel
            );

    } elseif ($name !== '') {

        $coordinators = $coordinatorController
            ->searchCoordinators($name);

    } elseif ($yearLevel !== '') {

        $coordinators = $coordinatorController
            ->filterCoordinatorsByYearLevel(
                $yearLevel
            );

    } else {

        $coordinators = $coordinatorController
            ->getCoordinators();

    }

    echo json_encode([
        'success' => true,
        'coordinators' => $coordinators
    ]);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'update_year_level') {

        $coordinatorId = (int) ($_POST['coordinator_id'] ?? 0);
        $yearLevel = trim($_POST['year_level'] ?? '');

        if ($coordinatorId <= 0) {

            echo json_encode([
                'success' => false,
                'message' => 'Invalid coordinator.'
            ]);

            exit;
        }

        $result = $coordinatorController
            ->updateAssignedYearLevel(
                $coordinatorId,
                $yearLevel
            );

        echo json_encode($result);

        exit;
    }

    if ($action === 'update_status') {

        $userId = (int) ($_POST['user_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');

        if ($userId <= 0) {

            echo json_encode([
                'success' => false,
                'message' => 'Invalid coordinator account.'
            ]);

            exit;
        }

        $result = $coordinatorController
            ->updateStatus(
                $userId,
                $status
            );

        echo json_encode($result);

        exit;
    }

    echo json_encode([
        'success' => false,
        'message' => 'Invalid action.'
    ]);

    exit;
}

http_response_code(405);

echo json_encode([
    'success' => false,
    'message' => 'Method not allowed.'
]);