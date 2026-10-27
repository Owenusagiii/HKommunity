<?php

require_once __DIR__ . '/../models/ScholarRegistration.php';

class AdminRegistrationController
{
    private ScholarRegistration $scholarRegistration;

    public function __construct(PDO $pdo)
    {
        $this->scholarRegistration =
            new ScholarRegistration($pdo);
    }

    public function getRegistrations(
        ?string $studentId = null,
        ?string $status = null
    ): array {

        return $this->scholarRegistration
            ->getRegistrations(
                $studentId,
                $status
            );
    }

    public function getRegistration(
        int $id
    ): ?array {

        return $this->scholarRegistration
            ->findById($id);
    }

    public function verifyRegistration(int $registrationId): bool
    {
        return $this->scholarRegistration->verifyRegistration($registrationId);
    }

    public function approveRegistration(
        int $registrationId,
        int $adminUserId
    ): bool {

        return $this->scholarRegistration
            ->approveRegistration(
                $registrationId,
                $adminUserId
            );
    }

    public function rejectRegistration(
        int $registrationId,
        int $adminUserId,
        string $rejectionReason
    ): bool {

        return $this->scholarRegistration
            ->rejectRegistration(
                $registrationId,
                $adminUserId,
                $rejectionReason
            );
    }
}