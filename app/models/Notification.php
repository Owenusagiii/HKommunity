<?php

class Notification
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function createNotification(int $userId, string $title, string $message, string $type, ?int $registrationId = null): int
    {
        if (!in_array($type, ['requirement', 'duty', 'renewal', 'report', 'announcement', 'system'], true)) {
            throw new InvalidArgumentException('Invalid notification type.');
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO notifications
                (user_id, registration_id, title, message, type, is_read)
             VALUES (:user_id, :registration_id, :title, :message, :type, 0)"
        );
        $stmt->execute([
            'user_id' => $userId,
            'registration_id' => $registrationId,
            'title' => trim($title),
            'message' => trim($message),
            'type' => $type,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function notifyAdmins(string $title, string $message, string $type, ?int $registrationId = null): void
    {
        $stmt = $this->pdo->query(
            "SELECT id FROM users WHERE role = 'admin' AND status = 'active'"
        );

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $userId) {
            $this->createNotification((int) $userId, $title, $message, $type, $registrationId);
        }
    }

    public function getNotifications(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, registration_id, title, message, type, is_read, created_at
             FROM notifications
             WHERE user_id = :user_id
             ORDER BY is_read ASC, created_at DESC"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function getRecentForUser(int $userId, int $limit = 3): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, title, message, type, is_read, created_at
             FROM notifications
             WHERE user_id = :user_id
             ORDER BY created_at DESC, id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getUnreadCount(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0"
        );
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function markAsRead(int $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notifications SET is_read = 1
             WHERE id = :id AND user_id = :user_id"
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        return $stmt->rowCount() === 1;
    }

    public function markAllAsRead(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notifications SET is_read = 1
             WHERE user_id = :user_id AND is_read = 0"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->rowCount();
    }
}
