<?php

class AcademicYear
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(): array
    {
        return $this->pdo->query(
            "SELECT * FROM academic_years ORDER BY academic_year DESC"
        )->fetchAll();
    }

    public function create(string $academicYear): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO academic_years (academic_year, status) VALUES (:value, 'inactive')"
            );
            return $stmt->execute(['value' => trim($academicYear)]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function hasSemesters(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM semesters WHERE academic_year_id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    public function delete(int $id): bool
    {
        if ($id < 1 || $this->hasSemesters($id)) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare('DELETE FROM academic_years WHERE id = :id');
            $stmt->execute(['id' => $id]);
            return $stmt->rowCount() === 1;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['active', 'inactive'], true)) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            $exists = $this->pdo->prepare(
                'SELECT id FROM academic_years WHERE id = :id FOR UPDATE'
            );
            $exists->execute(['id' => $id]);
            if (!$exists->fetchColumn()) {
                $this->pdo->rollBack();
                return false;
            }

            if ($status === 'active') {
                $deactivateYears = $this->pdo->prepare(
                    "UPDATE academic_years SET status = 'inactive' WHERE id <> :id"
                );
                $deactivateYears->execute(['id' => $id]);

                $deactivateSemesters = $this->pdo->prepare(
                    "UPDATE semesters SET status = 'inactive'"
                );
                $deactivateSemesters->execute();
            } else {
                $deactivateSemesters = $this->pdo->prepare(
                    "UPDATE semesters SET status = 'inactive' WHERE academic_year_id = :id"
                );
                $deactivateSemesters->execute(['id' => $id]);
            }

            $stmt = $this->pdo->prepare(
                "UPDATE academic_years SET status = :status WHERE id = :id"
            );
            $stmt->execute(['status' => $status, 'id' => $id]);
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }
}
