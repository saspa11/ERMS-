<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function require_login_from_subdir(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }
}

function current_user_name(): string
{
    return $_SESSION['full_name'] ?? 'User';
}

function current_user_role(): string
{
    return $_SESSION['role'] ?? '';
}

function current_employee_id(): ?int
{
    return isset($_SESSION['employee_id']) && $_SESSION['employee_id'] !== '' ? (int) $_SESSION['employee_id'] : null;
}

function is_admin(): bool
{
    return current_user_role() === 'admin';
}

function is_manager(): bool
{
    return current_user_role() === 'manager';
}

function is_employee(): bool
{
    return current_user_role() === 'employee';
}

function can_manage_employees(): bool
{
    return is_admin() || is_manager();
}

function can_manage_departments(): bool
{
    return is_admin() || is_manager();
}

function can_delete_operations(): bool
{
    return is_admin() || is_manager();
}

function can_manage_managers(): bool
{
    return is_admin();
}

function can_delete_employee(): bool
{
    return is_admin();
}

function can_approve_role(string $role): bool
{
    if (is_admin()) {
        return in_array($role, ['manager', 'employee'], true);
    }

    return is_manager() && $role === 'employee';
}

function require_role(array $roles, bool $fromSubdir = false): void
{
    $loggedIn = !empty($_SESSION['user_id']);
    if (!$loggedIn) {
        header('Location: ' . ($fromSubdir ? '../login.php' : 'login.php'));
        exit;
    }

    if (!in_array(current_user_role(), $roles, true)) {
        header('Location: ' . ($fromSubdir ? '../dashboard.php' : 'dashboard.php'));
        exit;
    }
}
?>
