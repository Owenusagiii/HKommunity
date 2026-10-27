<?php

class RenewalPeriod
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(): array
    {
        return $this->pdo->query(
            "SELECT rp.*, s.semester, a.academic_year
             FROM renewal_periods rp
             JOIN semesters s ON s.id = rp.semester_id
             JOIN academic_years a ON a.id = s.academic_year_id
             ORDER BY a.academic_year DESC,
                      FIELD(s.semester, '1st Semester', '2nd Semester') DESC,
                      rp.year_level"
        )->fetchAll();
    }

    public function create(int $semesterId, string $yearLevel, float $hours): bool
    {
        if (!in_array($yearLevel, ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'], true) || $hours < 0 || $hours > 999.99) {
            return false;
        }
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO renewal_periods
                    (semester_id, year_level, required_duty_hours, status)
                 VALUES (:semester_id, :year_level, :hours, 'active')"
            );
            return $stmt->execute([
                'semester_id' => $semesterId,
                'year_level' => $yearLevel,
                'hours' => $hours,
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function update(int $id, int $semesterId, string $yearLevel, float $hours): bool
    {
        if (
            $id < 1
            || $semesterId < 1
            || !in_array($yearLevel, ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'], true)
            || $hours < 0
            || $hours > 999.99
        ) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE renewal_periods
                 SET semester_id = :semester_id,
                     year_level = :year_level,
                     required_duty_hours = :hours
                 WHERE id = :id'
            );
            $stmt->execute([
                'semester_id' => $semesterId,
                'year_level' => $yearLevel,
                'hours' => $hours,
                'id' => $id,
            ]);
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
        $stmt = $this->pdo->prepare(
            "UPDATE renewal_periods SET status = :status WHERE id = :id"
        );
        $stmt->execute(['status' => $status, 'id' => $id]);
        return $stmt->rowCount() === 1;
    }

    public function hasLinkedRecords(int $id): bool
    {
        foreach (['renewals', 'scholar_requirements', 'duty_records', 'duty_submissions'] as $table) {
            $stmt = $this->pdo->prepare("SELECT 1 FROM {$table} WHERE renewal_period_id = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
            if ($stmt->fetchColumn()) {
                return true;
            }
        }

        return false;
    }

    public function delete(int $id): bool
    {
        if ($id < 1 || $this->hasLinkedRecords($id)) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare('DELETE FROM renewal_periods WHERE id = :id');
            $stmt->execute(['id' => $id]);
            return $stmt->rowCount() === 1;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function findActiveForScholar(int $scholarId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT rp.*, s.semester, a.academic_year
             FROM renewal_periods rp
             JOIN semesters s ON s.id = rp.semester_id
             JOIN academic_years a ON a.id = s.academic_year_id
             JOIN scholars sc ON sc.year_level = rp.year_level
             WHERE sc.id = :scholar_id
               AND rp.status = 'active'
               AND s.status = 'active'
               AND a.status = 'active'
             ORDER BY a.academic_year DESC,
                      FIELD(s.semester, '1st Semester', '2nd Semester') DESC
             LIMIT 1"
        );
        $stmt->execute(['scholar_id' => $scholarId]);
        return $stmt->fetch() ?: null;
    }

    public function findActiveForYearLevel(string $yearLevel): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT rp.*, s.semester, a.academic_year
             FROM renewal_periods rp
             JOIN semesters s ON s.id = rp.semester_id
             JOIN academic_years a ON a.id = s.academic_year_id
             WHERE rp.year_level = :year_level
               AND rp.status = 'active'
               AND s.status = 'active'
               AND a.status = 'active'
             ORDER BY a.academic_year DESC,
                      FIELD(s.semester, '1st Semester', '2nd Semester') DESC
             LIMIT 1"
        );
        $stmt->execute(['year_level' => $yearLevel]);
        return $stmt->fetch() ?: null;
    }

    public function findPreviousForActive(int $activePeriodId): ?array
    {
        $activeStmt = $this->pdo->prepare(
            "SELECT rp.year_level, ay.academic_year, s.semester
             FROM renewal_periods rp
             JOIN semesters s ON s.id = rp.semester_id
             JOIN academic_years ay ON ay.id = s.academic_year_id
             WHERE rp.id = :period_id
               AND rp.status = 'active'
               AND s.status = 'active'
               AND ay.status = 'active'"
        );
        $activeStmt->execute(['period_id' => $activePeriodId]);
        $active = $activeStmt->fetch();
        if (!$active) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT rp.*, s.semester, ay.academic_year
             FROM renewal_periods rp
             JOIN semesters s ON s.id = rp.semester_id
             JOIN academic_years ay ON ay.id = s.academic_year_id
             WHERE rp.year_level = :year_level
               AND (
                    ay.academic_year < :academic_year
                    OR (
                        ay.academic_year = :academic_year_equal
                        AND FIELD(s.semester, '1st Semester', '2nd Semester')
                            < FIELD(:semester, '1st Semester', '2nd Semester')
                    )
               )
             ORDER BY ay.academic_year DESC,
                      FIELD(s.semester, '1st Semester', '2nd Semester') DESC
             LIMIT 1"
        );
        $stmt->execute([
            'year_level' => $active['year_level'],
            'academic_year' => $active['academic_year'],
            'academic_year_equal' => $active['academic_year'],
            'semester' => $active['semester'],
        ]);
        return $stmt->fetch() ?: null;
    }

    public function findWorkPeriodForScholar(int $scholarId, string $yearLevel): ?array
    {
        $activePeriod = $this->findActiveForYearLevel($yearLevel);
        if (!$activePeriod) {
            return null;
        }

        if ($this->hasRenewal($scholarId, $yearLevel, (int) $activePeriod['id'])) {
            $activePeriod['catch_up'] = false;
            return $activePeriod;
        }

        $previousPeriod = $this->findPreviousForActive((int) $activePeriod['id']);
        if ($previousPeriod) {
            $previousPeriod['catch_up'] = true;
            return $previousPeriod;
        }

        return null;
    }

    private function hasRenewal(int $scholarId, string $yearLevel, int $periodId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT rn.id
             FROM renewals rn
             JOIN scholars sc ON sc.id = rn.scholar_id
             JOIN renewal_periods rp ON rp.id = rn.renewal_period_id
             WHERE rn.scholar_id = :scholar_id
               AND sc.year_level = :year_level
               AND rp.year_level = sc.year_level
               AND rn.renewal_period_id = :period_id
             LIMIT 1"
        );
        $stmt->execute([
            'scholar_id' => $scholarId,
            'year_level' => $yearLevel,
            'period_id' => $periodId,
        ]);
        return (bool) $stmt->fetchColumn();
    }
}
