<?php

require_once __DIR__ . '/RenewalPeriod.php';

class ScholarPeriodContext
{
    public static function resolve(PDO $pdo, array $scholar): array
    {
        $scholarId = (int) $scholar['id'];
        $currentPeriod = (new RenewalPeriod($pdo))->findActiveForScholar($scholarId);
        $periodsStmt = $pdo->prepare(
            "SELECT rp.id, rp.year_level, rp.required_duty_hours, rp.status,
                    sm.semester, sm.status AS semester_status,
                    ay.academic_year, ay.status AS academic_year_status
             FROM renewal_periods rp
             JOIN semesters sm ON sm.id = rp.semester_id
             JOIN academic_years ay ON ay.id = sm.academic_year_id
             WHERE (
                    (
                        rp.year_level = :year_level
                        AND (rp.status = 'inactive' OR sm.status = 'inactive' OR ay.status = 'inactive')
                    )
                    OR EXISTS (
                        SELECT 1 FROM renewals rn
                        WHERE rn.scholar_id = :renewal_scholar_id
                          AND rn.renewal_period_id = rp.id
                    )
                    OR EXISTS (
                        SELECT 1 FROM scholar_requirements sr
                        WHERE sr.scholar_id = :requirement_scholar_id
                          AND sr.renewal_period_id = rp.id
                    )
                    OR EXISTS (
                        SELECT 1 FROM duty_records dr
                        WHERE dr.scholar_id = :record_scholar_id
                          AND dr.renewal_period_id = rp.id
                    )
                    OR EXISTS (
                        SELECT 1 FROM duty_submissions ds
                        WHERE ds.scholar_id = :submission_scholar_id
                          AND ds.renewal_period_id = rp.id
                    )
                    OR rp.id = :current_period_id
             )
             ORDER BY ay.academic_year DESC,
                      FIELD(sm.semester, '1st Semester', '2nd Semester') DESC,
                      rp.year_level DESC"
        );
        $periodsStmt->execute([
            'year_level' => $scholar['year_level'],
            'renewal_scholar_id' => $scholarId,
            'requirement_scholar_id' => $scholarId,
            'record_scholar_id' => $scholarId,
            'submission_scholar_id' => $scholarId,
            'current_period_id' => (int) ($currentPeriod['id'] ?? 0),
        ]);
        $periods = $periodsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (array_key_exists('view_period_id', $_GET)) {
            $requestedPeriod = $_GET['view_period_id'];
            $requestedId = $requestedPeriod === 'current'
                ? (int) ($currentPeriod['id'] ?? 0)
                : (is_string($requestedPeriod) ? filter_var($requestedPeriod, FILTER_VALIDATE_INT) : false);
            $isAvailable = is_int($requestedId) && $requestedId > 0 && array_filter(
                $periods,
                static fn (array $period): bool => (int) $period['id'] === $requestedId
            );

            if ($isAvailable) {
                $_SESSION['scholar_view_period_id'] = $requestedId;
            } else {
                unset($_SESSION['scholar_view_period_id']);
            }
        }

        $selectedId = filter_var($_SESSION['scholar_view_period_id'] ?? null, FILTER_VALIDATE_INT);
        $selectedPeriod = null;
        if (is_int($selectedId) && $selectedId > 0) {
            foreach ($periods as $period) {
                if ((int) $period['id'] === $selectedId) {
                    $selectedPeriod = $period;
                    break;
                }
            }
        }
        $selectedPeriod ??= $currentPeriod;

        if ($selectedPeriod) {
            $_SESSION['scholar_view_period_id'] = (int) $selectedPeriod['id'];
        } else {
            unset($_SESSION['scholar_view_period_id']);
        }

        $canTransact = $selectedPeriod !== null
            && $currentPeriod !== null
            && (int) $selectedPeriod['id'] === (int) $currentPeriod['id'];

        return [
            'periods' => $periods,
            'current' => $currentPeriod,
            'selected' => $selectedPeriod,
            'can_transact' => $canTransact,
        ];
    }
}
