<?php

class Coordinator
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAllCoordinators(): array
    {
        $sql = "SELECT
                    c.id,
                    c.user_id,
                    c.first_name,
                    c.middle_name,
                    c.last_name,
                    c.suffix,
                    c.year_level,
                    u.phinmaed_email,
                    u.status,
                    u.created_at
                FROM coordinators c
                INNER JOIN users u
                    ON c.user_id = u.id
                ORDER BY c.last_name ASC, c.first_name ASC";

        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll();
    }

    public function searchCoordinators(string $name): array
    {
        $sql = "SELECT
                    c.id,
                    c.user_id,
                    c.first_name,
                    c.middle_name,
                    c.last_name,
                    c.suffix,
                    c.year_level,
                    u.phinmaed_email,
                    u.status,
                    u.created_at
                FROM coordinators c
                INNER JOIN users u
                    ON c.user_id = u.id
                WHERE CONCAT(
                    c.first_name, ' ',
                    COALESCE(c.middle_name, ''), ' ',
                    c.last_name, ' ',
                    COALESCE(c.suffix, '')
                ) LIKE :name
                ORDER BY c.last_name ASC, c.first_name ASC";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'name' => '%' . trim($name) . '%'
        ]);

        return $stmt->fetchAll();
    }

    public function filterByYearLevel(string $yearLevel): array
    {
        $sql = "SELECT
                    c.id,
                    c.user_id,
                    c.first_name,
                    c.middle_name,
                    c.last_name,
                    c.suffix,
                    c.year_level,
                    u.phinmaed_email,
                    u.status,
                    u.created_at
                FROM coordinators c
                INNER JOIN users u
                    ON c.user_id = u.id
                WHERE c.year_level = :year_level
                ORDER BY c.last_name ASC, c.first_name ASC";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'year_level' => $yearLevel
        ]);

        return $stmt->fetchAll();
    }

    public function searchAndFilter(
        string $name,
        string $yearLevel
    ): array {
        $sql = "SELECT
                    c.id,
                    c.user_id,
                    c.first_name,
                    c.middle_name,
                    c.last_name,
                    c.suffix,
                    c.year_level,
                    u.phinmaed_email,
                    u.status,
                    u.created_at
                FROM coordinators c
                INNER JOIN users u
                    ON c.user_id = u.id
                WHERE CONCAT(
                    c.first_name, ' ',
                    COALESCE(c.middle_name, ''), ' ',
                    c.last_name, ' ',
                    COALESCE(c.suffix, '')
                ) LIKE :name
                AND c.year_level = :year_level
                ORDER BY c.last_name ASC, c.first_name ASC";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'name' => '%' . trim($name) . '%',
            'year_level' => $yearLevel
        ]);

        return $stmt->fetchAll();
    }

    public function updateAssignedYearLevel(
        int $coordinatorId,
        string $yearLevel
    ): bool {
        $sql = "UPDATE coordinators
                SET year_level = :year_level
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            'year_level' => $yearLevel,
            'id' => $coordinatorId
        ]);
    }

    public function updateStatus(
        int $userId,
        string $status
    ): bool {
        $sql = "UPDATE users
                SET status = :status
                WHERE id = :id
                AND role = 'coordinator'";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            'status' => $status,
            'id' => $userId
        ]);
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.*, u.phinmaed_email, u.status, u.created_at
             FROM coordinators c
             JOIN users u ON u.id = c.user_id
             WHERE c.user_id = :user_id
             LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public function updateOwn(int $userId, string $first, ?string $middle, string $last, ?string $suffix): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE coordinators
             SET first_name = :first_name, middle_name = :middle_name,
                 last_name = :last_name, suffix = :suffix
             WHERE user_id = :user_id"
        );
        $stmt->execute([
            'first_name' => $first,
            'middle_name' => $middle,
            'last_name' => $last,
            'suffix' => $suffix,
            'user_id' => $userId,
        ]);
        return $stmt->rowCount() === 1;
    }
}