<?php

require_once __DIR__ . '/../app/bootstrap/error-handler.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /HKommunity/public/login.php');
    exit;
}