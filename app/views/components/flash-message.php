<?php
$successMessage = pullFlash('success');
$errorMessage = pullFlash('error');
?>
<?php if ($successMessage || $errorMessage): ?>
    <div class="flash-message-container" aria-live="polite" aria-atomic="false">
        <?php if ($successMessage): ?>
            <div class="flash-message success" role="status" data-auto-dismiss>
                <i class="fa-solid fa-circle-check flash-message-icon" aria-hidden="true"></i>
                <span><?= e($successMessage) ?></span>
                <button class="flash-message-close" type="button" aria-label="Dismiss notification">&times;</button>
            </div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="flash-message error" role="alert" data-auto-dismiss>
                <i class="fa-solid fa-circle-exclamation flash-message-icon" aria-hidden="true"></i>
                <span><?= e($errorMessage) ?></span>
                <button class="flash-message-close" type="button" aria-label="Dismiss notification">&times;</button>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
