<?php

require_once __DIR__ . '/../models/QuickLink.php';

class QuickLinkController
{
    private QuickLink $model;

    public function __construct(PDO $pdo)
    {
        $this->model = new QuickLink($pdo);
    }

    public function all(): array
    {
        return $this->model->all();
    }

    public function allAdmin(): array
    {
        return $this->model->allAdmin();
    }

    public function create(string $title, ?string $description, string $url, int $userId): bool
    {
        return $this->model->create($title, $description, $url, $userId);
    }

    public function update(int $id, string $title, ?string $description, string $url): bool
    {
        return $this->model->update($id, $title, $description, $url);
    }

    public function setStatus(int $id, string $status): bool
    {
        return $this->model->setStatus($id, $status);
    }
}
