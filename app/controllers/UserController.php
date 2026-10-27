<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../models/User.php';

class UserController
{
    private User $userModel;

    public function __construct(PDO $pdo)
    {
        $this->userModel = new User($pdo);
    }

    public function getUsers(): array
    {
        return $this->userModel->getAllUsers();
    }

    public function searchUsers(string $email): array
    {
        return $this->userModel->searchByEmail(trim($email));
    }

    public function updateStatus(int $userId, string $status): array
    {
        if (!in_array($status, ['active', 'inactive'], true)) {
            return [
                'success' => false,
                'message' => 'Invalid account status.',
            ];
        }

        try {
            $updated = $this->userModel->updateStatus($userId, $status);

            return [
                'success' => $updated,
                'message' => $updated
                    ? 'Account status updated successfully.'
                    : 'Unable to update the account status.',
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Unable to update the account status.',
            ];
        }
    }

    public function createCoordinator(
        string $email,
        string $password,
        string $firstName,
        ?string $middleName,
        string $lastName,
        ?string $suffix,
        string $yearLevel
    ): array {
        $email = trim($email);
        $firstName = trim($firstName);
        $middleName = trim((string) $middleName);
        $lastName = trim($lastName);
        $suffix = trim((string) $suffix);
        $yearLevel = trim($yearLevel);

        $result = $this->validateCommonAccountFields(
            $email,
            $password,
            $firstName,
            $middleName,
            $lastName,
            $suffix,
            $yearLevel
        );

        if (!$result['success']) {
            return $result;
        }

        try {
            $this->userModel->beginTransaction();

            $userId = $this->userModel->createUser(
                $email,
                $password,
                'coordinator'
            );

            $this->userModel->createCoordinator(
                $userId,
                $firstName,
                $middleName !== '' ? $middleName : null,
                $lastName,
                $suffix !== '' ? $suffix : null,
                $yearLevel
            );

            $this->userModel->commit();

            return [
                'success' => true,
                'message' => 'Coordinator account created successfully.',
            ];
        } catch (Throwable $e) {
            if ($this->userModel->rollBack()) {
                // Transaction was rolled back.
            }

            return [
                'success' => false,
                'message' => 'Unable to create the coordinator account.',
            ];
        }
    }

    public function createScholar(
        string $email,
        string $password,
        string $studentIdNumber,
        string $firstName,
        ?string $middleName,
        string $lastName,
        ?string $suffix,
        string $yearLevel,
        string $hkDiscountType
    ): array {
        $email = trim($email);
        $studentIdNumber = trim($studentIdNumber);
        $firstName = trim($firstName);
        $middleName = trim((string) $middleName);
        $lastName = trim($lastName);
        $suffix = trim((string) $suffix);
        $yearLevel = trim($yearLevel);
        $hkDiscountType = trim($hkDiscountType);

        $result = $this->validateCommonAccountFields(
            $email,
            $password,
            $firstName,
            $middleName,
            $lastName,
            $suffix,
            $yearLevel
        );

        if (!$result['success']) {
            return $result;
        }

        if (!preg_match('/^\d{2}-\d{2}-\d{4}-\d{6}$/', $studentIdNumber)) {
            return [
                'success' => false,
                'field' => 'student_id_number',
                'message' => 'Format must be 00-00-0000-000000.',
            ];
        }

        if (!in_array($hkDiscountType, $this->allowedDiscounts(), true)) {
            return [
                'success' => false,
                'field' => 'hk_discount_type',
                'message' => 'Invalid HK discount type.',
            ];
        }

        try {
            $this->userModel->beginTransaction();

            $userId = $this->userModel->createUser(
                $email,
                $password,
                'scholar'
            );

            $this->userModel->createScholar(
                $userId,
                $studentIdNumber,
                $firstName,
                $middleName !== '' ? $middleName : null,
                $lastName,
                $suffix !== '' ? $suffix : null,
                $yearLevel,
                $hkDiscountType
            );

            $this->userModel->commit();

            return [
                'success' => true,
                'message' => 'Scholar account created successfully.',
            ];
        } catch (Throwable $e) {
            $this->userModel->rollBack();

            return [
                'success' => false,
                'message' => 'Unable to create the scholar account.',
            ];
        }
    }

    private function validateCommonAccountFields(
        string $email,
        string $password,
        string $firstName,
        string $middleName,
        string $lastName,
        string $suffix,
        string $yearLevel
    ): array {
        if ($email === '') {
            return ['success' => false, 'field' => 'email', 'message' => 'PHINMAED Email is required.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'field' => 'email', 'message' => 'Please enter a valid email address.'];
        }

        if (!preg_match('/@phinmaed\.com$/i', $email)) {
            return ['success' => false, 'field' => 'email', 'message' => 'Please use a valid PHINMAED email address.'];
        }

        if ($this->userModel->findByEmail($email)) {
            return ['success' => false, 'field' => 'email', 'message' => 'An account with this email already exists.'];
        }

        $passwordResult = $this->validatePassword($password);
        if (!$passwordResult['success']) {
            return $passwordResult;
        }

        foreach ([
            ['value' => $firstName, 'field' => 'first_name', 'label' => 'First Name', 'required' => true],
            ['value' => $middleName, 'field' => 'middle_name', 'label' => 'Middle Name', 'required' => false],
            ['value' => $lastName, 'field' => 'last_name', 'label' => 'Last Name', 'required' => true],
        ] as $field) {
            if ($field['value'] === '' && $field['required']) {
                return ['success' => false, 'field' => $field['field'], 'message' => $field['label'] . ' is required.'];
            }

            if ($field['value'] !== '' && !$this->validName($field['value'])) {
                return ['success' => false, 'field' => $field['field'], 'message' => $field['label'] . ' must contain letters and spaces only.'];
            }
        }

        if ($suffix !== '' && !preg_match('/^[A-Za-zÀ-ÖØ-öø-ÿ\s.]+$/', $suffix)) {
            return ['success' => false, 'field' => 'suffix', 'message' => 'Suffix must contain letters, spaces, and periods only.'];
        }

        if (!in_array($yearLevel, $this->allowedYearLevels(), true)) {
            return ['success' => false, 'field' => 'year_level', 'message' => 'Invalid year level.'];
        }

        return ['success' => true];
    }

    private function validatePassword(string $password): array
    {
        if ($password === '') {
            return ['success' => false, 'field' => 'password', 'message' => 'Password is required.'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'field' => 'password', 'message' => 'Password must be at least 8 characters long.'];
        }
        if (!preg_match('/[A-Za-z]/', $password)) {
            return ['success' => false, 'field' => 'password', 'message' => 'Password must contain at least one letter.'];
        }
        if (!preg_match('/\d/', $password)) {
            return ['success' => false, 'field' => 'password', 'message' => 'Password must contain at least one number.'];
        }
        if (!preg_match('/[^A-Za-z\d]/', $password)) {
            return ['success' => false, 'field' => 'password', 'message' => 'Password must contain at least one special character.'];
        }

        return ['success' => true];
    }

    private function validName(string $value): bool
    {
        return preg_match('/^[A-Za-zÀ-ÖØ-öø-ÿ\s]+$/', $value) === 1;
    }

    private function allowedYearLevels(): array
    {
        return ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'];
    }

    private function allowedDiscounts(): array
    {
        return [
            'HK5.5', 'HK7.5', 'HK10', 'HK12.5', 'HK17.5',
            'HK25%', 'HK50%', 'HK55%', 'HK75%', 'HK80%'
        ];
    }
}
