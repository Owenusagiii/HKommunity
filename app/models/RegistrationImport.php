<?php

class RegistrationImport
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }


    public function createImport(
        string $fileName,
        string $fileType,
        int $importedBy
    ): int {

        $sql = "INSERT INTO registration_imports (
                    file_name,
                    file_type,
                    status,
                    imported_by
                ) VALUES (
                    :file_name,
                    :file_type,
                    'pending',
                    :imported_by
                )";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'file_name' => $fileName,
            'file_type' => $fileType,
            'imported_by' => $importedBy
        ]);

        return (int) $this->pdo->lastInsertId();
    }


    public function createRegistrationRecord(
        int $importId,
        string $studentIdNumber,
        ?string $course,
        string $firstName,
        ?string $middleName,
        string $lastName,
        ?string $suffix,
        ?string $yearLevel,
        ?string $hkDiscountType,
        string $status,
        ?string $errorMessage
    ): bool {

        $sql = "INSERT INTO registration_records (
                    registration_import_id,
                    student_id_number,
                    course,
                    first_name,
                    middle_name,
                    last_name,
                    suffix,
                    year_level,
                    hk_discount_type,
                    status,
                    error_message
                ) VALUES (
                    :registration_import_id,
                    :student_id_number,
                    :course,
                    :first_name,
                    :middle_name,
                    :last_name,
                    :suffix,
                    :year_level,
                    :hk_discount_type,
                    :status,
                    :error_message
                )";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            'registration_import_id' => $importId,
            'student_id_number' => $studentIdNumber,
            'course' => $course,
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'suffix' => $suffix,
            'year_level' => $yearLevel,
            'hk_discount_type' => $hkDiscountType,
            'status' => $status,
            'error_message' => $errorMessage
        ]);
    }


    public function updateImportStatus(
        int $importId,
        string $status
    ): bool {

        $sql = "UPDATE registration_imports
                SET status = :status
                WHERE id = :id
                  AND status <> 'approved'";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            'status' => $status,
            'id' => $importId
        ]);
    }

    public function hasLinkedRegistrations(int $importId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT EXISTS (
                SELECT 1
                FROM registration_records rr
                JOIN scholar_registrations sr
                  ON sr.matched_registration_record_id = rr.id
                WHERE rr.registration_import_id = :import_id
            )'
        );
        $stmt->execute(['import_id' => $importId]);
        return (bool) $stmt->fetchColumn();
    }

    public function deleteImport(int $importId): bool
    {
        try {
            $this->pdo->beginTransaction();

            $importStmt = $this->pdo->prepare(
                'SELECT id FROM registration_imports WHERE id = :id FOR UPDATE'
            );
            $importStmt->execute(['id' => $importId]);
            if (!$importStmt->fetchColumn() || $this->hasLinkedRegistrations($importId)) {
                $this->pdo->rollBack();
                return false;
            }

            $recordsStmt = $this->pdo->prepare(
                'DELETE FROM registration_records WHERE registration_import_id = :import_id'
            );
            $recordsStmt->execute(['import_id' => $importId]);

            $deleteStmt = $this->pdo->prepare(
                'DELETE FROM registration_imports WHERE id = :id'
            );
            $deleteStmt->execute(['id' => $importId]);
            if ($deleteStmt->rowCount() !== 1) {
                $this->pdo->rollBack();
                return false;
            }

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }

    public function getImportById(
        int $importId
    ): ?array {

        $sql = "SELECT *
                FROM registration_imports
                WHERE id = :id
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'id' => $importId
        ]);

        $import = $stmt->fetch();

        return $import ?: null;
    }

        public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }


    public function commit(): bool
    {
        return $this->pdo->commit();
    }


    public function rollBack(): bool
    {
        if ($this->pdo->inTransaction()) {
            return $this->pdo->rollBack();
        }

        return false;
    }
    public function all(): array
    {
        $stmt = $this->pdo->query(
            "SELECT ri.*, u.phinmaed_email
             FROM registration_imports ri
             JOIN users u ON u.id = ri.imported_by
             ORDER BY ri.created_at DESC"
        );
        return $stmt->fetchAll();
    }

    public function updateExistingScholarYearLevels(int $importId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT student_id_number, year_level
             FROM registration_records
             WHERE registration_import_id = :import_id
               AND status = 'valid'
               AND year_level IS NOT NULL"
        );
        $stmt->execute(['import_id' => $importId]);

        $update = $this->pdo->prepare(
            "UPDATE scholars
             SET year_level = :year_level
             WHERE student_id_number = :student_id_number"
        );

        $count = 0;
        foreach ($stmt->fetchAll() as $record) {
            $update->execute([
                'year_level' => $record['year_level'],
                'student_id_number' => $record['student_id_number'],
            ]);
            $count += $update->rowCount();
        }

        return $count;
    }

}