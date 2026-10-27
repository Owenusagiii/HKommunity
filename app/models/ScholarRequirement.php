<?php

require_once __DIR__ . '/RenewalPeriod.php';

class ScholarRequirement
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function allForScholar(int $scholarId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT sr.*, r.name, r.description, r.status AS requirement_status,
                    rp.year_level, rp.required_duty_hours, sm.semester, ay.academic_year
             FROM scholar_requirements sr
             JOIN requirements r ON r.id = sr.requirement_id
             JOIN renewal_periods rp ON rp.id = sr.renewal_period_id
             JOIN semesters sm ON sm.id = rp.semester_id
             JOIN academic_years ay ON ay.id = sm.academic_year_id
             WHERE sr.scholar_id = :scholar_id
             ORDER BY ay.academic_year DESC,
                      FIELD(sm.semester, '1st Semester', '2nd Semester') DESC,
                      sr.id DESC"
        );
        $stmt->execute(['scholar_id' => $scholarId]);
        return $stmt->fetchAll();
    }

    public function allForCoordinator(string $yearLevel): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT sr.*, r.name, r.description, sc.student_id_number,
                    sc.first_name, sc.middle_name, sc.last_name, sc.suffix,
                    sc.year_level, rp.required_duty_hours, sm.semester, ay.academic_year
             FROM scholar_requirements sr
             JOIN requirements r ON r.id = sr.requirement_id
             JOIN scholars sc ON sc.id = sr.scholar_id
             JOIN renewal_periods rp ON rp.id = sr.renewal_period_id
             JOIN semesters sm ON sm.id = rp.semester_id
             JOIN academic_years ay ON ay.id = sm.academic_year_id
             WHERE sc.year_level = :year_level
             ORDER BY ay.academic_year DESC,
                      FIELD(sm.semester, '1st Semester', '2nd Semester') DESC,
                      sr.id DESC"
        );
        $stmt->execute(['year_level' => $yearLevel]);
        return $stmt->fetchAll();
    }

    public function assign(
        int $scholarId,
        int $requirementId,
        int $renewalPeriodId,
        int $changedBy = 0
    ): bool {
        $check = $this->pdo->prepare(
            "SELECT sc.year_level AS scholar_year, rp.year_level AS period_year,
                    r.status AS requirement_status
             FROM scholars sc
             JOIN renewal_periods rp ON rp.id = :period_id
             JOIN semesters sm ON sm.id = rp.semester_id
             JOIN academic_years ay ON ay.id = sm.academic_year_id
             JOIN requirements r ON r.id = :requirement_id
             WHERE sc.id = :scholar_id"
        );
        $check->execute([
            'scholar_id' => $scholarId,
            'period_id' => $renewalPeriodId,
            'requirement_id' => $requirementId,
        ]);
        $record = $check->fetch();

        $workPeriod = $record
            ? (new RenewalPeriod($this->pdo))->findWorkPeriodForScholar($scholarId, $record['scholar_year'])
            : null;
        if (!$record
            || $record['scholar_year'] !== $record['period_year']
            || !$workPeriod
            || (int) $workPeriod['id'] !== $renewalPeriodId
            || $record['requirement_status'] !== 'active') {
            return false;
        }

        $exists = $this->pdo->prepare(
            "SELECT id FROM scholar_requirements
             WHERE scholar_id = :scholar_id
               AND requirement_id = :requirement_id
               AND renewal_period_id = :period_id
             LIMIT 1"
        );
        $exists->execute([
            'scholar_id' => $scholarId,
            'requirement_id' => $requirementId,
            'period_id' => $renewalPeriodId,
        ]);

        if ($exists->fetchColumn()) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            $insert = $this->pdo->prepare(
                "INSERT INTO scholar_requirements
                    (scholar_id, requirement_id, renewal_period_id, status)
                 VALUES (:scholar_id, :requirement_id, :period_id, 'assigned')"
            );
            $insert->execute([
                'scholar_id' => $scholarId,
                'requirement_id' => $requirementId,
                'period_id' => $renewalPeriodId,
            ]);
            $id = (int) $this->pdo->lastInsertId();

            if ($changedBy > 0) {
                $history = $this->pdo->prepare(
                    "INSERT INTO scholar_requirement_history
                        (scholar_requirement_id, status, changed_by)
                     VALUES (:id, 'assigned', :changed_by)"
                );
                $history->execute(['id' => $id, 'changed_by' => $changedBy]);
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

    public function assignMany(
        array $scholarIds,
        array $requirementIds,
        int $renewalPeriodId,
        int $changedBy = 0
    ): int {
        $count = 0;

        foreach ($scholarIds as $scholarId) {
            foreach ($requirementIds as $requirementId) {
                if ($this->assign((int) $scholarId, (int) $requirementId, $renewalPeriodId, $changedBy)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    public function submit(int $id, int $scholarId, string $link, int $changedBy): bool
    {
        $link = trim($link);
        if (!filter_var($link, FILTER_VALIDATE_URL)) {
            return false;
        }

        $host = strtolower((string) parse_url($link, PHP_URL_HOST));
        if ($host !== 'drive.google.com' && !str_ends_with($host, '.drive.google.com')) {
            return false;
        }

        $periodStmt = $this->pdo->prepare(
            'SELECT renewal_period_id
             FROM scholar_requirements
             WHERE id = :id AND scholar_id = :scholar_id'
        );
        $periodStmt->execute([
            'id' => $id,
            'scholar_id' => $scholarId,
        ]);
        $periodId = $periodStmt->fetchColumn();
        $scholarStmt = $this->pdo->prepare(
            'SELECT year_level FROM scholars WHERE id = :scholar_id'
        );
        $scholarStmt->execute(['scholar_id' => $scholarId]);
        $yearLevel = $scholarStmt->fetchColumn();
        $periodModel = new RenewalPeriod($this->pdo);
        $activePeriod = $yearLevel !== false ? $periodModel->findActiveForScholar($scholarId) : null;
        $workPeriod = $yearLevel !== false
            ? $periodModel->findWorkPeriodForScholar($scholarId, (string) $yearLevel)
            : null;
        if (
            $periodId === false
            || !$activePeriod
            || !$workPeriod
            || (int) $workPeriod['id'] !== (int) $activePeriod['id']
            || (int) $activePeriod['id'] !== (int) $periodId
        ) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "UPDATE scholar_requirements
                 SET gdrive_link = :link, status = 'submitted',
                     rejection_reason = NULL, updated_at = NOW()
                 WHERE id = :id
                   AND scholar_id = :scholar_id
                   AND status IN ('assigned', 'rejected')"
            );
            $stmt->execute([
                'link' => $link,
                'id' => $id,
                'scholar_id' => $scholarId,
            ]);

            if ($stmt->rowCount() !== 1) {
                $this->pdo->rollBack();
                return false;
            }

            $history = $this->pdo->prepare(
                "INSERT INTO scholar_requirement_history
                    (scholar_requirement_id, status, changed_by)
                 VALUES (:id, 'submitted', :changed_by)"
            );
            $history->execute(['id' => $id, 'changed_by' => $changedBy]);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }

    public function review(int $id, string $status, ?string $reason, int $changedBy): bool
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            return false;
        }

        $reason = trim((string) $reason);
        if ($status === 'rejected' && $reason === '') {
            return false;
        }

        $cycleStmt = $this->pdo->prepare(
            'SELECT sr.scholar_id, sr.renewal_period_id, sc.year_level
             FROM scholar_requirements sr
             JOIN scholars sc ON sc.id = sr.scholar_id
             WHERE sr.id = :id'
        );
        $cycleStmt->execute(['id' => $id]);
        $cycle = $cycleStmt->fetch();
        $workPeriod = $cycle
            ? (new RenewalPeriod($this->pdo))->findWorkPeriodForScholar(
                (int) $cycle['scholar_id'],
                $cycle['year_level']
            )
            : null;
        if (!$cycle || !$workPeriod || (int) $workPeriod['id'] !== (int) $cycle['renewal_period_id']) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "UPDATE scholar_requirements
                 SET status = :status, rejection_reason = :reason, updated_at = NOW()
                 WHERE id = :id AND status = 'submitted'"
            );
            $stmt->execute([
                'status' => $status,
                'reason' => $status === 'rejected' ? $reason : null,
                'id' => $id,
            ]);

            if ($stmt->rowCount() !== 1) {
                $this->pdo->rollBack();
                return false;
            }

            $history = $this->pdo->prepare(
                "INSERT INTO scholar_requirement_history
                    (scholar_requirement_id, status, changed_by)
                 VALUES (:id, :status, :changed_by)"
            );
            $history->execute([
                'id' => $id,
                'status' => $status,
                'changed_by' => $changedBy,
            ]);

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
