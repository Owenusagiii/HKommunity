<dialog
    class="admin-record-dialog admin-users-drawer coordinator-create-dialog"
    id="coordinator-create-dialog"
    aria-labelledby="coordinator-create-form-title"
    data-auto-open="<?= $formErrorMessage !== null ? 'true' : 'false' ?>"
>
    <section class="coordinator-create-card">
        <div class="coordinator-create-card-head">
            <span class="coordinator-create-section-icon" aria-hidden="true">
                <i class="fa-regular fa-address-card"></i>
            </span>
            <div>
                <h2 id="coordinator-create-form-title">Create Coordinator Account</h2>
                <p>Enter the account credentials and profile information.</p>
            </div>
            <span class="coordinator-create-required-note"><span aria-hidden="true">*</span> Required fields</span>
            <button type="button" class="coordinator-report-dialog-close coordinator-create-dialog-close" aria-label="Close coordinator account form">&times;</button>
        </div>

        <form class="coordinator-create-form" method="POST" action="<?= BASE_URL ?>admin/users">
            <input type="hidden" name="action" value="create_coordinator">
            <input type="hidden" name="role" value="coordinator">

            <?php if ($formErrorField === null && $formErrorMessage): ?>
                <p class="coordinator-create-form-error" role="alert">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <?= e($formErrorMessage) ?>
                </p>
            <?php endif; ?>

            <div class="coordinator-create-grid">
                <div class="coordinator-create-field">
                    <label for="coordinator-email">PHINMAED Email <span aria-hidden="true">*</span></label>
                    <input type="email" id="coordinator-email" name="email" value="<?= e($formData['email']) ?>" placeholder="example@phinmaed.com" autocomplete="email" required<?= $formErrorField === 'email' ? ' aria-invalid="true" aria-describedby="coordinator-email-error"' : '' ?>>
                    <?php if ($formErrorField === 'email'): ?>
                        <p class="field-error" id="coordinator-email-error" role="alert"><?= e($formErrorMessage) ?></p>
                    <?php endif; ?>
                </div>

                <div class="coordinator-create-field">
                    <label for="coordinator-password">Password <span aria-hidden="true">*</span></label>
                    <div class="password-field">
                        <input type="password" id="coordinator-password" name="password" placeholder="Create a secure password" autocomplete="new-password" required<?= $formErrorField === 'password' ? ' aria-invalid="true" aria-describedby="coordinator-password-error"' : '' ?>>
                        <button type="button" class="password-toggle" data-target="coordinator-password" aria-label="Show password" aria-pressed="false"></button>
                    </div>
                    <?php if ($formErrorField === 'password'): ?>
                        <p class="field-error" id="coordinator-password-error" role="alert"><?= e($formErrorMessage) ?></p>
                    <?php endif; ?>
                </div>

                <div class="coordinator-create-field">
                    <label for="coordinator-first-name">First Name <span aria-hidden="true">*</span></label>
                    <input type="text" id="coordinator-first-name" name="first_name" value="<?= e($formData['first_name']) ?>" placeholder="First name" autocomplete="given-name" required<?= $formErrorField === 'first_name' ? ' aria-invalid="true" aria-describedby="coordinator-first-name-error"' : '' ?>>
                    <?php if ($formErrorField === 'first_name'): ?>
                        <p class="field-error" id="coordinator-first-name-error" role="alert"><?= e($formErrorMessage) ?></p>
                    <?php endif; ?>
                </div>

                <div class="coordinator-create-field">
                    <label for="coordinator-middle-name">Middle Name</label>
                    <input type="text" id="coordinator-middle-name" name="middle_name" value="<?= e($formData['middle_name']) ?>" placeholder="Middle name (optional)" autocomplete="additional-name"<?= $formErrorField === 'middle_name' ? ' aria-invalid="true" aria-describedby="coordinator-middle-name-error"' : '' ?>>
                    <?php if ($formErrorField === 'middle_name'): ?>
                        <p class="field-error" id="coordinator-middle-name-error" role="alert"><?= e($formErrorMessage) ?></p>
                    <?php endif; ?>
                </div>

                <div class="coordinator-create-field">
                    <label for="coordinator-last-name">Last Name <span aria-hidden="true">*</span></label>
                    <input type="text" id="coordinator-last-name" name="last_name" value="<?= e($formData['last_name']) ?>" placeholder="Last name" autocomplete="family-name" required<?= $formErrorField === 'last_name' ? ' aria-invalid="true" aria-describedby="coordinator-last-name-error"' : '' ?>>
                    <?php if ($formErrorField === 'last_name'): ?>
                        <p class="field-error" id="coordinator-last-name-error" role="alert"><?= e($formErrorMessage) ?></p>
                    <?php endif; ?>
                </div>

                <div class="coordinator-create-field">
                    <label for="coordinator-suffix">Suffix</label>
                    <input type="text" id="coordinator-suffix" name="suffix" value="<?= e($formData['suffix']) ?>" placeholder="e.g. Jr., Sr., III"<?= $formErrorField === 'suffix' ? ' aria-invalid="true" aria-describedby="coordinator-suffix-error"' : '' ?>>
                    <?php if ($formErrorField === 'suffix'): ?>
                        <p class="field-error" id="coordinator-suffix-error" role="alert"><?= e($formErrorMessage) ?></p>
                    <?php endif; ?>
                </div>

                <div class="coordinator-create-field coordinator-create-year-field">
                    <label for="coordinator-year-level">Assigned Year Level <span aria-hidden="true">*</span></label>
                    <select id="coordinator-year-level" name="year_level" required<?= $formErrorField === 'year_level' ? ' aria-invalid="true" aria-describedby="coordinator-year-level-error"' : '' ?>>
                        <option value="">Select Year Level</option>
                        <option value="1st Year" <?= $formData['year_level'] === '1st Year' ? 'selected' : '' ?>>1st Year</option>
                        <option value="2nd Year" <?= $formData['year_level'] === '2nd Year' ? 'selected' : '' ?>>2nd Year</option>
                        <option value="3rd Year" <?= $formData['year_level'] === '3rd Year' ? 'selected' : '' ?>>3rd Year</option>
                        <option value="4th Year" <?= $formData['year_level'] === '4th Year' ? 'selected' : '' ?>>4th Year</option>
                        <option value="5th Year" <?= $formData['year_level'] === '5th Year' ? 'selected' : '' ?>>5th Year</option>
                    </select>
                    <span class="coordinator-create-hint">This determines which scholars and renewals appear in the coordinator's workspace.</span>
                    <?php if ($formErrorField === 'year_level'): ?>
                        <p class="field-error" id="coordinator-year-level-error" role="alert"><?= e($formErrorMessage) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="coordinator-create-actions">
                <button class="coordinator-create-cancel" type="button" data-admin-dialog-close>Cancel</button>
                <button
                    class="btn btn-green"
                    type="submit"
                    data-confirm-admin-action
                    data-confirm-title="Create coordinator account?"
                    data-confirm-description="Create this coordinator account with the entered profile details and assigned year level?"
                    data-confirm-submit-label="Create Account"
                    data-confirm-variant="success"
                >
                    <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                    Create Coordinator Account
                </button>
            </div>
        </form>
    </section>
</dialog>
