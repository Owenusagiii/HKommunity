<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../middleware/coordinator.php';
require_once __DIR__ . '/../../app/controllers/RenewalController.php';
require_once __DIR__ . '/../../app/models/Coordinator.php';

$me = array_values(array_filter((new Coordinator($pdo))->getAllCoordinators(), fn($x) => (int) $x['user_id'] === (int) $_SESSION['user_id']));

if (!$me)
    jsonResponse(['success' => false], 403);
$c = new RenewalController($pdo);
jsonResponse(['success' => true, 'items' => $c->getForCoordinatorYear($me[0]['year_level'])]);
