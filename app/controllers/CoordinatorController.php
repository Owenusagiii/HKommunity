<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../models/Coordinator.php';

class CoordinatorController
{
    private Coordinator $coordinatorModel;

    public function __construct(PDO $pdo)
    {
        $this->coordinatorModel = new Coordinator($pdo);
    }

    public function getCoordinators(): array
    {
        return $this->coordinatorModel->getAllCoordinators();
    }

    public function searchCoordinators(string $name): array
    {
        return $this->coordinatorModel->searchCoordinators($name);
    }

    public function filterCoordinatorsByYearLevel(
        string $yearLevel
    ): array {
        return $this->coordinatorModel->filterByYearLevel($yearLevel);
    }

    public function searchAndFilterCoordinators(
        string $name,
        string $yearLevel
    ): array {
        return $this->coordinatorModel->searchAndFilter(
            $name,
            $yearLevel
        );
    }

    public function updateAssignedYearLevel(
        int $coordinatorId,
        string $yearLevel
    ): array {

        $validYearLevels = [
            '1st Year',
            '2nd Year',
            '3rd Year',
            '4th Year',
            '5th Year'
        ];

        if (!in_array($yearLevel, $validYearLevels, true)) {

            return [
                'success' => false,
                'message' => 'Please select a valid Assigned Year Level.'
            ];
        }

        $updated = $this->coordinatorModel->updateAssignedYearLevel(
            $coordinatorId,
            $yearLevel
        );

        if (!$updated) {

            return [
                'success' => false,
                'message' => 'Unable to update the Assigned Year Level.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Assigned Year Level updated successfully.'
        ];
    }

    public function updateStatus(
        int $userId,
        string $status
    ): array {

        $validStatuses = [
            'active',
            'inactive'
        ];

        if (!in_array($status, $validStatuses, true)) {

            return [
                'success' => false,
                'message' => 'Please select a valid account status.'
            ];
        }

        $updated = $this->coordinatorModel->updateStatus(
            $userId,
            $status
        );

        if (!$updated) {

            return [
                'success' => false,
                'message' => 'Unable to update the coordinator account status.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Coordinator account status updated successfully.'
        ];
    }

}