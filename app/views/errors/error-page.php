<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= htmlspecialchars((string) $statusCode, ENT_QUOTES, 'UTF-8') ?> | HKommunity</title>
    <link rel="icon" type="image/jpeg" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>public/assets/images/logo.jpg">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>public/assets/css/errors.css">
</head>
<body>
    <main class="error-page">
        <div class="error-decoration error-decoration-one" aria-hidden="true"></div>
        <div class="error-decoration error-decoration-two" aria-hidden="true"></div>
        <a class="error-logo" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="HKommunity home">
            <img src="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>public/assets/images/logo.jpg" alt="">
        </a>
        <section class="error-card" aria-labelledby="error-title">
            <span class="error-code"><?= htmlspecialchars((string) http_response_code(), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="error-icon" aria-hidden="true">
                ?
            </span>
            <p class="error-eyebrow">HKommunity</p>
            <h1 id="error-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="error-message"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
            <div class="error-actions">
                <a class="error-primary-action" href="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') ?>
                </a>
                <button class="error-back-action" type="button" onclick="history.back()">Go back</button>
            </div>
            <p class="error-support">Your scholarship journey is still right on track.</p>
        </section>
    </main>
</body>
</html>
