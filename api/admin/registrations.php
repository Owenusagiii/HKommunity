<?php

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../app/controllers/AdminRegistrationController.php';


$controller =
    new AdminRegistrationController($pdo);


/*
 * POST ACTIONS
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action =
        $_POST['action'] ?? '';

    $registrationId =
        (int) ($_POST['registration_id'] ?? 0);

    $adminUserId =
        (int) ($_SESSION['user_id'] ?? 0);


    /*
     * Validate common values.
     */
    if (
        $registrationId <= 0 ||
        $adminUserId <= 0
    ) {

        header(
            'Content-Type: application/json'
        );

        echo json_encode([
            'success' => false,
            'message' => 'Invalid registration request.'
        ]);

        exit;
    }


    /*
     * APPROVE REGISTRATION
     */
    if ($action === 'approve') {

        $success =
            $controller->approveRegistration(
                $registrationId,
                $adminUserId
            );


        header(
            'Content-Type: application/json'
        );


        if ($success) {

            echo json_encode([
                'success' => true,
                'message' =>
                    'Scholar registration approved successfully.'
            ]);

        } else {

            echo json_encode([
                'success' => false,
                'message' =>
                    'Unable to approve the scholar registration.'
            ]);
        }

        exit;
    }


    /*
     * REJECT REGISTRATION
     */
    if ($action === 'reject') {

        $rejectionReason =
            trim(
                $_POST['rejection_reason'] ?? ''
            );


        if ($rejectionReason === '') {

            header(
                'Content-Type: application/json'
            );

            echo json_encode([
                'success' => false,
                'message' =>
                    'Rejection reason is required.'
            ]);

            exit;
        }


        $success =
            $controller->rejectRegistration(
                $registrationId,
                $adminUserId,
                $rejectionReason
            );


        header(
            'Content-Type: application/json'
        );


        if ($success) {

            echo json_encode([
                'success' => true,
                'message' =>
                    'Scholar registration rejected successfully.'
            ]);

        } else {

            echo json_encode([
                'success' => false,
                'message' =>
                    'Unable to reject the scholar registration.'
            ]);
        }

        exit;
    }


    /*
     * Invalid POST action.
     */
    header(
        'Content-Type: application/json'
    );

    echo json_encode([
        'success' => false,
        'message' => 'Invalid action.'
    ]);

    exit;
}


/*
 * GET REGISTRATIONS
 */
$studentId =
    $_GET['student_id'] ?? null;

$status =
    $_GET['status'] ?? null;


$registrations =
    $controller->getRegistrations(
        $studentId,
        $status
    );


header(
    'Content-Type: application/json'
);


echo json_encode([
    'success' => true,
    'registrations' => $registrations
]);