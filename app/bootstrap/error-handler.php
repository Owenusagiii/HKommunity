<?php

if (!function_exists('renderApplicationErrorPage')) {
    function renderApplicationErrorPage(int $statusCode): void
    {
        http_response_code($statusCode);

        if ($statusCode === 404) {
            $title = 'Page not found';
            $message = 'The page you are looking for may have moved, or the address may be incorrect.';
            $actionLabel = 'Back to home';
            $actionUrl = defined('BASE_URL') ? BASE_URL : '/HKommunity/';
        } else {
            $title = 'Something went wrong';
            $message = 'We could not complete your request. Please try again in a moment.';
            $actionLabel = 'Return to dashboard';
            $actionUrl = defined('BASE_URL') ? BASE_URL . 'public/index.php' : '/HKommunity/public/index.php';
        }

        $baseUrl = defined('BASE_URL') ? BASE_URL : '/HKommunity/';
        require __DIR__ . '/../views/errors/error-page.php';
    }
}

if (!function_exists('handleApplicationException')) {
    function handleApplicationException(Throwable $exception): void
    {
        error_log((string) $exception);

        $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $acceptHeader = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $isApiRequest = is_string($requestPath)
            && (str_contains($requestPath, '/api/') || str_contains($acceptHeader, 'application/json'));

        if ($isApiRequest) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'An unexpected error occurred.']);
            return;
        }

        renderApplicationErrorPage(500);
    }
}

set_exception_handler('handleApplicationException');
