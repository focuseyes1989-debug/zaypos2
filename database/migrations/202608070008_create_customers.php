<?php

declare(strict_types=1);

return [
    'name' => '202608070008_create_customers',

    'up' => static function (\PDO $database): void {
        $database->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,

    name VARCHAR(160) NOT NULL,
    code VARCHAR(80) NOT NULL,

    phone VARCHAR(50) NULL,
    email VARCHAR(190) NULL,
    address VARCHAR(500) NULL,
    tax_number VARCHAR(100) NULL,
    customer_group VARCHAR(100) NULL,

    credit_limit DECIMAL(18, 2) NOT NULL DEFAULT 0.00,
    opening_balance DECIMAL(18, 2) NOT NULL DEFAULT 0.00,

    notes TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',

    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,

    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,

    CONSTRAINT fk_customers_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_customers_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_customers_updated_by
        FOREIGN KEY (updated_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_customers_deleted_by
        FOREIGN KEY (deleted_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    UNIQUE KEY uq_customers_company_code (
        company_id,
        code
    ),

    KEY idx_customers_company_name (
        company_id,
        name
    ),

    KEY idx_customers_company_status (
        company_id,
        status
    ),

    KEY idx_customers_phone (
        phone
    ),

    KEY idx_customers_email (
        email
    ),

    KEY idx_customers_group (
        company_id,
        customer_group
    ),

    KEY idx_customers_deleted_at (
        deleted_at
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
SQL
        );

        $permissions = [
            [
                'name' => 'View Customers',
                'code' => 'customers.view',
                'description' => 'View customer records',
            ],
            [
                'name' => 'Create Customers',
                'code' => 'customers.create',
                'description' => 'Create customer records',
            ],
            [
                'name' => 'Update Customers',
                'code' => 'customers.update',
                'description' => 'Update customer records',
            ],
            [
                'name' => 'Delete Customers',
                'code' => 'customers.delete',
                'description' => 'Soft-delete customer records',
            ],
            [
                'name' => 'Restore Customers',
                'code' => 'customers.restore',
                'description' => 'Restore deleted customer records',
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
                'module' => 'customers',
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
               AND p.module = 'customers'"
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
                     'customers.view',
                     'customers.create',
                     'customers.update'
                 )
             WHERE r.code = 'manager'"
        );
    },
];