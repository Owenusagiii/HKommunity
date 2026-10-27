<?php

require_once __DIR__ . '/../models/DutyRecord.php';

class DutyController
{
    private DutyRecord $model;

    public function __construct(PDO $pdo)
    {
        $this->model = new DutyRecord($pdo);
    }

    public function all(?int $scholarId = null, ?string $studentId = null, ?int $coordinatorId = null): array
    {
        return $this->model->all($scholarId, $studentId, $coordinatorId);
    }

    public function update(int $id, string $date, string $type, float $hours, int $coordinatorId): bool
    {
        return $this->model->updateForCoordinator($id, $date, $type, $hours, $coordinatorId);
    }

    public function totalHours(int $scholarId, int $periodId): float
    {
        return $this->model->totalHours($scholarId, $periodId);
    }
}
