<?php

require_once __DIR__ . '/../models/RenewalPeriod.php';

class RenewalPeriodController
{
    private RenewalPeriod $model;

    public function __construct(PDO $pdo)
    {
        $this->model = new RenewalPeriod($pdo);
    }

    public function all(): array
    {
        return $this->model->all();
    }

    public function create(int $semesterId, string $yearLevel, float $hours): bool
    {
        return $this->model->create($semesterId, $yearLevel, $hours);
    }

    public function setStatus(int $id, string $status): bool
    {
        return $this->model->setStatus($id, $status);
    }
}
