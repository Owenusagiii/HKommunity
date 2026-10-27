<?php

class QuickLink
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(): array
    {
        return $this->pdo->query(
            "SELECT * FROM quick_links WHERE status = 'active' ORDER BY title"
        )->fetchAll();
    }

    public function latestActive(): array
    {
        return $this->pdo->query(
            "SELECT * FROM quick_links WHERE status = 'active' ORDER BY created_at DESC LIMIT 2"
        )->fetchAll();
    }

    public function allAdmin(): array
    {
        return $this->pdo->query(
            "SELECT * FROM quick_links ORDER BY created_at DESC"
        )->fetchAll();
    }

    public function create(string $title, ?string $description, string $url, int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO quick_links (title, description, url, status, created_by)
                 VALUES (:title, :description, :url, 'active', :created_by)"
            );
            return $stmt->execute([
                'title' => trim($title),
                'description' => $description,
                'url' => trim($url),
                'created_by' => $userId,
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function update(int $id, string $title, ?string $description, string $url): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE quick_links
             SET title = :title, description = :description, url = :url
             WHERE id = :id"
        );
        $stmt->execute([
            'title' => trim($title),
            'description' => $description,
            'url' => trim($url),
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
            "UPDATE quick_links SET status = :status WHERE id = :id"
        );
        $stmt->execute(['status' => $status, 'id' => $id]);
        return $stmt->rowCount() === 1;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM quick_links WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() === 1;
    }
}
