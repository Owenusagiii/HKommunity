<?php

require_once __DIR__ . '/../models/Report.php';

class ReportController
{
    private Report $model;

    public function __construct(PDO $pdo)
    {
        $this->model = new Report($pdo);
    }

    public function create(int $from, int $to, string $subject, string $message): bool
    {
        return $this->model->create($from, $to, $subject, $message);
    }

    public function forUser(int $userId): array
    {
        return $this->model->forUser($userId);
    }

    public function all(): array
    {
        return $this->model->all();
    }

    public function updateStatus(int $id, string $status, ?string $response): bool
    {
        return $this->model->updateStatus($id, $status, $response);
    }
}
