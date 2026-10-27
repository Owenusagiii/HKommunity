<?php

require_once __DIR__ . '/../models/Renewal.php';

class RenewalController
{
    private Renewal $model;

    public function __construct(PDO $pdo)
    {
        $this->model = new Renewal($pdo);
    }

    public function getForScholar(int $scholarId): array
    {
        return $this->model->getForScholar($scholarId);
    }

    public function getForCoordinatorYear(string $yearLevel): array
    {
        return $this->model->getForCoordinatorYear($yearLevel);
    }

    public function getAll(): array
    {
        return $this->model->getAll();
    }

    public function setStatus(int $id, string $status, int $changedBy = 0): bool
    {
        return $this->model->setStatus($id, $status, $changedBy);
    }
}
