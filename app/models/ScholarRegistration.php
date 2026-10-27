<?php

require_once __DIR__ . '/Notification.php';

class ScholarRegistration
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function emailExistsInUsers(string $email): bool
    {
        $sql = "SELECT id
                FROM users
                WHERE phinmaed_email = :email
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'email' => $email
        ]);

        return (bool) $stmt->fetch();
    }


    public function emailExistsInRegistrations(
        string $email
    ): bool {

        $sql = "SELECT id
                FROM scholar_registrations
                WHERE phinmaed_email = :email
                AND status = 'pending'
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'email' => $email
        ]);

        return (bool) $stmt->fetch();
    }


    public function createRegistration(
        string $email,
        string $password,
        string $studentIdNumber,
        string $firstName,
        ?string $middleName,
        string $lastName,
        ?string $suffix,
        int $courseId,
        string $yearLevel,
        string $hkDiscountType
    ): int {

        $sql = "INSERT INTO scholar_registrations (
                    phinmaed_email,
                    password,
                    student_id_number,
                    course_id,
                    first_name,
                    middle_name,
                    last_name,
                    suffix,
                    year_level,
                    hk_discount_type
                ) VALUES (
                    :email,
                    :password,
                    :student_id_number,
                    :course_id,
                    :first_name,
                    :middle_name,
                    :last_name,
                    :suffix,
                    :year_level,
                    :hk_discount_type
                )";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'email' => $email,
            'password' => password_hash(
                $password,
                PASSWORD_DEFAULT
            ),
            'student_id_number' => $studentIdNumber,
            'course_id' => $courseId,
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'suffix' => $suffix,
            'year_level' => $yearLevel,
            'hk_discount_type' => $hkDiscountType
        ]);

        return (int) $this->pdo->lastInsertId();
    }

        public function getRegistrations(
        ?string $studentId = null,
        ?string $status = null
    ): array {

        $sql = "SELECT
                    sr.*,
                    c.course_name,

                    rr.first_name AS official_first_name,
                    rr.middle_name AS official_middle_name,
                    rr.last_name AS official_last_name,
                    rr.suffix AS official_suffix,
                    rr.year_level AS official_year_level,
                    rr.hk_discount_type AS official_hk_discount_type,

                    ri.file_name AS official_file_name,
                    ri.created_at AS official_import_date

                FROM scholar_registrations sr
                LEFT JOIN courses c
                    ON sr.course_id = c.course_id

                LEFT JOIN registration_records rr
                    ON sr.matched_registration_record_id = rr.id

                LEFT JOIN registration_imports ri
                    ON rr.registration_import_id = ri.id

                WHERE 1 = 1";


        $params = [];


        if ($studentId !== null && $studentId !== '') {

            $sql .= " AND sr.student_id_number LIKE :student_id";

            $params['student_id'] = '%' . $studentId . '%';
        }


        if (
            $status !== null &&
            in_array(
                $status,
                ['pending', 'approved', 'rejected'],
                true
            )
        ) {

            $sql .= " AND sr.status = :status";

            $params['status'] = $status;
        }


        $sql .= " ORDER BY
                    CASE
                        WHEN sr.status = 'pending' THEN 1
                        WHEN sr.status = 'approved' THEN 2
                        WHEN sr.status = 'rejected' THEN 3
                    END,
                    sr.created_at DESC";


        $stmt = $this->pdo->prepare($sql);

        $stmt->execute($params);

        return $stmt->fetchAll();
    }


    public function findById(int $id): ?array
    {
        $sql = "SELECT
                    sr.*,
                    c.course_name,

                    rr.first_name AS official_first_name,
                    rr.middle_name AS official_middle_name,
                    rr.last_name AS official_last_name,
                    rr.suffix AS official_suffix,
                    rr.year_level AS official_year_level,
                    rr.hk_discount_type AS official_hk_discount_type,

                    ri.file_name AS official_file_name,
                    ri.created_at AS official_import_date

                FROM scholar_registrations sr
                LEFT JOIN courses c
                    ON sr.course_id = c.course_id

                LEFT JOIN registration_records rr
                    ON sr.matched_registration_record_id = rr.id

                LEFT JOIN registration_imports ri
                    ON rr.registration_import_id = ri.id

                WHERE sr.id = :id
                LIMIT 1";


        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'id' => $id
        ]);

        $registration = $stmt->fetch();

        return $registration ?: null;
    }

    public function verifyRegistration(
        int $registrationId
    ): bool {

        $registration = $this->findById($registrationId);

        if (!$registration) {
            return false;
        }


        $sql = "SELECT
                    rr.id,
                    rr.first_name,
                    rr.middle_name,
                    rr.last_name,
                    rr.suffix,
                    rr.year_level,
                    rr.hk_discount_type
                FROM registration_records rr

                INNER JOIN registration_imports ri
                    ON rr.registration_import_id = ri.id

                WHERE rr.student_id_number = :student_id
                AND rr.status = 'valid'
                AND ri.status = 'approved'

                ORDER BY ri.created_at DESC, rr.id DESC

                LIMIT 1";

        $stmt =
            $this->pdo->prepare($sql);

        $stmt->execute([
            'student_id' =>
                $registration['student_id_number']
        ]);

        $official =
            $stmt->fetch();


        if (!$official) {

            $update = $this->pdo->prepare(
                "UPDATE scholar_registrations
                SET verification_status = 'not_found',
                    matched_registration_record_id = NULL
                WHERE id = :id"
            );

            $update->execute([
                'id' => $registrationId
            ]);

            return true;
        }

        $normalize = static fn (?string $value): string => strtoupper(trim((string) ($value ?? '')));

        $isMatch =
            $normalize($registration['first_name']) ===
            $normalize($official['first_name'])

            && $normalize($registration['middle_name'] ?? '') ===
            $normalize($official['middle_name'] ?? '')

            && $normalize($registration['last_name']) ===
            $normalize($official['last_name'])

            && $normalize($registration['suffix'] ?? '') ===
            $normalize($official['suffix'] ?? '')

            && $registration['year_level'] ===
            $official['year_level']

            && $registration['hk_discount_type'] ===
            $official['hk_discount_type'];


        if ($isMatch) {

            $verificationStatus =
                'matched';

        } else {

            $verificationStatus =
                'mismatch';
        }


        $update =
            $this->pdo->prepare(
                "UPDATE scholar_registrations
                SET
                    verification_status = :verification_status,
                    matched_registration_record_id = :record_id
                WHERE id = :id"
            );

        $update->execute([
            'verification_status' =>
                $verificationStatus,

            'record_id' =>
                $official['id'],

            'id' =>
                $registrationId
        ]);


        return true;
    }

    public function approveRegistration(
        int $registrationId,
        int $adminUserId
    ): bool {

        try {

            $this->pdo->beginTransaction();

            /*
            * Get the registration.
            */
            $sql = "SELECT *
                    FROM scholar_registrations
                    WHERE id = :id
                    LIMIT 1";

            $stmt =
                $this->pdo->prepare($sql);

            $stmt->execute([
                'id' => $registrationId
            ]);

            $registration =
                $stmt->fetch();

            if (!$registration) {
                $this->pdo->rollBack();
                return false;
            }

            /*
            * Only Pending registrations
            * can be approved.
            */
            if ($registration['status'] !== 'pending') {
                $this->pdo->rollBack();
                return false;
            }

            /*
            * Registration must be verified
            * before approval.
            */
            if ($registration['verification_status'] !== 'matched') {
                $this->pdo->rollBack();
                return false;
            }

            /*
            * Make sure the email does not
            * already exist in users.
            */
            $emailCheck =
                $this->pdo->prepare(
                    "SELECT id
                    FROM users
                    WHERE phinmaed_email = :email
                    LIMIT 1"
                );

            $emailCheck->execute([
                'email' =>
                    $registration['phinmaed_email']
            ]);

            if ($emailCheck->fetch()) {
                $this->pdo->rollBack();
                return false;
            }

            /*
            * Create the Scholar's user account.
            */
            $userInsert =
                $this->pdo->prepare(
                    "INSERT INTO users (
                        phinmaed_email,
                        password,
                        role,
                        status
                    ) VALUES (
                        :email,
                        :password,
                        'scholar',
                        'active'
                    )"
                );

            $userInsert->execute([
                'email' =>
                    $registration['phinmaed_email'],

                'password' =>
                    $registration['password']
            ]);

            $userId =
                (int) $this->pdo->lastInsertId();

            /*
            * Create the Scholar record.
            */
            $scholarInsert =
                $this->pdo->prepare(
                    "INSERT INTO scholars (
                        user_id,
                        student_id_number,
                        course_id,
                        first_name,
                        middle_name,
                        last_name,
                        suffix,
                        year_level,
                        hk_discount_type
                    ) VALUES (
                        :user_id,
                        :student_id_number,
                        :course_id,
                        :first_name,
                        :middle_name,
                        :last_name,
                        :suffix,
                        :year_level,
                        :hk_discount_type
                    )"
                );

            $scholarInsert->execute([
                'user_id' =>
                    $userId,

                'student_id_number' =>
                    $registration['student_id_number'],

                'course_id' =>
                    $registration['course_id'],

                'first_name' =>
                    $registration['first_name'],

                'middle_name' =>
                    $registration['middle_name'],

                'last_name' =>
                    $registration['last_name'],

                'suffix' =>
                    $registration['suffix'],

                'year_level' =>
                    $registration['year_level'],

                'hk_discount_type' =>
                    $registration['hk_discount_type']
            ]);

            /*
            * Mark registration as Approved.
            */
            $registrationUpdate =
                $this->pdo->prepare(
                    "UPDATE scholar_registrations
                    SET
                        status = 'approved',
                        reviewed_by = :reviewed_by,
                        reviewed_at = NOW()
                    WHERE id = :id"
                );

            $registrationUpdate->execute([
                'reviewed_by' =>
                    $adminUserId,

                'id' =>
                    $registrationId
            ]);

            /*
            * Everything succeeded.
            */
            $this->pdo->commit();

            (new Notification($this->pdo))->createNotification(
                $userId,
                'Registration Approved',
                'Your scholar registration has been approved. You can now log in.',
                'system',
                $registrationId
            );

            return true;

        } catch (PDOException $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return false;
        }
    }

    public function rejectRegistration(
        int $registrationId,
        int $adminUserId,
        string $rejectionReason
    ): bool {
        $rejectionReason = trim($rejectionReason);

        if ($rejectionReason === '') {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            $check = $this->pdo->prepare(
                'SELECT id, status FROM scholar_registrations WHERE id = :id LIMIT 1'
            );
            $check->execute(['id' => $registrationId]);
            $registration = $check->fetch();

            if (!$registration || $registration['status'] !== 'pending') {
                $this->pdo->rollBack();
                return false;
            }

            $update = $this->pdo->prepare(
                "UPDATE scholar_registrations
                 SET status = 'rejected',
                     reviewed_by = :reviewed_by,
                     reviewed_at = NOW(),
                     rejection_reason = :reason
                 WHERE id = :id
                   AND status = 'pending'"
            );
            $update->execute([
                'reviewed_by' => $adminUserId,
                'reason' => $rejectionReason,
                'id' => $registrationId,
            ]);

            if ($update->rowCount() !== 1) {
                $this->pdo->rollBack();
                return false;
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }

}