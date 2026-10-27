<?php

class Requirement
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(): array
    {
        return $this->pdo->query(
            "SELECT * FROM requirements ORDER BY created_at DESC"
        )->fetchAll();
    }

    public function create(string $name, ?string $description): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO requirements (name, description, status)
                 VALUES (:name, :description, 'active')"
            );
            return $stmt->execute([
                'name' => trim($name),
                'description' => $description,
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function update(int $id, string $name, ?string $description): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE requirements
             SET name = :name, description = :description, updated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            'name' => trim($name),
            'description' => $description,
            'id' => $id,
        ]);
        return $stmt->rowCount() === 1;
    }

    public function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['active', 'inactive'], true)) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            "UPDATE requirements SET status = :status, updated_at = NOW() WHERE id = :id"
        );
        $stmt->execute(['status' => $status, 'id' => $id]);
        return $stmt->rowCount() === 1;
    }
}
