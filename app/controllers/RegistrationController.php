<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../models/ScholarRegistration.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/Course.php';

class RegistrationController
{
    private ScholarRegistration $registrationModel;
    private Notification $notificationModel;
    private Course $courseModel;

    public function __construct(PDO $pdo)
    {
        $this->registrationModel =
            new ScholarRegistration($pdo);
        $this->notificationModel = new Notification($pdo);
        $this->courseModel = new Course($pdo);
    }


    public function createRegistration(
        string $email,
        string $password,
        string $confirmPassword,
        string $firstName,
        ?string $middleName,
        string $lastName,
        ?string $suffix,
        string $studentIdNumber,
        string $courseId,
        string $hkDiscountType,
        string $yearLevel
    ): array {


        /*
         * 1. HK DISCOUNT TYPE
         */
        $hkDiscountType = trim($hkDiscountType);

        if ($hkDiscountType === '') {

            return [
                'success' => false,
                'field' => 'hk_discount_type',
                'message' => 'Please select an HK Discount Type.'
            ];
        }


        $validDiscountTypes = [
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


        if (
            !in_array(
                $hkDiscountType,
                $validDiscountTypes,
                true
            )
        ) {

            return [
                'success' => false,
                'field' => 'hk_discount_type',
                'message' => 'Please select a valid HK Discount Type.'
            ];
        }


        /*
         * 2. YEAR LEVEL
         */
        $yearLevel = trim($yearLevel);

        if ($yearLevel === '') {

            return [
                'success' => false,
                'field' => 'year_level',
                'message' => 'Please .'
            ];
        }


        $validYearLevels = [
            '1st Year',
            '2nd Year',
            '3rd Year',
            '4th Year',
            '5th Year'
        ];


        if (
            !in_array(
                $yearLevel,
                $validYearLevels,
                true
            )
        ) {

            return [
                'success' => false,
                'field' => 'year_level',
                'message' => 'Please select a valid Year Level.'
            ];
        }


        /*
         * 3. STUDENT ID NUMBER
         */
        $studentIdNumber = trim($studentIdNumber);

        if ($studentIdNumber === '') {

            return [
                'success' => false,
                'field' => 'student_id_number',
                'message' => 'Student ID Number is required.'
            ];
        }


        if (
            !preg_match(
                '/^\d{2}-\d{2}-\d{4}-\d{6}$/',
                $studentIdNumber
            )
        ) {

            return [
                'success' => false,
                'field' => 'student_id_number',
                'message' => 'Student ID Number must follow the format 00-00-0000-000000.'
            ];
        }


        /*
         * 4. FIRST NAME
         */
        $firstName = trim($firstName);

        if ($firstName === '') {

            return [
                'success' => false,
                'field' => 'first_name',
                'message' => 'First Name is required.'
            ];
        }


        if (
            !preg_match(
                '/^[A-Za-zÀ-ÖØ-öø-ÿ\s]+$/',
                $firstName
            )
        ) {

            return [
                'success' => false,
                'field' => 'first_name',
                'message' => 'First Name may only contain letters and spaces.'
            ];
        }


        /*
         * 5. MIDDLE NAME
         */
        $middleName = trim($middleName ?? '');

        if (
            $middleName !== '' &&
            !preg_match(
                '/^[A-Za-zÀ-ÖØ-öø-ÿ\s]+$/',
                $middleName
            )
        ) {

            return [
                'success' => false,
                'field' => 'middle_name',
                'message' => 'Middle Name may only contain letters and spaces.'
            ];
        }


        /*
         * 6. LAST NAME
         */
        $lastName = trim($lastName);

        if ($lastName === '') {

            return [
                'success' => false,
                'field' => 'last_name',
                'message' => 'Last Name is required.'
            ];
        }


        if (
            !preg_match(
                '/^[A-Za-zÀ-ÖØ-öø-ÿ\s]+$/',
                $lastName
            )
        ) {

            return [
                'success' => false,
                'field' => 'last_name',
                'message' => 'Last Name may only contain letters and spaces.'
            ];
        }


        /*
         * 7. SUFFIX
         */
        $suffix = trim($suffix ?? '');

        if (
            $suffix !== '' &&
            !preg_match(
                '/^[A-Za-zÀ-ÖØ-öø-ÿ\s.]+$/',
                $suffix
            )
        ) {

            return [
                'success' => false,
                'field' => 'suffix',
                'message' => 'Suffix may only contain letters, spaces, and periods.'
            ];
        }


        /*
         * 8. COURSE
         */
        $courseId = filter_var(trim($courseId), FILTER_VALIDATE_INT);

        if (
            $courseId === false
            || $courseId < 1
            || !$this->courseModel->isActive($courseId)
        ) {

            return [
                'success' => false,
                'field' => 'course_id',
                'message' => 'Please select an active course.'
            ];
        }


        /*
         * 9. PHINMAED EMAIL
         */
        $email = trim($email);

        if ($email === '') {

            return [
                'success' => false,
                'field' => 'email',
                'message' => 'PHINMAED Email is required.'
            ];
        }


        if (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            return [
                'success' => false,
                'field' => 'email',
                'message' => 'Please enter a valid PHINMAED Email.'
            ];
        }


        if (!preg_match('/@phinmaed\.com$/i', $email)) {
            return ['success' => false, 'field' => 'email', 'message' => 'Please use a valid PHINMAED email address.'];
        }

        if (
            $this->registrationModel
                ->emailExistsInUsers($email)
        ) {

            return [
                'success' => false,
                'field' => 'email',
                'message' => 'This email is already registered.'
            ];
        }


        if (
            $this->registrationModel
                ->emailExistsInRegistrations($email)
        ) {

            return [
                'success' => false,
                'field' => 'email',
                'message' => 'This email already has a pending registration.'
            ];
        }


        /*
         * 10. PASSWORD
         */
        if ($password === '') {

            return [
                'success' => false,
                'field' => 'password',
                'message' => 'Password is required.'
            ];
        }


        if (strlen($password) < 8) {

            return [
                'success' => false,
                'field' => 'password',
                'message' => 'Password must be at least 8 characters.'
            ];
        }


        if (!preg_match('/[A-Za-z]/', $password)) {

            return [
                'success' => false,
                'field' => 'password',
                'message' => 'Password must contain at least one letter.'
            ];
        }


        if (!preg_match('/\d/', $password)) {

            return [
                'success' => false,
                'field' => 'password',
                'message' => 'Password must contain at least one number.'
            ];
        }


        if (!preg_match('/[^A-Za-z\d]/', $password)) {

            return [
                'success' => false,
                'field' => 'password',
                'message' => 'Password must contain at least one special character.'
            ];
        }

        if ($confirmPassword === '') {

            return [
                'success' => false,
                'field' => 'confirm_password',
                'message' => 'Please confirm your password.'
            ];
        }

        if ($password !== $confirmPassword) {

            return [
                'success' => false,
                'field' => 'confirm_password',
                'message' => 'Passwords do not match.'
            ];
        }


        /*
         * CREATE REGISTRATION
         */
        try {

            $registrationId =
                $this->registrationModel
                    ->createRegistration(
                        $email,
                        $password,
                        $studentIdNumber,
                        $firstName,
                        $middleName !== ''
                            ? $middleName
                            : null,
                        $lastName,
                        $suffix !== ''
                            ? $suffix
                            : null,
                        $courseId,
                        $yearLevel,
                        $hkDiscountType
                    );


            /*
             * VERIFY REGISTRATION
             */
            $this->registrationModel
                ->verifyRegistration(
                    $registrationId
                );

            $this->notificationModel->notifyAdmins(
                'New Scholar Registration',
                $firstName . ' ' . $lastName . ' submitted a registration request.',
                'system',
                $registrationId
            );


            return [
                'success' => true,
                'registration_id' => $registrationId,
                'message' => 'Registration submitted successfully. Please wait for Admin approval.'
            ];

        } catch (PDOException $e) {

            return [
                'success' => false,
                'field' => null,
                'message' => 'Unable to submit the registration.'
            ];
        }
    }
}