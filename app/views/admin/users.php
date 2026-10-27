<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../components/helpers.php';

$users = $users ?? [];
if (!is_array($users)) {
    $users = [];
}

$email = trim((string) ($_GET['email'] ?? ''));
$pageTitle = $pageTitle ?? 'Users';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['status'])) {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $status = $_POST['status'];

    if (function_exists('flash') && function_exists('redirectTo')) {
        $ok = in_array($status, ['active', 'inactive'], true) && function_exists('updateStatus');
        flash($ok ? 'success' : 'error', $ok ? 'Account status updated.' : 'Unable to update account status.');
        redirectTo(BASE_URL . 'public/admin/users.php');
    }
}

require_once __DIR__ . '/../components/header.php';
?>

<main>
    <h1>Users</h1>
    <?php require __DIR__ . '/../components/flash-message.php'; ?>

    <p>
        <a class="btn" href="<?= BASE_URL ?>public/admin/create-account.php">Create Coordinator Account</a>
    </p>

    <form method="get" class="filters">
        <div class="form-row">
            <label for="email">Search by PHINMAED Email</label>
            <input id="email" name="email" value="<?= e($email) ?>">
        </div>
        <button type="submit">Search</button>
    </form>

    <table class="data-table">
        <thead>
            <tr>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Created</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= e($user['phinmaed_email'] ?? '') ?></td>
                    <td><?= e(ucfirst($user['role'] ?? '')) ?></td>
                    <td><?= e(ucfirst($user['status'] ?? '')) ?></td>
                    <td><?= e($user['created_at'] ?? '') ?></td>
                    <td>
                        <?php if (($user['role'] ?? '') !== 'admin'): ?>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="user_id" value="<?= e($user['id'] ?? 0) ?>">
                                <input type="hidden" name="status" value="<?= (($user['status'] ?? '') === 'active') ? 'inactive' : 'active' ?>">
                                <button type="submit"><?= (($user['status'] ?? '') === 'active') ? 'Deactivate' : 'Activate' ?></button>
                            </form>
                        <?php else: ?>
                            <span class="muted">Admin protected</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="5">No users found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</main>

</body>
</html>