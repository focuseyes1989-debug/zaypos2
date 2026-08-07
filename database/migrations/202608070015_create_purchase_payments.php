<?php

declare(strict_types=1);

return [
    'name' => '202608070015_create_purchase_payments',

    'up' => static function (\PDO $database): void {
        $database->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS purchase_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    company_id BIGINT UNSIGNED NOT NULL,
    purchase_id BIGINT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,

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

    CONSTRAINT fk_purchase_payments_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_purchase_payments_purchase
        FOREIGN KEY (purchase_id)
        REFERENCES purchases(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_purchase_payments_supplier
        FOREIGN KEY (supplier_id)
        REFERENCES suppliers(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_purchase_payments_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_purchase_payments_updated_by
        FOREIGN KEY (updated_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_purchase_payments_deleted_by
        FOREIGN KEY (deleted_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    UNIQUE KEY uq_purchase_payments_company_number (
        company_id,
        payment_number
    ),

    KEY idx_purchase_payments_company_purchase (
        company_id,
        purchase_id
    ),

    KEY idx_purchase_payments_company_supplier (
        company_id,
        supplier_id
    ),

    KEY idx_purchase_payments_company_date (
        company_id,
        payment_date
    ),

    KEY idx_purchase_payments_method (
        payment_method
    ),

    KEY idx_purchase_payments_reference (
        reference_number
    ),

    KEY idx_purchase_payments_deleted_at (
        deleted_at
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
SQL
        );

        $permissions = [
            [
                'name' => 'View Purchase Payments',
                'code' => 'purchase_payments.view',
                'description' => 'View purchase payment records',
            ],
            [
                'name' => 'Create Purchase Payments',
                'code' => 'purchase_payments.create',
                'description' => 'Record payments against purchases',
            ],
            [
                'name' => 'Delete Purchase Payments',
                'code' => 'purchase_payments.delete',
                'description' => 'Soft-delete purchase payment records',
            ],
            [
                'name' => 'Restore Purchase Payments',
                'code' => 'purchase_payments.restore',
                'description' => 'Restore deleted purchase payment records',
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
                'module' => 'purchase_payments',
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
               AND p.module = 'purchase_payments'"
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
                     'purchase_payments.view',
                     'purchase_payments.create'
                 )
             WHERE r.code = 'manager'"
        );
    },
];