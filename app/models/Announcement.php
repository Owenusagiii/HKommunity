<?php

class Announcement
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function allForRole(string $role): array
    {
        $audience = match ($role) {
            'coordinator' => 'coordinators',
            'scholar' => 'scholars',
            default => $role,
        };

        $stmt = $this->pdo->prepare(
                        "SELECT a.*,
                                        COALESCE(
                                                NULLIF(TRIM(CONCAT_WS(' ', sc.first_name, sc.middle_name, sc.last_name, sc.suffix)), ''),
                                                NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.middle_name, c.last_name, c.suffix)), ''),
                                                u.phinmaed_email
                                        ) AS sender_name
                         FROM announcements a
                         JOIN users u ON u.id = a.created_by
                         LEFT JOIN scholars sc ON sc.user_id = u.id
                         LEFT JOIN coordinators c ON c.user_id = u.id
                         WHERE a.status = 'active'
                             AND (a.audience = 'all' OR a.audience = :audience)
                         ORDER BY a.created_at DESC"
        );
        $stmt->execute(['audience' => $audience]);
        return $stmt->fetchAll();
    }

    public function all(): array
    {
        return $this->pdo->query(
            'SELECT * FROM announcements ORDER BY created_at DESC'
        )->fetchAll();
    }

    public function create(string $title, string $content, ?string $link, string $audience, int $userId): bool
    {
        if (!in_array($audience, ['all', 'coordinators', 'scholars'], true)) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO announcements
                    (title, content, link, audience, created_by, status)
                 VALUES (:title, :content, :link, :audience, :created_by, 'active')"
            );
            return $stmt->execute([
                'title' => trim($title),
                'content' => trim($content),
                'link' => $link,
                'audience' => $audience,
                'created_by' => $userId,
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function update(int $id, string $title, string $content, ?string $link, string $audience): bool
    {
        if (!in_array($audience, ['all', 'coordinators', 'scholars'], true)) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE announcements
             SET title = :title, content = :content, link = :link,
                 audience = :audience, updated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            'title' => trim($title),
            'content' => trim($content),
            'link' => $link,
            'audience' => $audience,
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
            'UPDATE announcements SET status = :status, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['status' => $status, 'id' => $id]);
        return $stmt->rowCount() === 1;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM announcements WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() === 1;
    }

    public function audienceUserIds(string $audience, ?int $excludeUserId = null): array
    {
        $role = match ($audience) {
            'coordinators' => 'coordinator',
            'scholars' => 'scholar',
            default => null,
        };
        $excludeClause = $excludeUserId !== null ? ' AND id <> :exclude_user_id' : '';
        $params = $excludeUserId !== null ? ['exclude_user_id' => $excludeUserId] : [];

        if ($role !== null) {
            $stmt = $this->pdo->prepare(
                "SELECT id FROM users
                 WHERE role = :role AND status = 'active'{$excludeClause}"
            );
            $stmt->execute(['role' => $role] + $params);
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT id FROM users
                 WHERE status = 'active'{$excludeClause}"
            );
            $stmt->execute($params);
        }

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
