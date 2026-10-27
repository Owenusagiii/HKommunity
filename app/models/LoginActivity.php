<?php

class LoginActivity
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function record(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_logs (user_id) VALUES (:user_id)'
        );
        $stmt->execute(['user_id' => $userId]);
    }

    public function recent(): array
    {
        return $this->pdo->query(
            "SELECT ula.logged_in_at,
                    u.role,
                    u.phinmaed_email AS user_email
             FROM user_logs ula
             JOIN users u ON u.id = ula.user_id
             ORDER BY ula.logged_in_at DESC, ula.id DESC"
        )->fetchAll();
    }
}
