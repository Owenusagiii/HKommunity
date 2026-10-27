<?php

class Semester
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(): array
    {
        return $this->pdo->query(
            "SELECT s.*, a.academic_year
             FROM semesters s
             JOIN academic_years a ON a.id = s.academic_year_id
             ORDER BY a.academic_year DESC,
                      FIELD(s.semester, '1st Semester', '2nd Semester') DESC"
        )->fetchAll();
    }

    public function create(int $academicYearId, string $semester): bool
    {
        if (!in_array($semester, ['1st Semester', '2nd Semester'], true)) {
            return false;
        }
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO semesters (academic_year_id, semester, status)
                 VALUES (:academic_year_id, :semester, 'inactive')"
            );
            return $stmt->execute([
                'academic_year_id' => $academicYearId,
                'semester' => $semester,
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function hasRenewalPeriods(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM renewal_periods WHERE semester_id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    public function delete(int $id): bool
    {
        if ($id < 1 || $this->hasRenewalPeriods($id)) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare('DELETE FROM semesters WHERE id = :id');
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

            $target = $this->pdo->prepare(
                'SELECT academic_year_id FROM semesters WHERE id = :id FOR UPDATE'
            );
            $target->execute(['id' => $id]);
            $academicYearId = $target->fetchColumn();
            if ($academicYearId === false) {
                $this->pdo->rollBack();
                return false;
            }

            if ($status === 'active') {
                $deactivateYears = $this->pdo->prepare(
                    "UPDATE academic_years SET status = 'inactive' WHERE id <> :id"
                );
                $deactivateYears->execute(['id' => $academicYearId]);

                $activateYear = $this->pdo->prepare(
                    "UPDATE academic_years SET status = 'active' WHERE id = :id"
                );
                $activateYear->execute(['id' => $academicYearId]);

                $deactivateSemesters = $this->pdo->prepare(
                    "UPDATE semesters SET status = 'inactive' WHERE id <> :id"
                );
                $deactivateSemesters->execute(['id' => $id]);
            }

            $stmt = $this->pdo->prepare(
                "UPDATE semesters SET status = :status WHERE id = :id"
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
