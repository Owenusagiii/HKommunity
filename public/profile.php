<?php

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/Coordinator.php';
require_once __DIR__ . '/../app/models/Scholar.php';
require_once __DIR__ . '/../app/views/components/helpers.php';

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'];
$userModel = new User($pdo);
$user = $userModel->findById($userId);

if (!$user) {
    http_response_code(403);
    exit('User account not found.');
}

$coordinatorModel = new Coordinator($pdo);
$scholarModel = new Scholar($pdo);
$profile = $role === 'coordinator'
    ? $coordinatorModel->findByUserId($userId)
    : ($role === 'scholar' ? $scholarModel->findByUserId($userId) : $user);

if (!$profile) {
    http_response_code(403);
    exit('Profile not found.');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($role === 'scholar') {
        $error = 'Scholar information can only be changed by an administrator.';
    } else {
        $first = trim((string) post('first_name'));
        $middle = trim((string) post('middle_name'));
        $last = trim((string) post('last_name'));
        $suffix = trim((string) post('suffix'));

        $validName = static fn (string $value): bool => preg_match('/^[A-Za-zÀ-ÖØ-öø-ÿ\s]+$/', $value) === 1;

        if ($first === '' || !$validName($first)) {
            $error = 'First Name must contain letters and spaces only.';
        } elseif ($last === '' || !$validName($last)) {
            $error = 'Last Name must contain letters and spaces only.';
        } elseif ($middle !== '' && !$validName($middle)) {
            $error = 'Middle Name must contain letters and spaces only.';
        } elseif ($suffix !== '' && preg_match('/^[A-Za-zÀ-ÖØ-öø-ÿ\s.]+$/', $suffix) !== 1) {
            $error = 'Suffix must contain letters, spaces, and periods only.';
        } else {
            $ok = $role === 'admin'
                ? $userModel->updateOwn($userId, $first, $last)
                : $coordinatorModel->updateOwn($userId, $first, $middle !== '' ? $middle : null, $last, $suffix !== '' ? $suffix : null);

            if ($ok) {
                flash('success', 'Profile updated successfully.');
                redirectTo(BASE_URL . 'public/profile.php');
            }

            $error = 'Unable to update your profile.';
        }
    }
}

$pageTitle = 'Profile';
require __DIR__ . '/../app/views/components/header.php';
$profileName = fullName($profile);
$profileName = $profileName !== '' ? $profileName : ($role === 'admin' ? 'Administrator' : $user['phinmaed_email']);
$initials = strtoupper(
    substr((string) ($profile['first_name'] ?? ''), 0, 1)
    . substr((string) ($profile['last_name'] ?? ''), 0, 1)
);
$initials = $initials !== '' ? $initials : strtoupper(substr($user['phinmaed_email'], 0, 1));
$roleLabel = match ($role) {
    'admin' => 'System administrator',
    'coordinator' => 'Scholarship coordinator',
    'scholar' => 'Scholar account',
    default => ucfirst($role),
};
?>
<main class="profile-page<?= $role === 'admin' ? ' admin-profile-page' : '' ?>">
    <section class="profile-content">
        <div class="page-head profile-page-head">
            <div>
                <h1>Profile &amp; Settings</h1>
            </div>
            <span class="profile-role-chip"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><?= e($roleLabel) ?></span>
        </div>

        <?php require __DIR__ . '/../app/views/components/flash-message.php'; ?>
        <?php if ($error): ?>
            <div class="flash-message error" data-auto-dismiss><?= e($error) ?></div>
        <?php endif; ?>

        <section class="profile-hero-card">
            <div class="profile-hero-identity">
                <div class="avatar-lg"><?= e($initials ?: 'U') ?></div>
                <div class="profile-hero-copy">
                    <span class="profile-hero-label">ACCOUNT HOLDER</span>
                    <h2><?= e($profileName) ?></h2>
                    <p><?= e($user['phinmaed_email']) ?></p>
                </div>
            </div>
            <div class="profile-status-chip status-<?= e($user['status']) ?>">
                <span aria-hidden="true"></span><?= e(ucfirst($user['status'])) ?> account
            </div>
        </section>

        <section class="records-card profile-details-card">
            <div class="settings-section-title">Personal Information</div>
            <?php if ($role === 'admin'): ?>
                <div class="profile-admin-note">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <p>Administrator identity details are managed through the system account. Your PHINMAED email is used as your account identifier.</p>
                </div>
            <?php endif; ?>
            <form method="post" class="settings-grid profile-fields">
                <?php if ($role !== 'admin'): ?>
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input id="first_name" name="first_name" value="<?= e($profile['first_name'] ?? '') ?>" <?= $role === 'scholar' ? 'disabled' : 'required' ?>>
                    </div>
                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input id="middle_name" name="middle_name" value="<?= e($profile['middle_name'] ?? '') ?>" <?= $role === 'scholar' ? 'disabled' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input id="last_name" name="last_name" value="<?= e($profile['last_name'] ?? '') ?>" <?= $role === 'scholar' ? 'disabled' : 'required' ?>>
                    </div>
                    <div class="form-group">
                        <label for="suffix">Suffix</label>
                        <input id="suffix" name="suffix" value="<?= e($profile['suffix'] ?? '') ?>" <?= $role === 'scholar' ? 'disabled' : '' ?>>
                    </div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="profile-email">PHINMAED Email</label>
                    <input id="profile-email" value="<?= e($user['phinmaed_email']) ?>" disabled>
                </div>
                <div class="form-group">
                    <label for="profile-role">Portal Role</label>
                    <input id="profile-role" value="<?= e($roleLabel) ?>" disabled>
                </div>
                <?php if ($role === 'coordinator'): ?>
                    <div class="settings-actions profile-save-actions">
                        <p>Your updated name will appear throughout the portal.</p>
                        <button type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save Profile</button>
                    </div>
                <?php elseif ($role === 'scholar'): ?>
                    <p class="profile-read-only-note"><i class="fa-solid fa-lock" aria-hidden="true"></i> Personal details are maintained by an administrator. Contact your Coordinator if they need to be corrected.</p>
                <?php endif; ?>
            </form>

            <div class="settings-section-title profile-section-divider">Account Information</div>
            <div class="profile-account-grid">
                <div class="profile-account-item">
                    <span><i class="fa-solid fa-user-tag" aria-hidden="true"></i> Portal role</span>
                    <strong><?= e($roleLabel) ?></strong>
                </div>
                <div class="profile-account-item">
                    <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Account status</span>
                    <strong><?= e(ucfirst($user['status'])) ?></strong>
                </div>
                <div class="profile-account-item">
                    <span><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i> Account created</span>
                    <strong><?= e((new DateTime($user['created_at']))->format('M j, Y')) ?></strong>
                </div>
                <?php if ($role === 'scholar'): ?>
                    <div class="profile-account-item">
                        <span><i class="fa-solid fa-id-card" aria-hidden="true"></i> Student ID</span>
                        <strong><?= e($profile['student_id_number']) ?></strong>
                    </div>
                    <div class="profile-account-item">
                        <span><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> Course</span>
                        <strong><?= e($profile['course_name']) ?></strong>
                    </div>
                    <div class="profile-account-item">
                        <span><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Year level</span>
                        <strong><?= e($profile['year_level']) ?></strong>
                    </div>
                    <div class="profile-account-item">
                        <span><i class="fa-solid fa-percent" aria-hidden="true"></i> HK discount type</span>
                        <strong><?= e($profile['hk_discount_type']) ?></strong>
                    </div>
                <?php elseif ($role === 'coordinator'): ?>
                    <div class="profile-account-item">
                        <span><i class="fa-solid fa-users" aria-hidden="true"></i> Assigned year level</span>
                        <strong><?= e($profile['year_level']) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </section>
</main>
<?php require __DIR__ . '/../app/views/components/footer.php'; ?>
