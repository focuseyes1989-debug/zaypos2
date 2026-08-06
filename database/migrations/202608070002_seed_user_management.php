<?php

declare(strict_types=1);

return [
    'name' => '202608070002_seed_user_management',
    'up' => static function (\PDO $database): void {
        $database->exec(
            "INSERT IGNORE INTO permissions (name, code, module, description, created_at)
             VALUES ('Reset User Password', 'users.password_reset', 'users',
                     'Reset another user password', UTC_TIMESTAMP())"
        );

        $database->exec(
            "INSERT IGNORE INTO roles
                (company_id, name, code, description, is_system, created_at, updated_at)
             SELECT id, 'Manager', 'manager', 'Shop management access', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
             FROM companies"
        );
        $database->exec(
            "INSERT IGNORE INTO roles
                (company_id, name, code, description, is_system, created_at, updated_at)
             SELECT id, 'Cashier', 'cashier', 'Cashier terminal access', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
             FROM companies"
        );

        $database->exec(
            "INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
             SELECT r.id, p.id, UTC_TIMESTAMP()
             FROM roles r
             INNER JOIN permissions p ON p.code IN (
                 'dashboard.view', 'users.view', 'users.create', 'users.update',
                 'users.disable', 'users.password_reset', 'audit_logs.view'
             )
             WHERE r.code = 'manager'"
        );
        $database->exec(
            "INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
             SELECT r.id, p.id, UTC_TIMESTAMP()
             FROM roles r
             INNER JOIN permissions p ON p.code = 'dashboard.view'
             WHERE r.code = 'cashier'"
        );
        $database->exec(
            "INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
             SELECT r.id, p.id, UTC_TIMESTAMP()
             FROM roles r
             CROSS JOIN permissions p
             WHERE r.code = 'super_admin'"
        );
    },
];
