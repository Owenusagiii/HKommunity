<?php

require_once __DIR__ . '/../models/Notification.php';

class NotificationController
{
    private Notification $model;

    public function __construct(PDO $pdo)
    {
        $this->model = new Notification($pdo);
    }

    public function getNotifications(int $userId): array
    {
        return $this->model->getNotifications($userId);
    }

    public function getUnreadCount(int $userId): int
    {
        return $this->model->getUnreadCount($userId);
    }

    public function markAsRead(int $notificationId, int $userId): bool
    {
        return $this->model->markAsRead($notificationId, $userId);
    }

    public function markAllAsRead(int $userId): int
    {
        return $this->model->markAllAsRead($userId);
    }

    public function createNotification(int $userId, string $title, string $message, string $type, ?int $registrationId = null): int
    {
        return $this->model->createNotification($userId, $title, $message, $type, $registrationId);
    }
}
