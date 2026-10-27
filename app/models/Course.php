<?php

class Course
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getActiveCourses(): array
    {
        return $this->pdo->query(
            "SELECT course_id, course_name
             FROM courses
             WHERE status = 'active'
             ORDER BY course_name"
        )->fetchAll();
    }

    public function isActive(int $courseId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1
             FROM courses
             WHERE course_id = :course_id
               AND status = 'active'
             LIMIT 1"
        );
        $stmt->execute(['course_id' => $courseId]);

        return (bool) $stmt->fetchColumn();
    }
}
