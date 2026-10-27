<?php

class DutyRecord
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(
        ?int $scholarId = null,
        ?string $studentId = null,
        ?int $coordinatorId = null,
        ?int $periodId = null
    ): array {
        $sql = "SELECT
                    dr.*,
                    sc.student_id_number,
                    sc.first_name,
                    sc.middle_name,
                    sc.last_name,
                    sc.suffix,
                    rp.year_level,
                    sm.semester,
                    ay.academic_year
                FROM duty_records dr
                JOIN scholars sc ON sc.id = dr.scholar_id
                JOIN renewal_periods rp ON rp.id = dr.renewal_period_id
                JOIN semesters sm ON sm.id = rp.semester_id
                JOIN academic_years ay ON ay.id = sm.academic_year_id
                WHERE 1 = 1";

        $params = [];

        if ($scholarId !== null) {
            $sql .= " AND dr.scholar_id = :scholar_id";
            $params['scholar_id'] = $scholarId;
        }

        if ($studentId !== null && $studentId !== '') {
            $sql .= " AND sc.student_id_number LIKE :student_id";
            $params['student_id'] = '%' . $studentId . '%';
        }

        if ($coordinatorId !== null) {
            $sql .= " AND dr.coordinator_id = :coordinator_id";
            $params['coordinator_id'] = $coordinatorId;
        }

        if ($periodId !== null) {
            $sql .= " AND dr.renewal_period_id = :period_id";
            $params['period_id'] = $periodId;
        }

        $sql .= " ORDER BY dr.duty_date DESC, dr.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function forCoordinatorYear(string $yearLevel): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                dr.*,
                sc.student_id_number,
                sc.first_name,
                sc.middle_name,
                sc.last_name,
                sc.suffix,
                rp.year_level,
                rp.required_duty_hours,
                sm.semester,
                ay.academic_year
             FROM duty_records dr
             JOIN scholars sc ON sc.id = dr.scholar_id
             JOIN renewal_periods rp ON rp.id = dr.renewal_period_id
             JOIN semesters sm ON sm.id = rp.semester_id
             JOIN academic_years ay ON ay.id = sm.academic_year_id
             WHERE sc.year_level = :year_level
             ORDER BY dr.duty_date DESC, dr.id DESC"
        );

        $stmt->execute(['year_level' => $yearLevel]);
        return $stmt->fetchAll();
    }

    public function totalHours(int $scholarId, int $periodId): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(hours), 0)
             FROM duty_records
             WHERE scholar_id = :scholar_id
               AND renewal_period_id = :period_id"
        );

        $stmt->execute([
            'scholar_id' => $scholarId,
            'period_id' => $periodId,
        ]);

        return (float) $stmt->fetchColumn();
    }
}
