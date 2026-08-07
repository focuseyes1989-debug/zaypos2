<?php

declare(strict_types=1);

return [
    'name' => '202608070018_create_sales_returns',

    'up' => static function (\PDO $database): void {
        /*
         * -------------------------------------------------------------
         * Sale return headers
         * -------------------------------------------------------------
         */
        $database->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS sale_returns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    company_id BIGINT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    warehouse_id BIGINT UNSIGNED NOT NULL,

    return_number VARCHAR(100) NOT NULL,

    return_date DATE NOT NULL,

    status VARCHAR(30) NOT NULL DEFAULT 'draft',

    refund_status VARCHAR(30) NOT NULL DEFAULT 'none',

    subtotal DECIMAL(18,4) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(18,4) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(18,4) NOT NULL DEFAULT 0,

    grand_total DECIMAL(18,4) NOT NULL DEFAULT 0,

    refunded_amount DECIMAL(18,4) NOT NULL DEFAULT 0,
    refund_balance DECIMAL(18,4) NOT NULL DEFAULT 0,

    reason VARCHAR(500) NULL,
    notes TEXT NULL,

    completed_at DATETIME NULL,
    completed_by BIGINT UNSIGNED NULL,

    cancelled_at DATETIME NULL,
    cancelled_by BIGINT UNSIGNED NULL,
    cancellation_reason VARCHAR(500) NULL,

    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,

    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,

    CONSTRAINT fk_sale_returns_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_sale_returns_sale
        FOREIGN KEY (sale_id)
        REFERENCES sales(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_sale_returns_customer
        FOREIGN KEY (customer_id)
        REFERENCES customers(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_sale_returns_warehouse
        FOREIGN KEY (warehouse_id)
        REFERENCES warehouses(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_sale_returns_completed_by
        FOREIGN KEY (completed_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_sale_returns_cancelled_by
        FOREIGN KEY (cancelled_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_sale_returns_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_sale_returns_updated_by
        FOREIGN KEY (updated_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_sale_returns_deleted_by
        FOREIGN KEY (deleted_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    UNIQUE KEY uq_sale_returns_company_number (
        company_id,
        return_number
    ),

    KEY idx_sale_returns_company_sale (
        company_id,
        sale_id
    ),

    KEY idx_sale_returns_company_customer (
        company_id,
        customer_id
    ),

    KEY idx_sale_returns_company_warehouse (
        company_id,
        warehouse_id
    ),

    KEY idx_sale_returns_company_date (
        company_id,
        return_date
    ),

    KEY idx_sale_returns_company_status (
        company_id,
        status
    ),

    KEY idx_sale_returns_refund_status (
        refund_status
    ),

    KEY idx_sale_returns_deleted_at (
        deleted_at
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
SQL
        );

        /*
         * -------------------------------------------------------------
         * Sale return items
         * -------------------------------------------------------------
         *
         * Each return item must point back to the exact sale item that
         * originally supplied the product, quantity, price and tax data.
         */
        $database->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS sale_return_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    sale_return_id BIGINT UNSIGNED NOT NULL,

    sale_item_id BIGINT UNSIGNED NOT NULL,

    product_id BIGINT UNSIGNED NOT NULL,
    unit_id BIGINT UNSIGNED NOT NULL,
    tax_id BIGINT UNSIGNED NULL,

    quantity DECIMAL(18,4) NOT NULL DEFAULT 0,

    unit_price DECIMAL(18,4) NOT NULL DEFAULT 0,

    discount_amount DECIMAL(18,4) NOT NULL DEFAULT 0,

    taxable_amount DECIMAL(18,4) NOT NULL DEFAULT 0,

    tax_rate DECIMAL(18,4) NOT NULL DEFAULT 0,

    tax_amount DECIMAL(18,4) NOT NULL DEFAULT 0,

    line_subtotal DECIMAL(18,4) NOT NULL DEFAULT 0,

    line_total DECIMAL(18,4) NOT NULL DEFAULT 0,

    reason VARCHAR(500) NULL,

    notes TEXT NULL,

    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    CONSTRAINT fk_sale_return_items_return
        FOREIGN KEY (sale_return_id)
        REFERENCES sale_returns(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_sale_return_items_sale_item
        FOREIGN KEY (sale_item_id)
        REFERENCES sale_items(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_sale_return_items_product
        FOREIGN KEY (product_id)
        REFERENCES products(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_sale_return_items_unit
        FOREIGN KEY (unit_id)
        REFERENCES units(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_sale_return_items_tax
        FOREIGN KEY (tax_id)
        REFERENCES taxes(id)
        ON DELETE SET NULL,

    UNIQUE KEY uq_sale_return_items_return_sale_item (
        sale_return_id,
        sale_item_id
    ),

    KEY idx_sale_return_items_sale_item (
        sale_item_id
    ),

    KEY idx_sale_return_items_product (
        product_id
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
SQL
        );

        /*
         * -------------------------------------------------------------
         * Refund ledger
         * -------------------------------------------------------------
         *
         * A return can have one or more refund transactions.
         * This allows partial refunds and mixed refund methods.
         */
        $database->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS sale_return_refunds (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    company_id BIGINT UNSIGNED NOT NULL,
    sale_return_id BIGINT UNSIGNED NOT NULL,

    refund_number VARCHAR(100) NOT NULL,

    refund_date DATE NOT NULL,

    amount DECIMAL(18,4) NOT NULL DEFAULT 0,

    refund_method VARCHAR(30) NOT NULL DEFAULT 'cash',

    reference_number VARCHAR(190) NULL,

    notes TEXT NULL,

    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,

    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,

    CONSTRAINT fk_sale_return_refunds_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_sale_return_refunds_return
        FOREIGN KEY (sale_return_id)
        REFERENCES sale_returns(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_sale_return_refunds_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_sale_return_refunds_updated_by
        FOREIGN KEY (updated_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_sale_return_refunds_deleted_by
        FOREIGN KEY (deleted_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    UNIQUE KEY uq_sale_return_refunds_company_number (
        company_id,
        refund_number
    ),

    KEY idx_sale_return_refunds_company_return (
        company_id,
        sale_return_id
    ),

    KEY idx_sale_return_refunds_company_date (
        company_id,
        refund_date
    ),

    KEY idx_sale_return_refunds_method (
        refund_method
    ),

    KEY idx_sale_return_refunds_deleted_at (
        deleted_at
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
SQL
        );

        /*
         * -------------------------------------------------------------
         * Sale Return permissions
         * -------------------------------------------------------------
         */
        $permissions = [
            [
                'name' => 'View Sale Returns',
                'code' => 'sale_returns.view',
                'description' =>
                    'View sale return records',
            ],
            [
                'name' => 'Create Sale Returns',
                'code' => 'sale_returns.create',
                'description' =>
                    'Create and edit draft sale returns',
            ],
            [
                'name' => 'Complete Sale Returns',
                'code' => 'sale_returns.complete',
                'description' =>
                    'Complete sale returns and return stock to inventory',
            ],
            [
                'name' => 'Cancel Sale Returns',
                'code' => 'sale_returns.cancel',
                'description' =>
                    'Cancel draft sale return records',
            ],
            [
                'name' => 'Delete Sale Returns',
                'code' => 'sale_returns.delete',
                'description' =>
                    'Soft-delete sale return records',
            ],
            [
                'name' => 'Restore Sale Returns',
                'code' => 'sale_returns.restore',
                'description' =>
                    'Restore deleted sale return records',
            ],
            [
                'name' => 'View Sale Return Refunds',
                'code' => 'sale_returns.refunds_view',
                'description' =>
                    'View refunds recorded against sale returns',
            ],
            [
                'name' => 'Create Sale Return Refunds',
                'code' => 'sale_returns.refunds_create',
                'description' =>
                    'Record customer refunds against completed sale returns',
            ],
            [
                'name' => 'Delete Sale Return Refunds',
                'code' => 'sale_returns.refunds_delete',
                'description' =>
                    'Soft-delete sale return refund transactions',
            ],
            [
                'name' => 'Restore Sale Return Refunds',
                'code' => 'sale_returns.refunds_restore',
                'description' =>
                    'Restore deleted sale return refund transactions',
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
                'name' =>
                    $permission['name'],

                'code' =>
                    $permission['code'],

                'module' =>
                    'sale_returns',

                'description' =>
                    $permission['description'],
            ]);
        }

        /*
         * -------------------------------------------------------------
         * Super Admin
         * -------------------------------------------------------------
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
               AND p.module = 'sale_returns'"
        );

        /*
         * -------------------------------------------------------------
         * Manager
         * -------------------------------------------------------------
         *
         * Managers may create/view/complete returns and record refunds.
         * Destructive delete/restore operations remain Super Admin only.
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
                     'sale_returns.view',
                     'sale_returns.create',
                     'sale_returns.complete',
                     'sale_returns.cancel',
                     'sale_returns.refunds_view',
                     'sale_returns.refunds_create'
                 )
             WHERE r.code = 'manager'"
        );
    },
];