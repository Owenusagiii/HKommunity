<?php

class Scholar
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                sc.*,
                c.course_name,
                u.phinmaed_email,
                u.status,
                u.created_at
             FROM scholars sc
             JOIN courses c ON c.course_id = sc.course_id
             JOIN users u ON u.id = sc.user_id
             WHERE sc.user_id = :user_id
             LIMIT 1"
        );

        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT sc.*, c.course_name, u.phinmaed_email, u.status
             FROM scholars sc
             JOIN courses c ON c.course_id = sc.course_id
             JOIN users u ON u.id = sc.user_id
             WHERE sc.id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function all(): array
    {
        return $this->pdo->query(
            "SELECT sc.*, c.course_name, u.phinmaed_email, u.status
             FROM scholars sc
             JOIN courses c ON c.course_id = sc.course_id
             JOIN users u ON u.id = sc.user_id
             ORDER BY sc.last_name, sc.first_name"
        )->fetchAll();
    }

    public function search(
        ?string $studentId,
        ?string $yearLevel,
        ?string $name,
        ?string $coordinatorYear = null
    ): array {
        $sql = "SELECT
                    sc.*,
                    c.course_name,
                    u.phinmaed_email,
                    u.status,
                    u.created_at
                FROM scholars sc
                JOIN courses c ON c.course_id = sc.course_id
                JOIN users u ON u.id = sc.user_id
                WHERE 1 = 1";

        $params = [];

        if ($studentId !== null && $studentId !== '') {
            $sql .= " AND sc.student_id_number LIKE :student_id";
            $params['student_id'] = '%' . $studentId . '%';
        }

        if ($yearLevel !== null && $yearLevel !== '') {
            $sql .= " AND sc.year_level = :year_level";
            $params['year_level'] = $yearLevel;
        }

        if ($name !== null && $name !== '') {
            $sql .= " AND CONCAT(
                        sc.first_name, ' ',
                        COALESCE(sc.middle_name, ''), ' ',
                        sc.last_name, ' ',
                        COALESCE(sc.suffix, '')
                    ) LIKE :name";
            $params['name'] = '%' . $name . '%';
        }

        if ($coordinatorYear !== null && $coordinatorYear !== '') {
            $sql .= " AND sc.year_level = :coordinator_year";
            $params['coordinator_year'] = $coordinatorYear;
        }

        $sql .= " ORDER BY sc.last_name, sc.first_name";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function updateOwn(
        int $userId,
        string $first,
        ?string $middle,
        string $last,
        ?string $suffix
    ): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE scholars
             SET first_name = :first_name,
                 middle_name = :middle_name,
                 last_name = :last_name,
                 suffix = :suffix
             WHERE user_id = :user_id"
        );

        return $stmt->execute([
            'first_name' => $first,
            'middle_name' => $middle,
            'last_name' => $last,
            'suffix' => $suffix,
            'user_id' => $userId,
        ]);
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
        $courseCheck = $this->pdo->prepare(
            "SELECT 1
             FROM courses
             WHERE course_id = :course_id
               AND status = 'active'
             LIMIT 1"
        );
        $courseCheck->execute(['course_id' => $courseId]);
        if (!$courseCheck->fetchColumn()) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE scholars
             SET student_id_number = :student_id_number,
                 first_name = :first_name,
                 middle_name = :middle_name,
                 last_name = :last_name,
                 suffix = :suffix,
                 year_level = :year_level,
                 course_id = :course_id,
                 hk_discount_type = :hk_discount_type
             WHERE id = :id"
        );

        return $stmt->execute([
            'student_id_number' => $studentIdNumber,
            'first_name' => $first,
            'middle_name' => $middle,
            'last_name' => $last,
            'suffix' => $suffix,
            'year_level' => $yearLevel,
            'course_id' => $courseId,
            'hk_discount_type' => $hkDiscountType,
            'id' => $scholarId,
        ]);
    }
}
