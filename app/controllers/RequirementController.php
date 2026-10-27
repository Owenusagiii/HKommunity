<?php

require_once __DIR__ . '/../models/Requirement.php';

class RequirementController
{
    private Requirement $model;

    public function __construct(PDO $pdo)
    {
        $this->model = new Requirement($pdo);
    }

    public function all(): array
    {
        return $this->model->all();
    }

    public function create(string $name, ?string $description): bool
    {
        return $this->model->create($name, $description);
    }

    public function update(int $id, string $name, ?string $description): bool
    {
        return $this->model->update($id, $name, $description);
    }

    public function setStatus(int $id, string $status): bool
    {
        return $this->model->setStatus($id, $status);
    }
}
