<?php

declare(strict_types=1);

use App\Config\Database;
use PDO;
use Throwable;

require dirname(__DIR__) . '/bootstrap/app.php';

function prompt(string $label, ?string $default = null): string
{
    $suffix = $default === null ? ': ' : " [{$default}]: ";
    fwrite(STDOUT, $label . $suffix);
    $value = trim((string) fgets(STDIN));
    return $value === '' && $default !== null ? $default : $value;
}

$companyName = prompt('Shop name', 'ZAY POS');
$branchName = prompt('Branch name', 'Main Branch');
$fullName = prompt('Administrator full name', 'System Administrator');
$username = mb_strtolower(prompt('Administrator username', 'admin'));
$email = mb_strtolower(prompt('Administrator email (optional)'));
$password = prompt('Administrator password');
$confirmation = prompt('Confirm password');

if (!preg_match('/^[a-z0-9._-]{3,80}$/', $username)) {
    fwrite(STDERR, "Username must be 3-80 characters using a-z, 0-9, dot, underscore or dash." . PHP_EOL);
    exit(1);
}

if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, 'Invalid email address.' . PHP_EOL);
    exit(1);
}

if (mb_strlen($password) < 10) {
    fwrite(STDERR, 'Password must be at least 10 characters.' . PHP_EOL);
    exit(1);
}

if ($password !== $confirmation) {
    fwrite(STDERR, 'Passwords do not match.' . PHP_EOL);
    exit(1);
}

$database = Database::connection();

try {
    $database->beginTransaction();

    $database->prepare(
        'INSERT INTO companies (name, code, timezone, currency_code, status, created_at, updated_at)
         VALUES (:name, \'ZAY\', :timezone, \'MMK\', \'active\', UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    )->execute([
        'name' => $companyName,
        'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Asia/Yangon',
    ]);
    $companyId = (int) $database->lastInsertId();

    $database->prepare(
        'INSERT INTO branches (company_id, name, code, status, created_at, updated_at)
         VALUES (:company_id, :name, \'MAIN\', \'active\', UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    )->execute([
        'company_id' => $companyId,
        'name' => $branchName,
    ]);
    $branchId = (int) $database->lastInsertId();

    $permissions = [
        ['Dashboard View', 'dashboard.view', 'dashboard'],
        ['Users View', 'users.view', 'users'],
        ['Users Create', 'users.create', 'users'],
        ['Users Update', 'users.update', 'users'],
        ['Users Disable', 'users.disable', 'users'],
        ['Reset User Password', 'users.password_reset', 'users'],
        ['Roles Manage', 'roles.manage', 'roles'],
        ['Audit Logs View', 'audit_logs.view', 'audit'],
        ['Settings Manage', 'settings.manage', 'settings'],
    ];

    $permissionStatement = $database->prepare(
        'INSERT INTO permissions (name, code, module, created_at)
         VALUES (:name, :code, :module, UTC_TIMESTAMP())'
    );
    foreach ($permissions as [$name, $code, $module]) {
        $permissionStatement->execute(compact('name', 'code', 'module'));
    }

    $database->prepare(
        'INSERT INTO roles (company_id, name, code, description, is_system, created_at, updated_at)
         VALUES (:company_id, \'Super Admin\', \'super_admin\', \'Full system access\', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    )->execute(['company_id' => $companyId]);
    $roleId = (int) $database->lastInsertId();

    $database->prepare(
        'INSERT INTO role_permissions (role_id, permission_id, created_at)
         SELECT :role_id, id, UTC_TIMESTAMP() FROM permissions'
    )->execute(['role_id' => $roleId]);

    $roleStatement = $database->prepare(
        'INSERT INTO roles (company_id, name, code, description, is_system, created_at, updated_at)
         VALUES (:company_id, :name, :code, :description, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    );
    $roleStatement->execute([
        'company_id' => $companyId,
        'name' => 'Manager',
        'code' => 'manager',
        'description' => 'Shop management access',
    ]);
    $managerRoleId = (int) $database->lastInsertId();
    $roleStatement->execute([
        'company_id' => $companyId,
        'name' => 'Cashier',
        'code' => 'cashier',
        'description' => 'Cashier terminal access',
    ]);
    $cashierRoleId = (int) $database->lastInsertId();

    $database->prepare(
        "INSERT INTO role_permissions (role_id, permission_id, created_at)
         SELECT :role_id, id, UTC_TIMESTAMP() FROM permissions
         WHERE code IN ('dashboard.view', 'users.view', 'users.create', 'users.update',
                        'users.disable', 'users.password_reset', 'audit_logs.view')"
    )->execute(['role_id' => $managerRoleId]);
    $database->prepare(
        "INSERT INTO role_permissions (role_id, permission_id, created_at)
         SELECT :role_id, id, UTC_TIMESTAMP() FROM permissions WHERE code = 'dashboard.view'"
    )->execute(['role_id' => $cashierRoleId]);

    $database->prepare(
        'INSERT INTO users
            (company_id, branch_id, username, email, full_name, password_hash, status,
             password_changed_at, created_at, updated_at)
         VALUES
            (:company_id, :branch_id, :username, :email, :full_name, :password_hash, \'active\',
             UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    )->execute([
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'username' => $username,
        'email' => $email === '' ? null : $email,
        'full_name' => $fullName,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
    $userId = (int) $database->lastInsertId();

    $database->prepare(
        'INSERT INTO user_roles (user_id, role_id, created_at)
         VALUES (:user_id, :role_id, UTC_TIMESTAMP())'
    )->execute([
        'user_id' => $userId,
        'role_id' => $roleId,
    ]);

    $database->commit();
    fwrite(STDOUT, 'Super Admin created successfully.' . PHP_EOL);
} catch (Throwable $exception) {
    if ($database->inTransaction()) {
        $database->rollBack();
    }

    fwrite(STDERR, 'Failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
