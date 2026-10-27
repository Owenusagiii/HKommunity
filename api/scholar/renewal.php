<?php

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../middleware/scholar.php';
require_once __DIR__ . '/../../app/controllers/ScholarController.php';
require_once __DIR__ . '/../../app/controllers/RenewalController.php';

$scholar = (new ScholarController($pdo))->findByUserId((int) $_SESSION['user_id']);

if (!$scholar) {
    jsonResponse(['success' => false, 'message' => 'Scholar profile not found.'], 403);
}

$controller = new RenewalController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && postString('action') === 'start') {
    jsonResponse([
        'success' => false,
        'message' => 'Renewal cycles are started by the Coordinator after eligibility review.'
    ], 403);
}

jsonResponse([
    'success' => true,
    'items' => $controller->getForScholar((int) $scholar['id'])
]);
