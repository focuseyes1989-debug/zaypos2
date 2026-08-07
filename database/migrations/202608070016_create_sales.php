<?php

declare(strict_types=1);

return [
    'name' => '202608070016_create_sales',

    'up' => static function (\PDO $database): void {
        /*
         * -------------------------------------------------------------
         * Sales
         * -------------------------------------------------------------
         *
         * Sale header / customer invoice.
         *
         * Workflow:
         * draft -> completed
         *       -> cancelled
         *
         * Inventory must only be affected when a sale is completed.
         */
        $database->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS sales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    company_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    warehouse_id BIGINT UNSIGNED NOT NULL,

    sale_number VARCHAR(100) NOT NULL,
    customer_reference VARCHAR(100) NULL,

    sale_date DATE NOT NULL,
    due_date DATE NULL,

    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    payment_status VARCHAR(30) NOT NULL DEFAULT 'unpaid',

    subtotal DECIMAL(18,4) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(18,4) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(18,4) NOT NULL DEFAULT 0,
    shipping_amount DECIMAL(18,4) NOT NULL DEFAULT 0,
    other_amount DECIMAL(18,4) NOT NULL DEFAULT 0,

    grand_total DECIMAL(18,4) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(18,4) NOT NULL DEFAULT 0,
    balance_due DECIMAL(18,4) NOT NULL DEFAULT 0,

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

    CONSTRAINT fk_sales_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_sales_customer
        FOREIGN KEY (customer_id)
        REFERENCES customers(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_sales_warehouse
        FOREIGN KEY (warehouse_id)
        REFERENCES warehouses(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_sales_completed_by
        FOREIGN KEY (completed_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_sales_cancelled_by
        FOREIGN KEY (cancelled_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_sales_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_sales_updated_by
        FOREIGN KEY (updated_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_sales_deleted_by
        FOREIGN KEY (deleted_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    UNIQUE KEY uq_sales_company_number (
        company_id,
        sale_number
    ),

    KEY idx_sales_company_customer (
        company_id,
        customer_id
    ),

    KEY idx_sales_company_warehouse (
        company_id,
        warehouse_id
    ),

    KEY idx_sales_company_status (
        company_id,
        status
    ),

    KEY idx_sales_company_payment_status (
        company_id,
        payment_status
    ),

    KEY idx_sales_company_sale_date (
        company_id,
        sale_date
    ),

    KEY idx_sales_customer_reference (
        customer_reference
    ),

    KEY idx_sales_due_date (
        due_date
    ),

    KEY idx_sales_deleted_at (
        deleted_at
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
SQL
        );

        /*
         * -------------------------------------------------------------
         * Sale items
         * -------------------------------------------------------------
         *
         * Each row represents one product line on a sale.
         *
         * quantity:
         *     quantity sold
         *
         * fulfilled_quantity:
         *     quantity already posted out of inventory
         *
         * This keeps the schema ready for partial fulfilment later.
         */
        $database->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS sale_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    sale_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,

    unit_id BIGINT UNSIGNED NOT NULL,
    tax_id BIGINT UNSIGNED NULL,

    quantity DECIMAL(18,4) NOT NULL DEFAULT 0,
    fulfilled_quantity DECIMAL(18,4) NOT NULL DEFAULT 0,

    unit_price DECIMAL(18,4) NOT NULL DEFAULT 0,

    discount_type VARCHAR(20) NULL,
    discount_value DECIMAL(18,4) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(18,4) NOT NULL DEFAULT 0,

    taxable_amount DECIMAL(18,4) NOT NULL DEFAULT 0,

    tax_rate DECIMAL(18,4) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(18,4) NOT NULL DEFAULT 0,

    line_subtotal DECIMAL(18,4) NOT NULL DEFAULT 0,
    line_total DECIMAL(18,4) NOT NULL DEFAULT 0,

    notes VARCHAR(500) NULL,

    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    CONSTRAINT fk_sale_items_sale
        FOREIGN KEY (sale_id)
        REFERENCES sales(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_sale_items_product
        FOREIGN KEY (product_id)
        REFERENCES products(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_sale_items_unit
        FOREIGN KEY (unit_id)
        REFERENCES units(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_sale_items_tax
        FOREIGN KEY (tax_id)
        REFERENCES taxes(id)
        ON DELETE SET NULL,

    KEY idx_sale_items_sale (
        sale_id
    ),

    KEY idx_sale_items_product (
        product_id
    ),

    KEY idx_sale_items_unit (
        unit_id
    ),

    KEY idx_sale_items_tax (
        tax_id
    ),

    KEY idx_sale_items_sale_product (
        sale_id,
        product_id
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
SQL
        );

        /*
         * -------------------------------------------------------------
         * Sale permissions
         * -------------------------------------------------------------
         */
        $permissions = [
            [
                'name' => 'View Sales',
                'code' => 'sales.view',
                'description' => 'View sale records',
            ],
            [
                'name' => 'Create Sales',
                'code' => 'sales.create',
                'description' => 'Create sale records',
            ],
            [
                'name' => 'Update Sales',
                'code' => 'sales.update',
                'description' => 'Update draft sale records',
            ],
            [
                'name' => 'Complete Sales',
                'code' => 'sales.complete',
                'description' =>
                    'Complete sales and post stock out of inventory',
            ],
            [
                'name' => 'Cancel Sales',
                'code' => 'sales.cancel',
                'description' => 'Cancel sale records',
            ],
            [
                'name' => 'Delete Sales',
                'code' => 'sales.delete',
                'description' => 'Soft-delete sale records',
            ],
            [
                'name' => 'Restore Sales',
                'code' => 'sales.restore',
                'description' => 'Restore deleted sale records',
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
                'module' => 'sales',
                'description' => $permission['description'],
            ]);
        }

        /*
         * -------------------------------------------------------------
         * Super Admin
         * -------------------------------------------------------------
         *
         * Super Admin receives every sale permission.
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
               AND p.module = 'sales'"
        );

        /*
         * -------------------------------------------------------------
         * Manager
         * -------------------------------------------------------------
         *
         * Manager receives normal operational sale permissions.
         *
         * Delete / restore are intentionally kept for Super Admin.
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
                     'sales.view',
                     'sales.create',
                     'sales.update',
                     'sales.complete',
                     'sales.cancel'
                 )
             WHERE r.code = 'manager'"
        );
    },
];