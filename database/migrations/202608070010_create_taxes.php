<?php

declare(strict_types=1);

return [
    'name' => '202608070010_create_taxes',

    'up' => static function (\PDO $database): void {
        $database->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS taxes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,

    name VARCHAR(160) NOT NULL,
    code VARCHAR(80) NOT NULL,

    tax_type VARCHAR(20) NOT NULL DEFAULT 'percentage',
    rate DECIMAL(18, 4) NOT NULL DEFAULT 0.0000,

    price_includes_tax TINYINT(1) NOT NULL DEFAULT 0,
    applies_to_sales TINYINT(1) NOT NULL DEFAULT 1,
    applies_to_purchases TINYINT(1) NOT NULL DEFAULT 0,
    is_default TINYINT(1) NOT NULL DEFAULT 0,

    description VARCHAR(500) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',

    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,

    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,

    CONSTRAINT fk_taxes_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_taxes_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_taxes_updated_by
        FOREIGN KEY (updated_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_taxes_deleted_by
        FOREIGN KEY (deleted_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    UNIQUE KEY uq_taxes_company_code (
        company_id,
        code
    ),

    KEY idx_taxes_company_name (
        company_id,
        name
    ),

    KEY idx_taxes_company_status (
        company_id,
        status
    ),

    KEY idx_taxes_company_type (
        company_id,
        tax_type
    ),

    KEY idx_taxes_company_default (
        company_id,
        is_default
    ),

    KEY idx_taxes_deleted_at (
        deleted_at
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
SQL
        );

        $permissions = [
            [
                'name' => 'View Taxes',
                'code' => 'taxes.view',
                'description' => 'View tax records',
            ],
            [
                'name' => 'Create Taxes',
                'code' => 'taxes.create',
                'description' => 'Create tax records',
            ],
            [
                'name' => 'Update Taxes',
                'code' => 'taxes.update',
                'description' => 'Update tax records',
            ],
            [
                'name' => 'Delete Taxes',
                'code' => 'taxes.delete',
                'description' => 'Soft-delete tax records',
            ],
            [
                'name' => 'Restore Taxes',
                'code' => 'taxes.restore',
                'description' => 'Restore deleted tax records',
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
                'module' => 'taxes',
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
               AND p.module = 'taxes'"
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
                     'taxes.view',
                     'taxes.create',
                     'taxes.update'
                 )
             WHERE r.code = 'manager'"
        );
    },
];