<?php

require_once __DIR__ . '/../models/Semester.php';

class SemesterController
{
    private Semester $model;

    public function __construct(PDO $pdo)
    {
        $this->model = new Semester($pdo);
    }

    public function all(): array
    {
        return $this->model->all();
    }

    public function create(int $academicYearId, string $semester): bool
    {
        return $this->model->create($academicYearId, $semester);
    }

    public function setStatus(int $id, string $status): bool
    {
        return $this->model->setStatus($id, $status);
    }
}
