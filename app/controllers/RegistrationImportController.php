<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../models/RegistrationImport.php';

class RegistrationImportController
{
    private RegistrationImport $importModel;

    public function __construct(PDO $pdo)
    {
        $this->importModel = new RegistrationImport($pdo);
    }


    public function importFile(
        string $fileName,
        string $fileType,
        string $filePath,
        int $importedBy
    ): array {

        $allowedTypes = [
            'csv',
            'xlsx'
        ];

        if (!in_array($fileType, $allowedTypes, true)) {
            return [
                'success' => false,
                'message' => 'Only CSV and XLSX files are allowed.'
            ];
        }


        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'message' => 'The uploaded file could not be found.'
            ];
        }


        try {

            $importId = $this->importModel->createImport(
                $fileName,
                $fileType,
                $importedBy
            );


            $rows = $this->readSpreadsheet(
                $filePath,
                $fileType
            );


            if (empty($rows)) {

                $this->importModel->updateImportStatus(
                    $importId,
                    'rejected'
                );

                return [
                    'success' => false,
                    'message' => 'The uploaded file contains no records.'
                ];
            }


            $headers = array_map(
                'strtolower',
                array_map(
                    'trim',
                    $rows[0]
                )
            );

            $requiredHeaders = [
                'student_id_number',
                'first_name',
                'middle_name',
                'last_name',
                'suffix',
                'year_level',
                'hk_discount_type'
            ];

            $headerIndexMap = [];
            foreach ($requiredHeaders as $requiredHeader) {
                $headerIndexMap[$requiredHeader] = null;
            }

            foreach ($headers as $index => $header) {
                if (array_key_exists($header, $headerIndexMap)) {
                    $headerIndexMap[$header] = $index;
                }
            }

            if (in_array(null, $headerIndexMap, true)) {
                $this->importModel->updateImportStatus(
                    $importId,
                    'rejected'
                );

                return [
                    'success' => false,
                    'message' => 'The spreadsheet must include these columns: student_id_number, first_name, middle_name, last_name, suffix, year_level, hk_discount_type.'
                ];
            }


            unset($rows[0]);


            $validCount = 0;
            $invalidCount = 0;


            $this->importModel->beginTransaction();


            $seenStudentIds = [];

            foreach ($rows as $row) {
                $row = array_pad($row, max(array_values($headerIndexMap)) + 1, '');

                $rowStudentId = trim((string) ($row[$headerIndexMap['student_id_number']] ?? ''));
                $duplicateStudentId = $rowStudentId !== '' && isset($seenStudentIds[$rowStudentId]);
                if ($rowStudentId !== '') {
                    $seenStudentIds[$rowStudentId] = true;
                }

                $studentIdNumber = trim(
                    (string) ($row[$headerIndexMap['student_id_number']] ?? '')
                );

                $course = '';

                $firstName = trim(
                    (string) ($row[$headerIndexMap['first_name']] ?? '')
                );

                $middleName = trim(
                    (string) ($row[$headerIndexMap['middle_name']] ?? '')
                );

                $lastName = trim(
                    (string) ($row[$headerIndexMap['last_name']] ?? '')
                );

                $suffix = trim(
                    (string) ($row[$headerIndexMap['suffix']] ?? '')
                );

                $yearLevel = trim(
                    (string) ($row[$headerIndexMap['year_level']] ?? '')
                );

                $hkDiscountType = trim(
                    (string) ($row[$headerIndexMap['hk_discount_type']] ?? '')
                );


                $validation = $this->validateRow(
                    $studentIdNumber,
                    $firstName,
                    $middleName,
                    $lastName,
                    $suffix,
                    $yearLevel,
                    $hkDiscountType
                );

                if ($duplicateStudentId) {
                    $validation = [
                        'valid' => false,
                        'message' => 'Duplicate Student ID Number in this import.'
                    ];
                }

                $status = $validation['valid'] ? 'valid' : 'invalid';
                $errorMessage = $validation['message'];

                $storedYearLevel = in_array(
                    $yearLevel,
                    [
                        '1st Year',
                        '2nd Year',
                        '3rd Year',
                        '4th Year',
                        '5th Year'
                    ],
                    true
                ) ? $yearLevel : null;

                $storedDiscount = in_array(
                    $hkDiscountType,
                    [
                        'HK5.5',
                        'HK7.5',
                        'HK10',
                        'HK12.5',
                        'HK17.5',
                        'HK25%',
                        'HK50%',
                        'HK55%',
                        'HK75%',
                        'HK80%'
                    ],
                    true
                ) ? $hkDiscountType : null;

                $this->importModel->createRegistrationRecord(
                    $importId,
                    $studentIdNumber,
                    $course,
                    $firstName,
                    $middleName !== ''
                        ? $middleName
                        : null,
                    $lastName,
                    $suffix !== ''
                        ? $suffix
                        : null,
                    $storedYearLevel,
                    $storedDiscount,
                    $status,
                    $errorMessage
                );


                if ($validation['valid']) {
                    $validCount++;
                } else {
                    $invalidCount++;
                }
            }


            $this->importModel->commit();


            return [
                'success' => true,
                'import_id' => $importId,
                'valid_count' => $validCount,
                'invalid_count' => $invalidCount,
                'message' => 'Registration file imported successfully.'
            ];

        } catch (Throwable $e) {

            if (isset($importId)) {

                try {
                    $this->importModel->rollBack();
                } catch (Throwable $rollbackError) {
                    // Ignore rollback errors.
                }

                try {
                    $this->importModel->updateImportStatus(
                        $importId,
                        'rejected'
                    );
                } catch (Throwable $statusError) {
                    // Ignore status update errors.
                }
            }


            return [
                'success' => false,
                'message' => 'Unable to process the registration file.'
            ];
        }
    }


    private function readSpreadsheet(
        string $filePath,
        string $fileType
    ): array {

        if ($fileType === 'csv') {

            $rows = [];

            $handle = fopen(
                $filePath,
                'r'
            );

            if ($handle === false) {
                throw new RuntimeException(
                    'Unable to open CSV file.'
                );
            }


            while (($row = fgetcsv($handle)) !== false) {
                $rows[] = $row;
            }


            fclose($handle);

            return $rows;
        }


        require_once __DIR__ . '/../../vendor/autoload.php';

        $spreadsheet =
            \PhpOffice\PhpSpreadsheet\IOFactory::load(
                $filePath
            );

        return $spreadsheet
            ->getActiveSheet()
            ->toArray();
    }


    private function validateRow(
        string $studentIdNumber,
        string $firstName,
        string $middleName,
        string $lastName,
        string $suffix,
        string $yearLevel,
        string $hkDiscountType
    ): array {

        if ($studentIdNumber === '') {

            return [
                'valid' => false,
                'message' => 'Student ID Number is required.'
            ];
        }

        if (
            !preg_match(
                '/^\d{2}-\d{2}-\d{4}-\d{6}$/',
                $studentIdNumber
            )
        ) {

            return [
                'valid' => false,
                'message' => 'Invalid Student ID Number format.'
            ];
        }


        if ($firstName === '') {

            return [
                'valid' => false,
                'message' => 'First Name is required.'
            ];
        }


        if (
            !preg_match(
                '/^[A-Za-zÀ-ÖØ-öø-ÿ\s]+$/',
                $firstName
            )
        ) {

            return [
                'valid' => false,
                'message' => 'First Name may only contain letters and spaces.'
            ];
        }


        if (
            $middleName !== '' &&
            !preg_match(
                '/^[A-Za-zÀ-ÖØ-öø-ÿ\s]+$/',
                $middleName
            )
        ) {

            return [
                'valid' => false,
                'message' => 'Middle Name may only contain letters and spaces.'
            ];
        }


        if ($lastName === '') {

            return [
                'valid' => false,
                'message' => 'Last Name is required.'
            ];
        }


        if (
            !preg_match(
                '/^[A-Za-zÀ-ÖØ-öø-ÿ\s]+$/',
                $lastName
            )
        ) {

            return [
                'valid' => false,
                'message' => 'Last Name may only contain letters and spaces.'
            ];
        }


        if (
            $suffix !== '' &&
            !preg_match(
                '/^[A-Za-zÀ-ÖØ-öø-ÿ\s.]+$/',
                $suffix
            )
        ) {

            return [
                'valid' => false,
                'message' => 'Suffix may only contain letters, spaces, and periods.'
            ];
        }


        $validYearLevels = [
            '1st Year',
            '2nd Year',
            '3rd Year',
            '4th Year',
            '5th Year'
        ];


        if (
            !in_array(
                $yearLevel,
                $validYearLevels,
                true
            )
        ) {

            return [
                'valid' => false,
                'message' => 'Invalid Year Level.'
            ];
        }


        $validDiscountTypes = [
            'HK5.5',
            'HK7.5',
            'HK10',
            'HK12.5',
            'HK17.5',
            'HK25%',
            'HK50%',
            'HK55%',
            'HK75%',
            'HK80%'
        ];


        if (
            !in_array(
                $hkDiscountType,
                $validDiscountTypes,
                true
            )
        ) {

            return [
                'valid' => false,
                'message' => 'Invalid HK Discount Type.'
            ];
        }


        return [
            'valid' => true,
            'message' => null
        ];
    }
}