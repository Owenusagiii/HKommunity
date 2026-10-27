<?php

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function post(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $default;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

function pullFlash(string $type): ?string
{
    $message = $_SESSION['flash'][$type] ?? null;
    unset($_SESSION['flash'][$type]);
    return $message;
}

function redirectTo(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function fullName(array $row): string
{
    return trim(implode(' ', array_filter([
        $row['first_name'] ?? '',
        $row['middle_name'] ?? '',
        $row['last_name'] ?? '',
        $row['suffix'] ?? '',
    ], static fn ($value) => $value !== null && $value !== '')));
}

function nameCaps(array $row): string
{
    return strtoupper(trim(
        ($row['last_name'] ?? '') . ', ' .
        ($row['first_name'] ?? '') . ' ' .
        ($row['middle_name'] ?? '')
    ));
}

function allowedYearLevels(): array
{
    return ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'];
}

function allowedDiscounts(): array
{
    return [
        'HK5.5', 'HK7.5', 'HK10', 'HK12.5', 'HK17.5',
        'HK25%', 'HK50%', 'HK55%', 'HK75%', 'HK80%'
    ];
}

function validUrl(?string $url): bool
{
    return $url !== null && $url !== '' && filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function isValidCsrfToken(mixed $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
