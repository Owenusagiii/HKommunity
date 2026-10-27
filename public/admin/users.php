<?php
require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/models/User.php';
require_once __DIR__ . '/../../app/models/Scholar.php';
require_once __DIR__ . '/../../app/models/Coordinator.php';
require_once __DIR__ . '/../../app/controllers/UserController.php';
require_once __DIR__ . '/../../app/views/components/helpers.php';

$model = new User($pdo);
$scholarModel = new Scholar($pdo);
$coordinatorModel = new Coordinator($pdo);
$userController = new UserController($pdo);
$formData = [
    'role' => 'coordinator',
    'email' => '',
    'password' => '',
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'suffix' => '',
    'year_level' => ''
];
$formErrorField = null;
$formErrorMessage = null;
$studentId = trim((string) ($_GET['student_id'] ?? ''));
$scholarYearLevel = trim((string) ($_GET['scholar_year_level'] ?? ''));
$coordinatorName = trim((string) ($_GET['coordinator_name'] ?? ''));
$coordinatorYearLevel = trim((string) ($_GET['coordinator_year_level'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    if ($action === 'create_coordinator') {
        $postedFormValue = static function (string $key): string {
            $value = post($key);
            return is_scalar($value) ? (string) $value : '';
        };
        $formData = [
            'role' => $postedFormValue('role'),
            'email' => $postedFormValue('email'),
            'password' => $postedFormValue('password'),
            'first_name' => $postedFormValue('first_name'),
            'middle_name' => $postedFormValue('middle_name'),
            'last_name' => $postedFormValue('last_name'),
            'suffix' => $postedFormValue('suffix'),
            'year_level' => $postedFormValue('year_level')
        ];

        if ($formData['role'] !== 'coordinator') {
            $result = [
                'success' => false,
                'field' => 'role',
                'message' => 'Only coordinator accounts can be created here.'
            ];
        } else {
            $result = $userController->createCoordinator(
                (string) $formData['email'],
                (string) $formData['password'],
                (string) $formData['first_name'],
                $formData['middle_name'] !== '' ? (string) $formData['middle_name'] : null,
                (string) $formData['last_name'],
                $formData['suffix'] !== '' ? (string) $formData['suffix'] : null,
                (string) $formData['year_level']
            );
        }

        $formData['password'] = '';

        if ($result['success']) {
            flash('success', $result['message']);
            redirectTo(BASE_URL . 'admin/users');
        }

        $formErrorField = ($result['field'] ?? null) === 'role' ? null : ($result['field'] ?? null);
        $formErrorMessage = $result['message'] ?? 'Unable to create the account.';
    } else {
        $status = post('status');
        $ok = false;

        if ($action === 'scholar_status') {
            $ok = in_array($status, ['active', 'inactive'], true)
                && $model->updateStatus((int) post('user_id'), $status);
        } elseif ($action === 'coordinator_status') {
            $ok = in_array($status, ['active', 'inactive'], true)
                && $model->updateStatus((int) post('user_id'), $status);
        } elseif ($action === 'coordinator_year_level') {
            $newYear = post('year_level');
            $ok = in_array($newYear, allowedYearLevels(), true)
                && $coordinatorModel->updateAssignedYearLevel((int) post('id'), $newYear);
        }

        flash($ok ? 'success' : 'error', $ok ? 'Account updated.' : 'Unable to update account.');
        redirectTo(BASE_URL . 'public/admin/users.php');
    }
}

$scholars = $scholarModel->search(
    $studentId !== '' ? $studentId : null,
    $scholarYearLevel !== '' ? $scholarYearLevel : null,
    null
);
$coordinators = $coordinatorName !== '' && $coordinatorYearLevel !== ''
    ? $coordinatorModel->searchAndFilter($coordinatorName, $coordinatorYearLevel)
    : ($coordinatorName !== ''
        ? $coordinatorModel->searchCoordinators($coordinatorName)
        : ($coordinatorYearLevel !== ''
            ? $coordinatorModel->filterByYearLevel($coordinatorYearLevel)
            : $coordinatorModel->getAllCoordinators()));
$pageTitle = 'Users';
require __DIR__ . '/../../app/views/components/header.php';
?>
<main>
    <section class="duty-records-content admin-users-page">
        <div class="page-head duty-page-head">
            <div>
                <h1>Users &amp; Accounts</h1>
            </div>
            <div class="duty-info-banner">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>Account status and coordinator assignments can be updated from the records below.</span>
            </div>
        </div>

        <?php require __DIR__ . '/../../app/views/components/flash-message.php'; ?>

        <div class="records-card admin-users-card coordinator-scholars-card">
            <div class="records-head">
                <div>
                    <h2 class="admin-users-section-title"><span class="admin-users-section-icon"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i></span>Scholar Accounts</h2>
                    <p class="sub-small">Scholar profiles and account status</p>
                </div>
                <div class="coordinator-scholars-tools admin-role-tools">
                    <span class="record-count"><?= e(count($scholars)) ?> scholars</span>
                    <form method="get" class="coordinator-scholars-search admin-role-search" data-admin-users-search>
                        <label class="visually-hidden" for="scholar_year_level">Filter scholars by year level</label>
                        <select class="admin-users-year-filter" id="scholar_year_level" name="scholar_year_level">
                            <option value="">All year levels</option>
                            <?php foreach (allowedYearLevels() as $level): ?>
                                <option value="<?= e($level) ?>" <?= $scholarYearLevel === $level ? 'selected' : '' ?>><?= e($level) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="requirement-search">
                            <span class="visually-hidden">Search scholars by student ID number</span>
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <input type="search" id="student_id" name="student_id" value="<?= e($studentId) ?>" placeholder="Search by student ID" autocomplete="off" aria-controls="admin-scholars-table">
                        </label>
                    </form>
                </div>
            </div>
            <div class="table-scroll coordinator-scholars-table-scroll">
                <table class="data-table admin-role-table" id="admin-scholars-table"><thead><tr><th>Scholar</th><th>Year Level</th><th>Course</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($scholars as $row): ?><tr class="admin-popout-row" tabindex="0" data-admin-dialog="admin-scholar-<?= e($row['id']) ?>" data-admin-search-row data-search-id="<?= e($row['student_id_number']) ?>" data-search-year="<?= e($row['year_level']) ?>" aria-haspopup="dialog" aria-controls="admin-scholar-<?= e($row['id']) ?>"><td><strong class="admin-scholar-progress-name"><?= e(nameCaps($row)) ?></strong><small><?= e($row['student_id_number']) ?></small></td><td><?= e($row['year_level']) ?></td><td><?= e($row['course_name'] ?? 'N/A') ?></td><td><span class="status-pill status-<?= e($row['status']) ?>"><?= e(ucfirst($row['status'])) ?></span></td></tr><?php endforeach; ?>
                <tr class="admin-users-search-empty" hidden><td colspan="4">No scholars match your search.</td></tr>
                </tbody></table>
            </div>
            <?php foreach ($scholars as $row): ?>
                <dialog class="admin-record-dialog scholar-requirement-dialog admin-user-detail-dialog" id="admin-scholar-<?= e($row['id']) ?>" aria-labelledby="admin-scholar-title-<?= e($row['id']) ?>">
                    <div class="scholar-requirement-dialog-head">
                        <div>
                            <span class="scholar-requirement-dialog-eyebrow">Scholar account</span>
                            <h2 id="admin-scholar-title-<?= e($row['id']) ?>"><?= e(nameCaps($row)) ?></h2>
                            <span class="admin-scholar-header-student-id"><?= e($row['student_id_number']) ?></span>
                        </div>
                        <button type="button" class="scholar-requirement-dialog-close" aria-label="Close scholar details"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    </div>
                    <div class="scholar-requirement-dialog-content admin-user-detail-content">
                        <div class="admin-user-detail-card admin-scholar-detail-card">
                            <div class="admin-user-detail-card-heading">
                                <h3>Scholar details</h3>
                                <p>Review the scholar's account information below. Use the action button to update account status.</p>
                            </div>
                            <div class="scholar-requirement-detail-grid">
                                <div><span>Email</span><strong><?= e($row['phinmaed_email']) ?></strong></div>
                                <div><span>Year level</span><strong><?= e($row['year_level']) ?></strong></div>
                                <div><span>Course</span><strong><?= e($row['course_name'] ?? 'N/A') ?></strong></div>
                                <div><span>HK Discount Type</span><strong><?= e($row['hk_discount_type']) ?></strong></div>
                                <div><span>Created</span><strong><?= e(!empty($row['created_at']) ? (new DateTime($row['created_at']))->format('M j, Y \a\t g:i A') : 'N/A') ?></strong></div>
                            </div>
                        </div>
                        <form method="post" class="admin-scholar-status-form" id="admin-scholar-status-form-<?= e($row['id']) ?>">
                            <input type="hidden" name="action" value="scholar_status">
                            <input type="hidden" name="user_id" value="<?= e($row['user_id']) ?>">
                            <input type="hidden" name="status" value="<?= $row['status'] === 'active' ? 'inactive' : 'active' ?>">
                        </form>
                        <div class="admin-scholar-status-actions">
                            <button
                                type="submit"
                                form="admin-scholar-status-form-<?= e($row['id']) ?>"
                                class="btn <?= $row['status'] === 'active' ? 'btn-danger admin-user-deactivate-button' : 'btn-green' ?>"
                                <?= $row['status'] === 'active' ? 'data-confirm-account-deactivation="Scholar"' : '' ?>
                                <?= $row['status'] === 'inactive' ? 'data-confirm-admin-action data-confirm-variant="success" data-confirm-title="Activate scholar?" data-confirm-description="Are you sure you want to activate this scholar account?" data-confirm-submit-label="Activate Scholar"' : '' ?>
                            ><?= $row['status'] === 'active' ? 'Deactivate User' : 'Activate Scholar' ?></button>
                        </div>
                    </div>
                </dialog>
            <?php endforeach; ?>
        </div>

        <div class="records-card admin-users-card coordinator-scholars-card">
            <div class="records-head">
                <div>
                    <h2 class="admin-users-section-title"><span class="admin-users-section-icon"><i class="fa-solid fa-user-tie" aria-hidden="true"></i></span>Coordinator Accounts</h2>
                    <p class="sub-small">Coordinator Profiles</p>
                </div>
                <div class="coordinator-scholars-tools admin-role-tools">
                    <span class="record-count"><?= e(count($coordinators)) ?> coordinators</span>
                    <form method="get" class="coordinator-scholars-search admin-role-search" data-admin-users-search>
                        <label class="visually-hidden" for="coordinator_year_level">Filter coordinators by assigned year level</label>
                        <select class="admin-users-year-filter" id="coordinator_year_level" name="coordinator_year_level">
                            <option value="">All year levels</option>
                            <?php foreach (allowedYearLevels() as $level): ?>
                                <option value="<?= e($level) ?>" <?= $coordinatorYearLevel === $level ? 'selected' : '' ?>><?= e($level) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="requirement-search">
                            <span class="visually-hidden">Search coordinators by name</span>
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <input type="search" id="coordinator_name" name="coordinator_name" value="<?= e($coordinatorName) ?>" placeholder="Search by name" autocomplete="off" aria-controls="admin-coordinators-table">
                        </label>
                    </form>
                </div>
            </div>
            <div class="table-scroll coordinator-scholars-table-scroll">
                <table class="data-table admin-role-table" id="admin-coordinators-table"><thead><tr><th>Name</th><th>PHINMAED Email</th><th>Assigned Year Level</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($coordinators as $row): ?><tr class="admin-popout-row" tabindex="0" data-admin-dialog="admin-coordinator-<?= e($row['id']) ?>" data-admin-search-row data-search-name="<?= e(nameCaps($row)) ?>" data-search-year="<?= e($row['year_level']) ?>" aria-haspopup="dialog" aria-controls="admin-coordinator-<?= e($row['id']) ?>"><td><?= e(nameCaps($row)) ?></td><td><?= e($row['phinmaed_email']) ?></td><td><?= e($row['year_level']) ?></td><td><span class="status-pill status-<?= e($row['status']) ?>"><?= e(ucfirst($row['status'])) ?></span></td></tr><?php endforeach; ?>
                <tr class="admin-users-search-empty" hidden><td colspan="4">No coordinators match your search.</td></tr>
                </tbody></table>
            </div>
            <?php foreach ($coordinators as $row): ?>
                <dialog class="admin-record-dialog scholar-requirement-dialog admin-user-detail-dialog" id="admin-coordinator-<?= e($row['id']) ?>" aria-labelledby="admin-coordinator-title-<?= e($row['id']) ?>">
                    <div class="scholar-requirement-dialog-head">
                        <div><span class="scholar-requirement-dialog-eyebrow">Coordinator account</span><h2 id="admin-coordinator-title-<?= e($row['id']) ?>"><?= e(nameCaps($row)) ?></h2></div>
                        <button type="button" class="scholar-requirement-dialog-close" aria-label="Close coordinator details"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    </div>
                    <div class="scholar-requirement-dialog-content admin-user-detail-content">
                        <div class="admin-user-detail-card admin-coordinator-detail-card">
                            <div class="admin-user-detail-card-heading">
                                <h3>Coordinator details</h3>
                                <p>Review the coordinator's account information and use the controls below to manage their assignment or status.</p>
                            </div>
                            <div class="scholar-requirement-detail-grid">
                                <div><span>Email</span><strong><?= e($row['phinmaed_email']) ?></strong></div>
                                <div><span>Assigned year level</span><strong><?= e($row['year_level']) ?></strong></div>
                                <div><span>Status</span><strong><span class="status-pill status-<?= e($row['status']) ?>"><?= e(ucfirst($row['status'])) ?></span></strong></div>
                                <div><span>Created</span><strong><?= e(!empty($row['created_at']) ? (new DateTime($row['created_at']))->format('M j, Y \a\t g:i A') : 'N/A') ?></strong></div>
                            </div>
                            <form method="post" class="admin-record-edit-form admin-coordinator-detail-form">
                                <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                <input type="hidden" name="user_id" value="<?= e($row['user_id']) ?>">
                                <input type="hidden" name="status" value="<?= $row['status'] === 'active' ? 'inactive' : 'active' ?>">
                                <div class="admin-coordinator-detail-actions">
                                    <div class="form-row">
                                        <label for="coordinator-year-level-<?= e($row['id']) ?>">Change Assigned Year Level</label>
                                        <select id="coordinator-year-level-<?= e($row['id']) ?>" name="year_level" required>
                                            <?php foreach (allowedYearLevels() as $level): ?>
                                                <option value="<?= e($level) ?>" <?= $row['year_level'] === $level ? 'selected' : '' ?>><?= e($level) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="admin-coordinator-detail-buttons">
                                        <button type="submit" name="action" value="coordinator_year_level" class="btn btn-green">Save Assignment</button>
                                        <button
                                            type="submit"
                                            name="action"
                                            value="coordinator_status"
                                            class="btn <?= $row['status'] === 'active' ? 'btn-danger admin-user-deactivate-button' : 'btn-green' ?>"
                                            <?= $row['status'] === 'active' ? 'data-confirm-account-deactivation="Coordinator"' : '' ?>
                                            <?= $row['status'] === 'inactive' ? 'data-confirm-admin-action data-confirm-variant="success" data-confirm-title="Activate coordinator?" data-confirm-description="Are you sure you want to activate this coordinator account?" data-confirm-submit-label="Activate Coordinator"' : '' ?>
                                        ><?= $row['status'] === 'active' ? 'Deactivate User' : 'Activate Coordinator' ?></button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </dialog>
            <?php endforeach; ?>
            <div class="coordinator-create-account-actions">
                <button class="btn btn-green" type="button" data-admin-open-dialog="coordinator-create-dialog" aria-haspopup="dialog" aria-controls="coordinator-create-dialog">
                    <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                    Create Coordinator Account
                </button>
            </div>
        </div>
    </section>
    <?php require __DIR__ . '/../../app/views/admin/create-account.php'; ?>
</main>
<dialog class="logout-confirm-dialog admin-account-deactivation-confirm-dialog" id="admin-account-deactivation-confirm-dialog" aria-labelledby="admin-account-deactivation-confirm-title" aria-describedby="admin-account-deactivation-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div>
    <h2 id="admin-account-deactivation-confirm-title">Deactivate account?</h2>
    <p id="admin-account-deactivation-confirm-description">Are you sure you want to deactivate this account?</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-account-deactivation-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-account-deactivation-confirm>Deactivate Account</button>
    </div>
</dialog>
<dialog class="logout-confirm-dialog admin-record-save-confirm-dialog" id="admin-action-confirm-dialog" aria-labelledby="admin-action-confirm-title" aria-describedby="admin-action-confirm-description">
    <div class="logout-confirm-icon"><i class="fa-solid fa-circle-question" aria-hidden="true"></i></div>
    <h2 id="admin-action-confirm-title">Confirm action</h2>
    <p id="admin-action-confirm-description">Are you sure you want to continue?</p>
    <div class="logout-confirm-actions">
        <button type="button" class="logout-confirm-cancel" data-admin-action-cancel>Cancel</button>
        <button type="button" class="logout-confirm-submit" data-admin-action-confirm>Confirm</button>
    </div>
</dialog>
<?php
$pageScripts = [
    'public/assets/js/admin-table-popouts.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-table-popouts.js'),
    'public/assets/js/admin-account-deactivation-confirmation.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-account-deactivation-confirmation.js'),
    'public/assets/js/admin-action-confirmation.js?v=' . filemtime(__DIR__ . '/../assets/js/admin-action-confirmation.js'),
    'public/assets/js/admin-users-search.js'
];
require __DIR__ . '/../../app/views/components/footer.php';
?>
