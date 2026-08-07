<?php

declare(strict_types=1);

return [
    'name' => '202608070009_create_warehouses',

    'up' => static function (\PDO $database): void {
        $database->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS warehouses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,

    name VARCHAR(160) NOT NULL,
    code VARCHAR(80) NOT NULL,
    location_name VARCHAR(160) NULL,

    phone VARCHAR(50) NULL,
    email VARCHAR(190) NULL,
    address VARCHAR(500) NULL,
    manager_name VARCHAR(160) NULL,

    is_default TINYINT(1) NOT NULL DEFAULT 0,
    allow_negative_stock TINYINT(1) NOT NULL DEFAULT 0,

    notes TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',

    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,

    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,

    CONSTRAINT fk_warehouses_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_warehouses_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_warehouses_updated_by
        FOREIGN KEY (updated_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_warehouses_deleted_by
        FOREIGN KEY (deleted_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    UNIQUE KEY uq_warehouses_company_code (
        company_id,
        code
    ),

    KEY idx_warehouses_company_name (
        company_id,
        name
    ),

    KEY idx_warehouses_company_status (
        company_id,
        status
    ),

    KEY idx_warehouses_company_default (
        company_id,
        is_default
    ),

    KEY idx_warehouses_deleted_at (
        deleted_at
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
SQL
        );

        $permissions = [
            [
                'name' => 'View Warehouses',
                'code' => 'warehouses.view',
                'description' => 'View warehouse records',
            ],
            [
                'name' => 'Create Warehouses',
                'code' => 'warehouses.create',
                'description' => 'Create warehouse records',
            ],
            [
                'name' => 'Update Warehouses',
                'code' => 'warehouses.update',
                'description' => 'Update warehouse records',
            ],
            [
                'name' => 'Delete Warehouses',
                'code' => 'warehouses.delete',
                'description' => 'Soft-delete warehouse records',
            ],
            [
                'name' => 'Restore Warehouses',
                'code' => 'warehouses.restore',
                'description' => 'Restore deleted warehouse records',
            ],
        ];

        $permissionStatement = $database->prepare(
            'INSERT IGNORE INTO permissions (
                name,
                code,
                module,
                description,
                created_at
             ) VALUES (
                :name,
                :code,
                :module,
                :description,
                UTC_TIMESTAMP()
             )'
        );

        foreach ($permissions as $permission) {
            $permissionStatement->execute([
                'name' => $permission['name'],
                'code' => $permission['code'],
                'module' => 'warehouses',
                'description' => $permission['description'],
            ]);
        }

        $database->exec(
            "INSERT IGNORE INTO role_permissions (
                role_id,
                permission_id,
                created_at
             )
             SELECT
                r.id,
                p.id,
                UTC_TIMESTAMP()
             FROM roles r
             CROSS JOIN permissions p
             WHERE r.code = 'super_admin'
               AND p.module = 'warehouses'"
        );

        $database->exec(
            "INSERT IGNORE INTO role_permissions (
                role_id,
                permission_id,
                created_at
             )
             SELECT
                r.id,
                p.id,
                UTC_TIMESTAMP()
             FROM roles r
             INNER JOIN permissions p
                 ON p.code IN (
                     'warehouses.view',
                     'warehouses.create',
                     'warehouses.update'
                 )
             WHERE r.code = 'manager'"
        );
    },
];