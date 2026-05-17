<?php
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function active_class(string $page, string $current): string
{
    return $page === $current ? 'active' : '';
}

function redirect_to(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function full_name(array $employee): string
{
    return trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));
}

function calculate_age(?string $birthdate): string
{
    if (!$birthdate) {
        return '';
    }

    try {
        $dob = new DateTime($birthdate);
        return (string) $dob->diff(new DateTime('today'))->y;
    } catch (Exception $e) {
        return '';
    }
}

function money_format_php($amount): string
{
    if ($amount === null || $amount === '') {
        return 'Not set';
    }

    return 'PHP ' . number_format((float) $amount, 2);
}

function role_label(?string $role): string
{
    return match ($role) {
        'admin' => 'Admin',
        'manager' => 'Manager',
        'employee' => 'Employee',
        default => (string) $role,
    };
}

function employee_join_sql(): string
{
    return "
        SELECT employees.*, departments.name AS department_name, positions.title AS position_title,
               employee_statuses.name AS status_name
        FROM employees
        LEFT JOIN departments ON employees.department_id = departments.id
        LEFT JOIN positions ON employees.position_id = positions.id
        INNER JOIN employee_statuses ON employees.status_id = employee_statuses.id
    ";
}

?>
