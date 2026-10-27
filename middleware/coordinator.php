<?php

require_once __DIR__ . '/auth.php';

if ($_SESSION['role'] !== 'coordinator') {
    http_response_code(403);
    die('Access denied.');
}