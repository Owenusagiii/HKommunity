<?php

require_once __DIR__ . '/../models/Announcement.php';

class AnnouncementController
{
    private Announcement $model;

    public function __construct(PDO $pdo)
    {
        $this->model = new Announcement($pdo);
    }

    public function allForRole(string $role): array
    {
        return $this->model->allForRole($role);
    }

    public function all(): array
    {
        return $this->model->all();
    }

    public function create(string $title, string $content, ?string $link, string $audience, int $userId): bool
    {
        return $this->model->create($title, $content, $link, $audience, $userId);
    }

    public function update(int $id, string $title, string $content, ?string $link, string $audience): bool
    {
        return $this->model->update($id, $title, $content, $link, $audience);
    }

    public function setStatus(int $id, string $status): bool
    {
        return $this->model->setStatus($id, $status);
    }
}
