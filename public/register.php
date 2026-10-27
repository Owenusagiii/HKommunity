<?php

session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/controllers/RegistrationController.php';
require_once __DIR__ . '/../app/models/Course.php';

$registrationController =
    new RegistrationController($pdo);
$courses = (new Course($pdo))->getActiveCourses();

$formData = [
    'email' => '',
    'password' => '',
    'confirm_password' => '',
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'suffix' => '',
    'student_id_number' => '',
    'course_id' => '',
    'hk_discount_type' => '',
    'year_level' => ''
];

$formErrorField = null;
$formErrorMessage = null;

$successMessage = null;

$discountTypes = [
    'HK5.5',
    'HK7.5',
    'HK10',
    'HK12.5',
    'HK17.5',
    'HK25%',
    'HK50%',
    'HK55%',
    'HK75%',
    'HK80%'
];

$yearLevels = [
    '1st Year',
    '2nd Year',
    '3rd Year',
    '4th Year',
    '5th Year'
];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $formData = [
        'email' => $_POST['email'] ?? '',
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'first_name' => $_POST['first_name'] ?? '',
        'middle_name' => $_POST['middle_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'suffix' => $_POST['suffix'] ?? '',
        'student_id_number' => $_POST['student_id_number'] ?? '',
        'course_id' => $_POST['course_id'] ?? '',
        'hk_discount_type' => $_POST['hk_discount_type'] ?? '',
        'year_level' => $_POST['year_level'] ?? ''
    ];


    $result =
        $registrationController->createRegistration(
            $formData['email'],
            $formData['password'],
            $formData['confirm_password'],
            $formData['first_name'],
            $formData['middle_name'],
            $formData['last_name'],
            $formData['suffix'],
            $formData['student_id_number'],
            (string) $formData['course_id'],
            $formData['hk_discount_type'],
            $formData['year_level']
        );


    if ($result['success']) {

        $successMessage =
            $result['message'];

        $formData = [
            'email' => '',
            'password' => '',
            'confirm_password' => '',
            'first_name' => '',
            'middle_name' => '',
            'last_name' => '',
            'suffix' => '',
            'student_id_number' => '',
            'course_id' => '',
            'hk_discount_type' => '',
            'year_level' => ''
        ];

    } else {

        $formErrorField =
            $result['field'] ?? null;

        $formErrorMessage =
            $result['message']
            ?? 'Unable to submit the registration.';
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Register</title>
    <link rel="icon" type="image/jpeg" href="<?= BASE_URL ?>public/assets/images/logo.jpg">
    <link rel="stylesheet" href="<?= BASE_URL ?>public/assets/css/lanlogreg.css?v=<?= filemtime(__DIR__ . '/assets/css/lanlogreg.css') ?>">
    <script src="<?= BASE_URL ?>public/assets/js/flash-messages.js" defer></script>
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

            <section class="auth-card">
                <h2>REGISTER</h2>

                <?php if ($successMessage): ?>
                    <div class="flash-message success" role="status" data-auto-dismiss>
                        <?= htmlspecialchars($successMessage) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>register" autocomplete="on">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="hk_discount_type">HK Discount Type</label>
                            <select id="hk_discount_type" name="hk_discount_type" required>
                                <option value="">Select HK Discount Type</option>
                                <?php foreach ($discountTypes as $discountType): ?>
                                    <option value="<?= htmlspecialchars($discountType) ?>" <?= $formData['hk_discount_type'] === $discountType ? 'selected' : '' ?>><?= htmlspecialchars($discountType) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($formErrorField === 'hk_discount_type'): ?><p class="field-error"><?= htmlspecialchars($formErrorMessage) ?></p><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="course_id">Course</label>
                            <select id="course_id" name="course_id" required>
                                <option value="">Select your course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= (int) $course['course_id'] ?>" <?= (string) $formData['course_id'] === (string) $course['course_id'] ? 'selected' : '' ?>><?= htmlspecialchars($course['course_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($formErrorField === 'course_id'): ?><p class="field-error"><?= htmlspecialchars($formErrorMessage) ?></p><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="year_level">Year Level</label>
                            <select id="year_level" name="year_level" required>
                                <option value="">Select Year Level</option>
                                <?php foreach ($yearLevels as $yearLevel): ?>
                                    <option value="<?= htmlspecialchars($yearLevel) ?>" <?= $formData['year_level'] === $yearLevel ? 'selected' : '' ?>><?= htmlspecialchars($yearLevel) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($formErrorField === 'year_level'): ?><p class="field-error"><?= htmlspecialchars($formErrorMessage) ?></p><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="student_id_number">Student ID Number</label>
                            <input type="text" id="student_id_number" name="student_id_number" value="<?= htmlspecialchars($formData['student_id_number']) ?>" placeholder="00-00-0000-000000" autocomplete="username" required>
                            <?php if ($formErrorField === 'student_id_number'): ?><p class="field-error"><?= htmlspecialchars($formErrorMessage) ?></p><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($formData['first_name']) ?>" placeholder="Enter first name" required>
                            <?php if ($formErrorField === 'first_name'): ?><p class="field-error"><?= htmlspecialchars($formErrorMessage) ?></p><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="middle_name">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name" value="<?= htmlspecialchars($formData['middle_name']) ?>" placeholder="Enter middle name">
                            <?php if ($formErrorField === 'middle_name'): ?><p class="field-error"><?= htmlspecialchars($formErrorMessage) ?></p><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($formData['last_name']) ?>" placeholder="Enter last name" required>
                            <?php if ($formErrorField === 'last_name'): ?><p class="field-error"><?= htmlspecialchars($formErrorMessage) ?></p><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="suffix">Suffix</label>
                            <input type="text" id="suffix" name="suffix" value="<?= htmlspecialchars($formData['suffix']) ?>" placeholder="e.g., Jr., III">
                            <?php if ($formErrorField === 'suffix'): ?><p class="field-error"><?= htmlspecialchars($formErrorMessage) ?></p><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="email">PHINMAED Email</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($formData['email']) ?>" placeholder="e.g. scholar@phinmaed.com" autocomplete="off" required>
                            <?php if ($formErrorField === 'email'): ?><p class="field-error"><?= htmlspecialchars($formErrorMessage) ?></p><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="password-field">
                                <input type="password" id="password" name="password" placeholder="Enter password" autocomplete="new-password" required minlength="8">
                                <button type="button" class="password-toggle" data-target="password" aria-label="Show password"></button>
                            </div>
                            <?php if ($formErrorField === 'password'): ?><p class="field-error"><?= htmlspecialchars($formErrorMessage) ?></p><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <div class="password-field">
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password" autocomplete="new-password" required minlength="8">
                                <button type="button" class="password-toggle" data-target="confirm_password" aria-label="Show password"></button>
                            </div>
                            <?php if ($formErrorField === 'confirm_password'): ?><p class="field-error"><?= htmlspecialchars($formErrorMessage) ?></p><?php endif; ?>
                        </div>
                    </div>

                    <p class="hint">Password must be at least 8 characters and contain a number and special character.</p>
                    <button class="btn btn-green" type="submit">REGISTER</button>
                </form>

                <div class="bottom">Already have an account? <a href="<?= BASE_URL ?>public/login.php">Log in &#8594;</a></div>
            </section>
        </main>
    </div>
    <script src="<?= BASE_URL ?>public/assets/js/password-toggle.js" defer></script>
</body>
</html>