<?php

require_once __DIR__ . '/RenewalPeriod.php';

class Renewal
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function statusLabels(): array
    {
        return [
            'not_started' => 'Awaiting Coordinator',
            'in_progress' => 'In Progress',
            'for_review' => 'For Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'completed' => 'Completed',
        ];
    }

    public static function allowedStatuses(): array
    {
        return array_keys(self::statusLabels());
    }

    public function getForScholar(int $scholarId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT rn.*, rp.year_level, rp.required_duty_hours,
                    sm.semester, ay.academic_year
             FROM renewals rn
             JOIN renewal_periods rp ON rp.id = rn.renewal_period_id
             JOIN semesters sm ON sm.id = rp.semester_id
             JOIN academic_years ay ON ay.id = sm.academic_year_id
             WHERE rn.scholar_id = :scholar_id
             ORDER BY ay.academic_year DESC,
                      FIELD(sm.semester, '1st Semester', '2nd Semester') DESC"
        );
        $stmt->execute(['scholar_id' => $scholarId]);
        return $stmt->fetchAll();
    }

    public function getForCoordinatorYear(string $yearLevel, ?string $studentId = null): array
    {
        $sql = "SELECT rn.*, sc.student_id_number, sc.first_name, sc.middle_name,
                    sc.last_name, sc.suffix, rp.year_level,
                    rp.required_duty_hours, sm.semester, ay.academic_year
             FROM renewals rn
             JOIN scholars sc ON sc.id = rn.scholar_id
             JOIN renewal_periods rp ON rp.id = rn.renewal_period_id
             JOIN semesters sm ON sm.id = rp.semester_id
             JOIN academic_years ay ON ay.id = sm.academic_year_id
             WHERE sc.year_level = :year_level
             ";
        $params = ['year_level' => $yearLevel];

        if ($studentId !== null && $studentId !== '') {
            $sql .= ' AND sc.student_id_number LIKE :student_id';
            $params['student_id'] = '%' . $studentId . '%';
        }

        $sql .= " ORDER BY ay.academic_year DESC,
                           FIELD(sm.semester, '1st Semester', '2nd Semester') DESC,
                           sc.last_name, sc.first_name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getAll(): array
    {
        return $this->pdo->query(
            "SELECT rn.*, sc.student_id_number, sc.first_name, sc.middle_name,
                    sc.last_name, sc.suffix, rp.year_level,
                    rp.required_duty_hours, sm.semester, ay.academic_year
             FROM renewals rn
             JOIN scholars sc ON sc.id = rn.scholar_id
             JOIN renewal_periods rp ON rp.id = rn.renewal_period_id
             JOIN semesters sm ON sm.id = rp.semester_id
             JOIN academic_years ay ON ay.id = sm.academic_year_id
             ORDER BY ay.academic_year DESC,
                      FIELD(sm.semester, '1st Semester', '2nd Semester') DESC,
                      sc.last_name, sc.first_name"
        )->fetchAll();
    }

    public function previousPeriod(int $activePeriodId): ?array
    {
        return (new RenewalPeriod($this->pdo))->findPreviousForActive($activePeriodId);
    }

    public function getPromotionCandidates(string $yearLevel, int $activePeriodId): array
    {
        $activeStmt = $this->pdo->prepare(
            "SELECT rp.id
             FROM renewal_periods rp
             WHERE rp.id = :period_id
               AND rp.year_level = :year_level
               AND rp.status = 'active'
               AND EXISTS (
                   SELECT 1 FROM semesters sm
                   JOIN academic_years ay ON ay.id = sm.academic_year_id
                   WHERE sm.id = rp.semester_id
                     AND sm.status = 'active'
                     AND ay.status = 'active'
               )"
        );
        $activeStmt->execute([
            'period_id' => $activePeriodId,
            'year_level' => $yearLevel,
        ]);
        if (!$activeStmt->fetchColumn()) {
            return [];
        }

        $previousPeriod = $this->previousPeriod($activePeriodId);
        if ($previousPeriod && $previousPeriod['year_level'] !== $yearLevel) {
            return [];
        }

        if (!$previousPeriod) {
            $stmt = $this->pdo->prepare(
                "SELECT sc.id, sc.user_id, sc.student_id_number, sc.first_name, sc.middle_name,
                        sc.last_name, sc.suffix,
                        current_rn.id AS current_renewal_id,
                        0 AS rendered_hours,
                        0 AS assigned_count,
                        0 AS unapproved_count,
                        0 AS previous_required_hours,
                        CASE WHEN current_rn.id IS NULL THEN 1 ELSE 0 END AS eligible
                 FROM scholars sc
                 LEFT JOIN renewals current_rn
                    ON current_rn.scholar_id = sc.id
                   AND current_rn.renewal_period_id = :active_period_id
                 WHERE sc.year_level = :year_level
                 ORDER BY sc.last_name, sc.first_name"
            );
            $stmt->execute([
                'active_period_id' => $activePeriodId,
                'year_level' => $yearLevel,
            ]);
            $items = $stmt->fetchAll();
            foreach ($items as &$item) {
                $item['eligible'] = (bool) $item['eligible'];
            }
            unset($item);
            return $items;
        }

        $stmt = $this->pdo->prepare(
            "SELECT sc.id, sc.user_id, sc.student_id_number, sc.first_name, sc.middle_name,
                   sc.last_name, sc.suffix,
                   current_rn.id AS current_renewal_id,
                    COALESCE(hours.rendered_hours, 0) AS rendered_hours,
                    COALESCE(requirements.assigned_count, 0) AS assigned_count,
                    COALESCE(requirements.unapproved_count, 0) AS unapproved_count,
                    previous_rn.status AS previous_renewal_status,
                    :required_hours AS previous_required_hours
             FROM scholars sc
             LEFT JOIN renewals current_rn
                ON current_rn.scholar_id = sc.id
               AND current_rn.renewal_period_id = :active_period_id
             LEFT JOIN (
                SELECT scholar_id, SUM(hours) AS rendered_hours
                FROM duty_records
                WHERE renewal_period_id = :hours_period_id
                GROUP BY scholar_id
             ) hours ON hours.scholar_id = sc.id
             LEFT JOIN (
                SELECT scholar_id,
                       COUNT(*) AS assigned_count,
                       SUM(CASE WHEN status <> 'approved' THEN 1 ELSE 0 END) AS unapproved_count
                FROM scholar_requirements
                WHERE renewal_period_id = :requirements_period_id
                GROUP BY scholar_id
             ) requirements ON requirements.scholar_id = sc.id
             LEFT JOIN renewals previous_rn
                ON previous_rn.scholar_id = sc.id
               AND previous_rn.renewal_period_id = :previous_renewal_period_id
             WHERE sc.year_level = :year_level
             ORDER BY sc.last_name, sc.first_name"
        );
        $stmt->execute([
            'required_hours' => $previousPeriod['required_duty_hours'],
            'active_period_id' => $activePeriodId,
            'hours_period_id' => $previousPeriod['id'],
            'requirements_period_id' => $previousPeriod['id'],
            'previous_renewal_period_id' => $previousPeriod['id'],
            'year_level' => $yearLevel,
        ]);

        $items = $stmt->fetchAll();
        foreach ($items as &$item) {
            $previousStatus = $item['previous_renewal_status'] ?? null;
            $previousRequirementsApproved = (int) $item['unapproved_count'] === 0;
            $previousDutyHoursMet = (float) $item['rendered_hours'] >= (float) $item['previous_required_hours'];
            $previousCycleQualifies = $previousStatus === 'approved'
                || ($previousStatus === 'completed' && $previousRequirementsApproved && $previousDutyHoursMet);

            $item['eligible'] = $item['current_renewal_id'] === null
                && $previousCycleQualifies;
        }
        unset($item);

        return $items;
    }

    public function promoteScholar(int $scholarId, string $yearLevel, int $changedBy): bool
    {
        try {
            $this->pdo->beginTransaction();

            $scholarStmt = $this->pdo->prepare(
                'SELECT id FROM scholars WHERE id = :id AND year_level = :year_level FOR UPDATE'
            );
            $scholarStmt->execute([
                'id' => $scholarId,
                'year_level' => $yearLevel,
            ]);
            if (!$scholarStmt->fetchColumn()) {
                $this->pdo->rollBack();
                return false;
            }

            $activeStmt = $this->pdo->prepare(
                "SELECT rp.id
                 FROM renewal_periods rp
                 JOIN semesters sm ON sm.id = rp.semester_id
                 JOIN academic_years ay ON ay.id = sm.academic_year_id
                 WHERE rp.year_level = :year_level
                   AND rp.status = 'active'
                   AND sm.status = 'active'
                   AND ay.status = 'active'
                 ORDER BY ay.academic_year DESC,
                          FIELD(sm.semester, '1st Semester', '2nd Semester') DESC
                 LIMIT 1"
            );
            $activeStmt->execute(['year_level' => $yearLevel]);
            $activePeriodId = $activeStmt->fetchColumn();
            if ($activePeriodId === false) {
                $this->pdo->rollBack();
                return false;
            }

            $previousPeriod = $this->previousPeriod((int) $activePeriodId);
            if ($previousPeriod) {
                $previousRenewalStmt = $this->pdo->prepare(
                    'SELECT status FROM renewals
                     WHERE scholar_id = :scholar_id AND renewal_period_id = :period_id
                     FOR UPDATE'
                );
                $previousRenewalStmt->execute([
                    'scholar_id' => $scholarId,
                    'period_id' => $previousPeriod['id'],
                ]);
                $previousRenewalStatus = $previousRenewalStmt->fetchColumn();

                if ($previousRenewalStatus !== 'approved') {
                    if ($previousRenewalStatus !== 'completed') {
                        $this->pdo->rollBack();
                        return false;
                    }

                    $hoursStmt = $this->pdo->prepare(
                        'SELECT COALESCE(SUM(hours), 0)
                         FROM duty_records
                         WHERE scholar_id = :scholar_id AND renewal_period_id = :period_id'
                    );
                    $hoursStmt->execute([
                        'scholar_id' => $scholarId,
                        'period_id' => $previousPeriod['id'],
                    ]);
                    if ((float) $hoursStmt->fetchColumn() < (float) $previousPeriod['required_duty_hours']) {
                        $this->pdo->rollBack();
                        return false;
                    }

                    $requirementsStmt = $this->pdo->prepare(
                        "SELECT COUNT(*)
                         FROM scholar_requirements
                         WHERE scholar_id = :scholar_id
                           AND renewal_period_id = :period_id
                           AND status <> 'approved'"
                    );
                    $requirementsStmt->execute([
                        'scholar_id' => $scholarId,
                        'period_id' => $previousPeriod['id'],
                    ]);
                    if ((int) $requirementsStmt->fetchColumn() > 0) {
                        $this->pdo->rollBack();
                        return false;
                    }
                }
            }

            $currentRenewalStmt = $this->pdo->prepare(
                'SELECT id FROM renewals
                 WHERE scholar_id = :scholar_id AND renewal_period_id = :period_id'
            );
            $currentRenewalStmt->execute([
                'scholar_id' => $scholarId,
                'period_id' => $activePeriodId,
            ]);
            if ($currentRenewalStmt->fetchColumn()) {
                $this->pdo->rollBack();
                return false;
            }

            $insert = $this->pdo->prepare(
                "INSERT INTO renewals (scholar_id, renewal_period_id, status)
                 VALUES (:scholar_id, :period_id, 'in_progress')"
            );
            $insert->execute([
                'scholar_id' => $scholarId,
                'period_id' => $activePeriodId,
            ]);
            $renewalId = (int) $this->pdo->lastInsertId();

            if ($changedBy > 0) {
                $history = $this->pdo->prepare(
                    "INSERT INTO renewal_history (renewal_id, status, changed_by)
                     VALUES (:renewal_id, 'in_progress', :changed_by)"
                );
                $history->execute([
                    'renewal_id' => $renewalId,
                    'changed_by' => $changedBy,
                ]);
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

    public function history(int $renewalId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT rh.*, u.phinmaed_email
             FROM renewal_history rh
             JOIN users u ON u.id = rh.changed_by
             WHERE rh.renewal_id = :renewal_id
             ORDER BY rh.changed_at DESC"
        );
        $stmt->execute(['renewal_id' => $renewalId]);
        return $stmt->fetchAll();
    }

    public function hasMetRequiredDutyHours(int $renewalId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT rp.required_duty_hours,
                    COALESCE(SUM(dr.hours), 0) AS rendered_duty_hours
             FROM renewals rn
             JOIN renewal_periods rp ON rp.id = rn.renewal_period_id
             LEFT JOIN duty_records dr
                ON dr.scholar_id = rn.scholar_id
               AND dr.renewal_period_id = rn.renewal_period_id
             WHERE rn.id = :renewal_id
             GROUP BY rn.id, rp.required_duty_hours"
        );
        $stmt->execute(['renewal_id' => $renewalId]);
        $hours = $stmt->fetch();

        return $hours !== false
            && (float) $hours['rendered_duty_hours'] >= (float) $hours['required_duty_hours'];
    }

    public function hasApprovedRequirements(int $renewalId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT NOT EXISTS (
                 SELECT 1
                 FROM scholar_requirements sr
                 WHERE sr.scholar_id = rn.scholar_id
                   AND sr.renewal_period_id = rn.renewal_period_id
                   AND sr.status <> 'approved'
             ) AS requirements_approved
             FROM renewals rn
             WHERE rn.id = :renewal_id"
        );
        $stmt->execute(['renewal_id' => $renewalId]);
        $approved = $stmt->fetchColumn();

        return $approved !== false && (bool) $approved;
    }

    public function setStatus(int $id, string $status, int $changedBy = 0): bool
    {
        if (!in_array($status, self::allowedStatuses(), true)) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            if ($status === 'completed'
                && (!$this->hasMetRequiredDutyHours($id) || !$this->hasApprovedRequirements($id))) {
                $this->pdo->rollBack();
                return false;
            }

            $stmt = $this->pdo->prepare(
                "UPDATE renewals
                 SET status = :status,
                     updated_at = NOW()
                 WHERE id = :id"
            );
            $stmt->execute([
                'status' => $status,
                'id' => $id,
            ]);

            if ($stmt->rowCount() !== 1) {
                $currentStatus = $this->pdo->prepare(
                    'SELECT status FROM renewals WHERE id = :id'
                );
                $currentStatus->execute(['id' => $id]);
                if ($currentStatus->fetchColumn() === $status) {
                    $this->pdo->commit();
                    return true;
                }

                $this->pdo->rollBack();
                return false;
            }

            if ($changedBy > 0) {
                $history = $this->pdo->prepare(
                    "INSERT INTO renewal_history (renewal_id, status, changed_by)
                     VALUES (:renewal_id, :status, :changed_by)"
                );
                $history->execute([
                    'renewal_id' => $id,
                    'status' => $status,
                    'changed_by' => $changedBy,
                ]);
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
}
