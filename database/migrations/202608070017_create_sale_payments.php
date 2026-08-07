<?php

declare(strict_types=1);

return [
    'name' => '202608070017_create_sale_payments',

    'up' => static function (\PDO $database): void {
        $database->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS sale_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    company_id BIGINT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NULL,

    payment_number VARCHAR(100) NOT NULL,
    payment_date DATE NOT NULL,

    amount DECIMAL(18,4) NOT NULL DEFAULT 0,

    payment_method VARCHAR(30) NOT NULL DEFAULT 'cash',
    reference_number VARCHAR(190) NULL,

    notes TEXT NULL,

    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,

    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,

    CONSTRAINT fk_sale_payments_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_sale_payments_sale
        FOREIGN KEY (sale_id)
        REFERENCES sales(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_sale_payments_customer
        FOREIGN KEY (customer_id)
        REFERENCES customers(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_sale_payments_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_sale_payments_updated_by
        FOREIGN KEY (updated_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_sale_payments_deleted_by
        FOREIGN KEY (deleted_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    UNIQUE KEY uq_sale_payments_company_number (
        company_id,
        payment_number
    ),

    KEY idx_sale_payments_company_sale (
        company_id,
        sale_id
    ),

    KEY idx_sale_payments_company_customer (
        company_id,
        customer_id
    ),

    KEY idx_sale_payments_company_date (
        company_id,
        payment_date
    ),

    KEY idx_sale_payments_method (
        payment_method
    ),

    KEY idx_sale_payments_reference (
        reference_number
    ),

    KEY idx_sale_payments_deleted_at (
        deleted_at
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
SQL
        );

        /*
         * -------------------------------------------------------------
         * Sale payment permissions
         * -------------------------------------------------------------
         */
        $permissions = [
            [
                'name' => 'View Sale Payments',
                'code' => 'sale_payments.view',
                'description' => 'View sale payment records',
            ],
            [
                'name' => 'Create Sale Payments',
                'code' => 'sale_payments.create',
                'description' => 'Record payments against sales',
            ],
            [
                'name' => 'Delete Sale Payments',
                'code' => 'sale_payments.delete',
                'description' => 'Soft-delete sale payment records',
            ],
            [
                'name' => 'Restore Sale Payments',
                'code' => 'sale_payments.restore',
                'description' => 'Restore deleted sale payment records',
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
                'module' => 'sale_payments',
                'description' => $permission['description'],
            ]);
        }

        /*
         * -------------------------------------------------------------
         * Super Admin
         * -------------------------------------------------------------
         *
         * Super Admin receives every sale-payment permission.
         */
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
               AND p.module = 'sale_payments'"
        );

        /*
         * -------------------------------------------------------------
         * Manager
         * -------------------------------------------------------------
         *
         * Managers may view and record sale payments.
         * Delete/restore remain Super Admin operations by default.
         */
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
                     'sale_payments.view',
                     'sale_payments.create'
                 )
             WHERE r.code = 'manager'"
        );
    },
];