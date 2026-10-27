<?php

require_once __DIR__ . '/../models/ScholarRequirement.php';

class ScholarRequirementController
{
    private ScholarRequirement $model;

    public function __construct(PDO $pdo)
    {
        $this->model = new ScholarRequirement($pdo);
    }

    public function allForScholar(int $scholarId): array
    {
        return $this->model->allForScholar($scholarId);
    }

    public function allForCoordinator(string $yearLevel): array
    {
        return $this->model->allForCoordinator($yearLevel);
    }

    public function assign(int $scholarId, int $requirementId, int $periodId, int $changedBy = 0): bool
    {
        return $this->model->assign($scholarId, $requirementId, $periodId, $changedBy);
    }

    public function assignMany(array $scholarIds, array $requirementIds, int $periodId, int $changedBy = 0): int
    {
        return $this->model->assignMany($scholarIds, $requirementIds, $periodId, $changedBy);
    }

    public function submit(int $id, int $scholarId, string $link, int $changedBy): bool
    {
        return $this->model->submit($id, $scholarId, $link, $changedBy);
    }

    public function review(int $id, string $status, ?string $reason, int $changedBy): bool
    {
        return $this->model->review($id, $status, $reason, $changedBy);
    }
}
