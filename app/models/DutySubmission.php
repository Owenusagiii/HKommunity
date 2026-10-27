<?php

require_once __DIR__ . '/RenewalPeriod.php';

class DutySubmission
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(
        int $scholarId,
        int $periodId,
        string $date,
        string $type,
        float $hours,
        array $files
    ): int {
        $activePeriod = (new RenewalPeriod($this->pdo))->findActiveForScholar($scholarId);
        if (!$activePeriod || (int) $activePeriod['id'] !== $periodId) {
            throw new RuntimeException('Duty submissions can only be created for the current active period.');
        }
        if ((float) $activePeriod['required_duty_hours'] <= 0) {
            throw new RuntimeException('Duty submissions are locked because no duty hours are required for this period.');
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO duty_submissions
                    (scholar_id, renewal_period_id, duty_date, duty_type, claimed_hours)
                 VALUES (:scholar_id, :period_id, :duty_date, :duty_type, :claimed_hours)"
            );
            $stmt->execute([
                'scholar_id' => $scholarId,
                'period_id' => $periodId,
                'duty_date' => $date,
                'duty_type' => trim($type),
                'claimed_hours' => $hours,
            ]);
            $submissionId = (int) $this->pdo->lastInsertId();

            $fileStmt = $this->pdo->prepare(
                'INSERT INTO duty_submission_files
                    (submission_id, original_filename, stored_filename, mime_type, file_size)
                 VALUES (:submission_id, :original_filename, :stored_filename, :mime_type, :file_size)'
            );

            foreach ($files as $file) {
                $fileStmt->execute([
                    'submission_id' => $submissionId,
                    'original_filename' => $file['original_filename'],
                    'stored_filename' => $file['stored_filename'],
                    'mime_type' => $file['mime_type'],
                    'file_size' => $file['file_size'],
                ]);
            }

            $this->pdo->commit();
            return $submissionId;
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function forScholar(int $scholarId, ?int $periodId = null): array
    {
        $sql = "SELECT ds.*, ay.academic_year, sm.semester
             FROM duty_submissions ds
             JOIN renewal_periods rp ON rp.id = ds.renewal_period_id
             JOIN semesters sm ON sm.id = rp.semester_id
             JOIN academic_years ay ON ay.id = sm.academic_year_id
             WHERE ds.scholar_id = :scholar_id";
        $params = ['scholar_id' => $scholarId];
        if ($periodId !== null) {
            $sql .= ' AND ds.renewal_period_id = :period_id';
            $params['period_id'] = $periodId;
        }
        $sql .= ' ORDER BY ds.submitted_at DESC, ds.id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        return $this->attachFiles($items);
    }

    public function deleteForScholar(int $submissionId, int $scholarId): ?array
    {
        $this->pdo->beginTransaction();

        try {
            $submissionStmt = $this->pdo->prepare(
                "SELECT ds.id, ds.status, ds.renewal_period_id, rp.required_duty_hours
                 FROM duty_submissions ds
                 JOIN renewal_periods rp ON rp.id = ds.renewal_period_id
                 WHERE ds.id = :submission_id AND ds.scholar_id = :scholar_id
                 LIMIT 1
                 FOR UPDATE"
            );
            $submissionStmt->execute([
                'submission_id' => $submissionId,
                'scholar_id' => $scholarId,
            ]);
            $submission = $submissionStmt->fetch();

            $activePeriod = (new RenewalPeriod($this->pdo))->findActiveForScholar($scholarId);
            if (
                !$submission
                || $submission['status'] === 'approved'
                || (float) $submission['required_duty_hours'] <= 0
                || !$activePeriod
                || (int) $submission['renewal_period_id'] !== (int) $activePeriod['id']
            ) {
                $this->pdo->rollBack();
                return null;
            }

            $filesStmt = $this->pdo->prepare(
                'SELECT stored_filename
                 FROM duty_submission_files
                 WHERE submission_id = :submission_id'
            );
            $filesStmt->execute(['submission_id' => $submissionId]);
            $storedFiles = $filesStmt->fetchAll(PDO::FETCH_COLUMN);

            $deleteStmt = $this->pdo->prepare(
                "DELETE FROM duty_submissions
                 WHERE id = :submission_id
                   AND scholar_id = :scholar_id
                   AND status <> 'approved'"
            );
            $deleteStmt->execute([
                'submission_id' => $submissionId,
                'scholar_id' => $scholarId,
            ]);

            if ($deleteStmt->rowCount() !== 1) {
                $this->pdo->rollBack();
                return null;
            }

            $this->pdo->commit();
            return $storedFiles;
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function updateForScholar(
        int $submissionId,
        int $scholarId,
        ?string $date,
        ?string $type,
        ?float $hours,
        array $files,
        array $removeFileIds = []
    ): ?array {
        $this->pdo->beginTransaction();

        try {
            $submissionStmt = $this->pdo->prepare(
                "SELECT ds.id, ds.status, ds.renewal_period_id, ds.duty_date, ds.duty_type,
                        ds.claimed_hours, rp.required_duty_hours
                 FROM duty_submissions ds
                 JOIN renewal_periods rp ON rp.id = ds.renewal_period_id
                 WHERE ds.id = :submission_id AND ds.scholar_id = :scholar_id
                 LIMIT 1
                 FOR UPDATE"
            );
            $submissionStmt->execute([
                'submission_id' => $submissionId,
                'scholar_id' => $scholarId,
            ]);
            $submission = $submissionStmt->fetch();

            $activePeriod = (new RenewalPeriod($this->pdo))->findActiveForScholar($scholarId);
            if (
                !$submission
                || $submission['status'] === 'approved'
                || (float) $submission['required_duty_hours'] <= 0
                || !$activePeriod
                || (int) $submission['renewal_period_id'] !== (int) $activePeriod['id']
            ) {
                $this->pdo->rollBack();
                return null;
            }

            $date ??= $submission['duty_date'];
            $type ??= $submission['duty_type'];
            $hours ??= (float) $submission['claimed_hours'];

            $existingFilesStmt = $this->pdo->prepare(
                'SELECT id, stored_filename
                 FROM duty_submission_files
                 WHERE submission_id = :submission_id
                 FOR UPDATE'
            );
            $existingFilesStmt->execute(['submission_id' => $submissionId]);
            $existingFiles = $existingFilesStmt->fetchAll(PDO::FETCH_ASSOC);
            $filesById = [];
            foreach ($existingFiles as $existingFile) {
                $filesById[(int) $existingFile['id']] = $existingFile['stored_filename'];
            }

            $removeFileIds = array_values(array_unique(array_map('intval', $removeFileIds)));
            foreach ($removeFileIds as $removeFileId) {
                if (!isset($filesById[$removeFileId])) {
                    $this->pdo->rollBack();
                    return null;
                }
            }

            $remainingFileCount = count($existingFiles) - count($removeFileIds) + count($files);
            if ($remainingFileCount < 1 || $remainingFileCount > 5) {
                $this->pdo->rollBack();
                return null;
            }

            $deletedStoredFilenames = [];
            if ($removeFileIds) {
                $deleteFileStmt = $this->pdo->prepare(
                    'DELETE FROM duty_submission_files
                     WHERE submission_id = :submission_id AND id = :file_id'
                );
                foreach ($removeFileIds as $removeFileId) {
                    $deleteFileStmt->execute([
                        'submission_id' => $submissionId,
                        'file_id' => $removeFileId,
                    ]);
                    $deletedStoredFilenames[] = $filesById[$removeFileId];
                }
            }

            $updateStmt = $this->pdo->prepare(
                "UPDATE duty_submissions
                 SET duty_date = :duty_date,
                     duty_type = :duty_type,
                     claimed_hours = :claimed_hours,
                     status = 'pending',
                     reviewed_by = NULL,
                     reviewed_at = NULL,
                     rejection_reason = NULL,
                     submitted_at = CURRENT_TIMESTAMP
                 WHERE id = :submission_id
                   AND scholar_id = :scholar_id
                   AND status <> 'approved'"
            );
            $updateStmt->execute([
                'duty_date' => $date,
                'duty_type' => trim($type),
                'claimed_hours' => $hours,
                'submission_id' => $submissionId,
                'scholar_id' => $scholarId,
            ]);

            $fileStmt = $this->pdo->prepare(
                'INSERT INTO duty_submission_files
                    (submission_id, original_filename, stored_filename, mime_type, file_size)
                 VALUES (:submission_id, :original_filename, :stored_filename, :mime_type, :file_size)'
            );
            foreach ($files as $file) {
                $fileStmt->execute([
                    'submission_id' => $submissionId,
                    'original_filename' => $file['original_filename'],
                    'stored_filename' => $file['stored_filename'],
                    'mime_type' => $file['mime_type'],
                    'file_size' => $file['file_size'],
                ]);
            }

            $this->pdo->commit();
            return $deletedStoredFilenames;
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function forCoordinatorYear(string $yearLevel): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT ds.*, rp.required_duty_hours, sc.user_id AS scholar_user_id,
                    sc.student_id_number, sc.first_name, sc.middle_name,
                    sc.last_name, sc.suffix, ay.academic_year, sm.semester
             FROM duty_submissions ds
             JOIN scholars sc ON sc.id = ds.scholar_id
             JOIN renewal_periods rp ON rp.id = ds.renewal_period_id
             JOIN semesters sm ON sm.id = rp.semester_id
             JOIN academic_years ay ON ay.id = sm.academic_year_id
             WHERE sc.year_level = :year_level
               AND rp.year_level = sc.year_level
             ORDER BY CASE WHEN ds.status = 'pending' THEN 0 ELSE 1 END,
                      ds.submitted_at DESC, ds.id DESC"
        );
        $stmt->execute(['year_level' => $yearLevel]);
        $items = $stmt->fetchAll();

        return $this->attachFiles($items);
    }

    public function fileById(int $fileId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT dsf.*, ds.scholar_id, sc.user_id AS scholar_user_id,
                    sc.year_level
             FROM duty_submission_files dsf
             JOIN duty_submissions ds ON ds.id = dsf.submission_id
             JOIN scholars sc ON sc.id = ds.scholar_id
             WHERE dsf.id = :file_id
             LIMIT 1"
        );
        $stmt->execute(['file_id' => $fileId]);

        return $stmt->fetch() ?: null;
    }

    public function updateForCoordinator(
        int $submissionId,
        string $coordinatorYearLevel,
        string $date,
        string $type,
        float $hours
    ): bool {
        $this->pdo->beginTransaction();

        try {
            $submissionStmt = $this->pdo->prepare(
                "SELECT ds.id
                 FROM duty_submissions ds
                 JOIN scholars sc ON sc.id = ds.scholar_id
                 JOIN renewal_periods rp ON rp.id = ds.renewal_period_id
                 WHERE ds.id = :submission_id
                   AND ds.status = 'pending'
                   AND sc.year_level = :year_level
                   AND rp.year_level = sc.year_level
                   AND rp.required_duty_hours > 0
                 LIMIT 1
                 FOR UPDATE"
            );
            $submissionStmt->execute([
                'submission_id' => $submissionId,
                'year_level' => $coordinatorYearLevel,
            ]);

            if (!$submissionStmt->fetch()) {
                $this->pdo->rollBack();
                return false;
            }

            $updateStmt = $this->pdo->prepare(
                "UPDATE duty_submissions
                 SET duty_date = :duty_date,
                     duty_type = :duty_type,
                     claimed_hours = :claimed_hours
                 WHERE id = :submission_id
                   AND status = 'pending'"
            );
            $updateStmt->execute([
                'duty_date' => $date,
                'duty_type' => trim($type),
                'claimed_hours' => $hours,
                'submission_id' => $submissionId,
            ]);

            $this->pdo->commit();
            return true;
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function review(
        int $submissionId,
        int $coordinatorId,
        string $coordinatorYearLevel,
        string $decision,
        ?string $rejectionReason = null
    ): ?array {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            return null;
        }
        if (
            $decision === 'rejected'
            && (trim((string) $rejectionReason) === '' || strlen((string) $rejectionReason) > 2000)
        ) {
            return null;
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                "SELECT ds.id, ds.scholar_id, ds.renewal_period_id,
                        ds.duty_date, ds.duty_type, ds.claimed_hours,
                        sc.user_id AS scholar_user_id, sc.year_level,
                        rp.required_duty_hours
                 FROM duty_submissions ds
                 JOIN scholars sc ON sc.id = ds.scholar_id
                 JOIN renewal_periods rp ON rp.id = ds.renewal_period_id
                 WHERE ds.id = :submission_id
                   AND ds.status = 'pending'
                   AND sc.year_level = :year_level
                   AND rp.year_level = sc.year_level
                   AND rp.required_duty_hours > 0
                 FOR UPDATE"
            );
            $stmt->execute([
                'submission_id' => $submissionId,
                'year_level' => $coordinatorYearLevel,
            ]);
            $submission = $stmt->fetch();

            if (!$submission) {
                $this->pdo->rollBack();
                return null;
            }

            if ($decision === 'approved') {
                $recordStmt = $this->pdo->prepare(
                    "INSERT INTO duty_records
                        (scholar_id, renewal_period_id, duty_date, duty_type,
                         hours, coordinator_id, duty_submission_id)
                     VALUES
                        (:scholar_id, :period_id, :duty_date, :duty_type,
                         :hours, :coordinator_id, :submission_id)"
                );
                $recordStmt->execute([
                    'scholar_id' => $submission['scholar_id'],
                    'period_id' => $submission['renewal_period_id'],
                    'duty_date' => $submission['duty_date'],
                    'duty_type' => $submission['duty_type'],
                    'hours' => $submission['claimed_hours'],
                    'coordinator_id' => $coordinatorId,
                    'submission_id' => $submission['id'],
                ]);
            }

            $update = $this->pdo->prepare(
                "UPDATE duty_submissions
                 SET status = :status,
                     reviewed_by = :reviewed_by,
                     reviewed_at = NOW(),
                     rejection_reason = :rejection_reason
                 WHERE id = :submission_id AND status = 'pending'"
            );
            $update->execute([
                'status' => $decision,
                'reviewed_by' => $coordinatorId,
                'rejection_reason' => $decision === 'rejected' ? $rejectionReason : null,
                'submission_id' => $submissionId,
            ]);

            if ($update->rowCount() !== 1) {
                $this->pdo->rollBack();
                return null;
            }

            $this->pdo->commit();
            $submission['status'] = $decision;
            return $submission;
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function attachFiles(array $items): array
    {
        if (!$items) {
            return $items;
        }

        $placeholders = [];
        $params = [];
        foreach ($items as $index => $item) {
            $key = 'submission_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int) $item['id'];
            $items[$index]['files'] = [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, submission_id, original_filename, mime_type, file_size
             FROM duty_submission_files
             WHERE submission_id IN (' . implode(', ', $placeholders) . ')
             ORDER BY id'
        );
        $stmt->execute($params);
        $filesBySubmission = [];
        foreach ($stmt->fetchAll() as $file) {
            $filesBySubmission[(int) $file['submission_id']][] = $file;
        }

        foreach ($items as &$item) {
            $item['files'] = $filesBySubmission[(int) $item['id']] ?? [];
        }
        unset($item);

        return $items;
    }
}
