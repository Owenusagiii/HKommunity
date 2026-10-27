<?php

require_once __DIR__ . '/../config/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role'] ?? '') {
        case 'admin':
            header('Location: ' . BASE_URL . 'admin/dashboard');
            exit;
        case 'coordinator':
            header('Location: ' . BASE_URL . 'coordinator/dashboard');
            exit;
        case 'scholar':
            header('Location: ' . BASE_URL . 'scholar/dashboard');
            exit;
        default:
            session_destroy();
            header('Location: ' . BASE_URL . 'login');
            exit;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Hawak Kamay Scholar Portal</title>
    <link rel="icon" type="image/jpeg" href="<?= BASE_URL ?>public/assets/images/logo.jpg">
    <link rel="stylesheet" href="<?= BASE_URL ?>public/assets/css/lanlogreg.css">
</head>
<body>
    <div class="page">
        <a class="logo" href="<?= BASE_URL ?>" aria-label="Go to HKommunity landing page">
            <img src="<?= BASE_URL ?>public/assets/images/logo.jpg" alt="<?= APP_NAME ?> logo">
        </a>
        <div class="dots one"></div>
        <div class="dots two"></div>
        <div class="accent-pill"></div>

        <main class="landing-layout">
            <?php require __DIR__ . '/../app/views/auth/landing-brand.php'; ?>

            <section class="auth-card landing-auth-card">
                <h2>Welcome to<br><strong><?= APP_NAME ?>!</strong></h2>
                <p>Your HK scholarship journey, all in one place.</p>
                <a href="<?= BASE_URL ?>public/login.php" class="btn btn-green">LOG IN</a>
                <a href="<?= BASE_URL ?>public/register.php" class="btn btn-outline">REGISTER</a>
            </section>
        </main>
    </div>
</body>
</html>
