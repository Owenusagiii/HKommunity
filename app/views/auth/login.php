<?php

require_once __DIR__ . '/../../../config/app.php';

$error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
$identifier = $_SESSION['login_identifier'] ?? '';
unset($_SESSION['login_identifier']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Hawak Kamay Scholar Portal</title>
    <link rel="icon" type="image/jpeg" href="<?= BASE_URL ?>public/assets/images/logo.jpg">
    <link rel="stylesheet" href="<?= BASE_URL ?>public/assets/css/lanlogreg.css?v=2">
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
            <?php require __DIR__ . '/landing-brand.php'; ?>

            <section class="auth-card">
                <h2>Welcome to<br><strong><?= APP_NAME ?>!</strong></h2>
                <p>Your HK scholarship journey, all in one place.</p>

                <?php if ($error): ?>
                    <div class="auth-flash-container" aria-live="assertive">
                        <div class="flash-message error" role="alert" data-auto-dismiss>
                            <span class="auth-flash-icon" aria-hidden="true">!</span>
                            <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                            <button class="flash-message-close" type="button" aria-label="Dismiss notification">&times;</button>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>login" class="login-form">
                    <div class="form-group">
                        <label for="identifier">PHINMAED Email or Student ID Number</label>
                        <input type="text" id="identifier" name="identifier" value="<?= htmlspecialchars($identifier, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="username">
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="password-field">
                            <input type="password" id="password" name="password" required autocomplete="current-password">
                            <button type="button" class="password-toggle" data-target="password" aria-label="Show password"></button>
                        </div>
                    </div>

                    <button type="button" class="forgot-password-link" data-forgot-password>
                        Forgot password?
                    </button>

                    <button type="submit" class="btn btn-green">LOG IN</button>
                </form>

                <a href="<?= BASE_URL ?>public/register.php" class="btn btn-outline">REGISTER</a>
            </section>
        </main>
    </div>

    <dialog class="forgot-password-dialog" id="forgot-password-dialog" aria-labelledby="forgot-password-title" aria-describedby="forgot-password-message">
        <div class="forgot-password-icon" aria-hidden="true">?</div>
        <h2 id="forgot-password-title">Password help</h2>
        <p id="forgot-password-message">Password recovery is not available yet. Please contact your portal administrator for help accessing your account.</p>
        <button type="button" class="btn btn-green" data-forgot-password-close>Got it</button>
    </dialog>

    <script src="<?= BASE_URL ?>public/assets/js/password-toggle.js" defer></script>
    <script src="<?= BASE_URL ?>public/assets/js/flash-messages.js" defer></script>
    <script src="<?= BASE_URL ?>public/assets/js/forgot-password-card.js" defer></script>
</body>
</html>