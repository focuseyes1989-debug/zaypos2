<?php

declare(strict_types=1);

return [
    'name' => '202608070005_create_brands',

    'up' => static function (\PDO $database): void {
        $database->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS brands (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    code VARCHAR(80) NOT NULL,
    description VARCHAR(500) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,

    CONSTRAINT fk_brands_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_brands_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_brands_updated_by
        FOREIGN KEY (updated_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_brands_deleted_by
        FOREIGN KEY (deleted_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    UNIQUE KEY uq_brands_company_code (company_id, code),
    KEY idx_brands_company_status (company_id, status),
    KEY idx_brands_company_name (company_id, name),
    KEY idx_brands_deleted_at (deleted_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
SQL
        );

        $permissions = [
            [
                'name' => 'View Brands',
                'code' => 'brands.view',
                'description' => 'View brand records',
            ],
            [
                'name' => 'Create Brands',
                'code' => 'brands.create',
                'description' => 'Create brand records',
            ],
            [
                'name' => 'Update Brands',
                'code' => 'brands.update',
                'description' => 'Update brand records',
            ],
            [
                'name' => 'Delete Brands',
                'code' => 'brands.delete',
                'description' => 'Soft-delete brand records',
            ],
            [
                'name' => 'Restore Brands',
                'code' => 'brands.restore',
                'description' => 'Restore deleted brand records',
            ],
        ];

        $permissionStatement = $database->prepare(
            'INSERT IGNORE INTO permissions
                (name, code, module, description, created_at)
             VALUES
                (:name, :code, :module, :description, UTC_TIMESTAMP())'
        );

        foreach ($permissions as $permission) {
            $permissionStatement->execute([
                'name' => $permission['name'],
                'code' => $permission['code'],
                'module' => 'brands',
                'description' => $permission['description'],
            ]);
        }

        $database->exec(
            "INSERT IGNORE INTO role_permissions
                (role_id, permission_id, created_at)
             SELECT r.id, p.id, UTC_TIMESTAMP()
             FROM roles r
             CROSS JOIN permissions p
             WHERE r.code = 'super_admin'
               AND p.module = 'brands'"
        );

        $database->exec(
            "INSERT IGNORE INTO role_permissions
                (role_id, permission_id, created_at)
             SELECT r.id, p.id, UTC_TIMESTAMP()
             FROM roles r
             INNER JOIN permissions p
                 ON p.code IN (
                     'brands.view',
                     'brands.create',
                     'brands.update'
                 )
             WHERE r.code = 'manager'"
        );
    },
];