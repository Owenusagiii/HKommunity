<?php

class Report
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(int $from, int $to, string $subject, string $message): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO reports (submitted_by, submitted_to, subject, message, status)
             VALUES (:from, :to, :subject, :message, 'for_review')"
        );
        return $stmt->execute([
            'from' => $from,
            'to' => $to,
            'subject' => trim($subject),
            'message' => trim($message),
        ]);
    }

    public function forUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.*, uf.phinmaed_email AS from_email, ut.phinmaed_email AS to_email
             FROM reports r
             JOIN users uf ON uf.id = r.submitted_by
             JOIN users ut ON ut.id = r.submitted_to
             WHERE r.submitted_by = :submitted_by
                OR r.submitted_to = :submitted_to
             ORDER BY r.created_at DESC"
        );
        $stmt->execute([
            'submitted_by' => $userId,
            'submitted_to' => $userId,
        ]);
        return $stmt->fetchAll();
    }

    public function all(): array
    {
        return $this->pdo->query(
            "SELECT r.*, uf.phinmaed_email AS from_email, ut.phinmaed_email AS to_email
             FROM reports r
             JOIN users uf ON uf.id = r.submitted_by
             JOIN users ut ON ut.id = r.submitted_to
             ORDER BY r.created_at DESC"
        )->fetchAll();
    }

    public function updateStatus(int $id, string $status, ?string $response): bool
    {
        if (!in_array($status, ['for_review', 'resolved', 'rejected'], true)) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            "UPDATE reports
             SET status = :status, response = :response, updated_at = NOW()
             WHERE id = :id"
        );
        return $stmt->execute([
            'status' => $status,
            'response' => $response,
            'id' => $id,
        ]);
    }

    public function deleteForReviewByUser(int $reportId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM reports
             WHERE id = :id
               AND submitted_by = :submitted_by
               AND status = 'for_review'"
        );
        $stmt->execute([
            'id' => $reportId,
            'submitted_by' => $userId,
        ]);

        return $stmt->rowCount() === 1;
    }

    public function transfer(int $id, int $newRecipient): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE reports
             SET submitted_to = :recipient, status = 'for_review', updated_at = NOW()
             WHERE id = :id"
        );
        return $stmt->execute(['recipient' => $newRecipient, 'id' => $id]);
    }
}
