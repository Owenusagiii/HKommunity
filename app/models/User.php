<?php

class User
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByEmail(string $email): ?array
    {
        $sql = "SELECT *
                FROM users
            WHERE LOWER(phinmaed_email) = LOWER(:email)
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'email' => $email
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function findByLoginIdentifier(string $identifier): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.*
             FROM users u
             LEFT JOIN scholars s ON s.user_id = u.id
             WHERE LOWER(u.phinmaed_email) = LOWER(:email)
                OR s.student_id_number = :student_id_number
             LIMIT 1"
        );
        $stmt->execute([
            'email' => $identifier,
            'student_id_number' => $identifier,
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function getFirstNameById(int $userId): ?string
    {
        $sql = "SELECT
                    u.role,
                    c.first_name AS coordinator_first_name,
                    s.first_name AS scholar_first_name
                FROM users u
                LEFT JOIN coordinators c ON c.user_id = u.id
                LEFT JOIN scholars s ON s.user_id = u.id
                WHERE u.id = :user_id
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        return $user['coordinator_first_name']
            ?? $user['scholar_first_name']
            ?? ($user['role'] === 'admin' ? 'Admin' : null);
    }

    public function getAllUsers(): array
    {
        $sql = "SELECT
                    id,
                    phinmaed_email,
                    role,
                    status,
                    created_at
                FROM users
                ORDER BY created_at DESC";

        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll();
    }

    public function searchByEmail(string $email): array
    {
        $sql = "SELECT
                    id,
                    phinmaed_email,
                    role,
                    status,
                    created_at
                FROM users
                WHERE phinmaed_email LIKE :email
                ORDER BY created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'email' => '%' . $email . '%'
        ]);

        return $stmt->fetchAll();
    }

    public function createUser(
        string $email,
        string $password,
        string $role
    ): int {
        $sql = "INSERT INTO users (
                    phinmaed_email,
                    password,
                    role,
                    status
                ) VALUES (
                    :email,
                    :password,
                    :role,
                    'active'
                )";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $userId, string $status): bool
    {
        $sql = "UPDATE users
                SET status = :status
                WHERE id = :id
                AND role <> 'admin'";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            'status' => $status,
            'id' => $userId
        ]);
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }


    public function commit(): bool
    {
        return $this->pdo->commit();
    }


    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public function createCoordinator(
        int $userId,
        string $firstName,
        ?string $middleName,
        string $lastName,
        ?string $suffix,
        string $yearLevel
    ): bool {
        $sql = "INSERT INTO coordinators (
                    user_id,
                    first_name,
                    middle_name,
                    last_name,
                    suffix,
                    year_level
                ) VALUES (
                    :user_id,
                    :first_name,
                    :middle_name,
                    :last_name,
                    :suffix,
                    :year_level
                )";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            'user_id' => $userId,
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'suffix' => $suffix,
            'year_level' => $yearLevel
        ]);
    }


    public function createScholar(
        int $userId,
        string $studentIdNumber,
        string $firstName,
        ?string $middleName,
        string $lastName,
        ?string $suffix,
        string $yearLevel,
        string $hkDiscountType
    ): bool {
        $sql = "INSERT INTO scholars (
                    user_id,
                    student_id_number,
                    first_name,
                    middle_name,
                    last_name,
                    suffix,
                    year_level,
                    hk_discount_type
                ) VALUES (
                    :user_id,
                    :student_id_number,
                    :first_name,
                    :middle_name,
                    :last_name,
                    :suffix,
                    :year_level,
                    :hk_discount_type
                )";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            'user_id' => $userId,
            'student_id_number' => $studentIdNumber,
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'suffix' => $suffix,
            'year_level' => $yearLevel,
            'hk_discount_type' => $hkDiscountType
        ]);
    }
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function updateOwn(int $id, string $first, string $last): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET first_name = :first_name, last_name = :last_name WHERE id = :id'
        );
        $stmt->execute([
            'first_name' => $first,
            'last_name' => $last,
            'id' => $id,
        ]);
        return $stmt->rowCount() === 1;
    }
}