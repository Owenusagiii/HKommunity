<?php

require_once __DIR__ . '/../models/Scholar.php';

class ScholarController
{
    private Scholar $model;

    public function __construct(PDO $pdo)
    {
        $this->model = new Scholar($pdo);
    }

    public function findByUserId(int $id): ?array
    {
        return $this->model->findByUserId($id);
    }

    public function findById(int $id): ?array
    {
        return $this->model->findById($id);
    }

    public function all(): array
    {
        return $this->model->all();
    }

    public function search(?string $studentId, ?string $year, ?string $name, ?string $coordinatorYear = null): array
    {
        return $this->model->search($studentId, $year, $name, $coordinatorYear);
    }

    public function updateOwn(int $userId, string $first, ?string $middle, string $last, ?string $suffix): bool
    {
        return $this->model->updateOwn($userId, $first, $middle, $last, $suffix);
    }

    public function updateOfficial(
        int $scholarId,
        string $studentIdNumber,
        string $first,
        ?string $middle,
        string $last,
        ?string $suffix,
        string $yearLevel,
        int $courseId,
        string $hkDiscountType
    ): bool {
        return $this->model->updateOfficial(
            $scholarId,
            $studentIdNumber,
            $first,
            $middle,
            $last,
            $suffix,
            $yearLevel,
            $courseId,
            $hkDiscountType
        );
    }
}
