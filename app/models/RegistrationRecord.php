<?php

class RegistrationRecord
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }


    public function findByStudentId(
        string $studentIdNumber
    ): ?array {

        $sql = "SELECT
                    rr.*,
                    ri.file_name,
                    ri.created_at AS import_created_at
                FROM registration_records rr
                INNER JOIN registration_imports ri
                    ON rr.registration_import_id = ri.id
                WHERE rr.student_id_number = :student_id_number
                AND rr.status = 'valid'
                AND ri.status = 'approved'
                ORDER BY ri.created_at DESC
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'student_id_number' => $studentIdNumber
        ]);

        $record = $stmt->fetch();

        return $record ?: null;
    }


    public function getRecordsByImport(
        int $importId
    ): array {

        $sql = "SELECT *
                FROM registration_records
                WHERE registration_import_id = :import_id
                ORDER BY id ASC";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'import_id' => $importId
        ]);

        return $stmt->fetchAll();
    }
}