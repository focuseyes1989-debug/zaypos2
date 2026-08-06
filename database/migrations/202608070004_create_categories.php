<?php

declare(strict_types=1);

return [
    'name' => '202608070004_create_categories',

    'up' => static function (\PDO $database): void {
        $database->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
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

    CONSTRAINT fk_categories_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_categories_parent
        FOREIGN KEY (parent_id)
        REFERENCES categories(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_categories_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_categories_updated_by
        FOREIGN KEY (updated_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_categories_deleted_by
        FOREIGN KEY (deleted_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    UNIQUE KEY uq_categories_company_code (company_id, code),
    KEY idx_categories_company_status (company_id, status),
    KEY idx_categories_company_name (company_id, name),
    KEY idx_categories_parent (parent_id),
    KEY idx_categories_deleted_at (deleted_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
SQL
        );

        $permissions = [
            [
                'name' => 'View Categories',
                'code' => 'categories.view',
                'description' => 'View category records',
            ],
            [
                'name' => 'Create Categories',
                'code' => 'categories.create',
                'description' => 'Create category records',
            ],
            [
                'name' => 'Update Categories',
                'code' => 'categories.update',
                'description' => 'Update category records',
            ],
            [
                'name' => 'Delete Categories',
                'code' => 'categories.delete',
                'description' => 'Soft-delete category records',
            ],
            [
                'name' => 'Restore Categories',
                'code' => 'categories.restore',
                'description' => 'Restore deleted category records',
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
                'module' => 'categories',
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
               AND p.module = 'categories'"
        );

        $database->exec(
            "INSERT IGNORE INTO role_permissions
                (role_id, permission_id, created_at)
             SELECT r.id, p.id, UTC_TIMESTAMP()
             FROM roles r
             INNER JOIN permissions p
                 ON p.code IN (
                     'categories.view',
                     'categories.create',
                     'categories.update'
                 )
             WHERE r.code = 'manager'"
        );
    },
];