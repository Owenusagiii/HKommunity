<?php

require_once __DIR__ . '/../models/AcademicYear.php';

class AcademicYearController
{
    private AcademicYear $model;

    public function __construct(PDO $pdo)
    {
        $this->model = new AcademicYear($pdo);
    }

    public function all(): array
    {
        return $this->model->all();
    }

    public function create(string $academicYear): bool
    {
        return $this->model->create($academicYear);
    }

    public function setStatus(int $id, string $status): bool
    {
        return $this->model->setStatus($id, $status);
    }
}
