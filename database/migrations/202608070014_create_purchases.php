<?php

declare(strict_types=1);

return [
    'name' => '202608070014_create_purchases',

    'up' => static function (\PDO $database): void {
        /*
         * -------------------------------------------------------------
         * Purchases
         * -------------------------------------------------------------
         *
         * Purchase header / supplier invoice.
         *
         * Workflow:
         * draft -> received
         *       -> cancelled
         *
         * Inventory must only be affected when a purchase is received.
         */
        $database->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS purchases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    company_id BIGINT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    warehouse_id BIGINT UNSIGNED NOT NULL,

    purchase_number VARCHAR(100) NOT NULL,
    supplier_invoice_number VARCHAR(100) NULL,

    purchase_date DATE NOT NULL,
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

    received_at DATETIME NULL,
    received_by BIGINT UNSIGNED NULL,

    cancelled_at DATETIME NULL,
    cancelled_by BIGINT UNSIGNED NULL,
    cancellation_reason VARCHAR(500) NULL,

    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,

    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,

    CONSTRAINT fk_purchases_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_purchases_supplier
        FOREIGN KEY (supplier_id)
        REFERENCES suppliers(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_purchases_warehouse
        FOREIGN KEY (warehouse_id)
        REFERENCES warehouses(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_purchases_received_by
        FOREIGN KEY (received_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_purchases_cancelled_by
        FOREIGN KEY (cancelled_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_purchases_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_purchases_updated_by
        FOREIGN KEY (updated_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_purchases_deleted_by
        FOREIGN KEY (deleted_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    UNIQUE KEY uq_purchases_company_number (
        company_id,
        purchase_number
    ),

    KEY idx_purchases_company_supplier (
        company_id,
        supplier_id
    ),

    KEY idx_purchases_company_warehouse (
        company_id,
        warehouse_id
    ),

    KEY idx_purchases_company_status (
        company_id,
        status
    ),

    KEY idx_purchases_company_payment_status (
        company_id,
        payment_status
    ),

    KEY idx_purchases_company_purchase_date (
        company_id,
        purchase_date
    ),

    KEY idx_purchases_supplier_invoice (
        supplier_invoice_number
    ),

    KEY idx_purchases_due_date (
        due_date
    ),

    KEY idx_purchases_deleted_at (
        deleted_at
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
SQL
        );

        /*
         * -------------------------------------------------------------
         * Purchase items
         * -------------------------------------------------------------
         *
         * Each row represents one product line on a purchase.
         *
         * quantity:
         *     quantity ordered/purchased
         *
         * received_quantity:
         *     quantity already posted into inventory
         *
         * This allows the schema to support partial receiving later.
         */
        $database->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS purchase_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    purchase_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,

    unit_id BIGINT UNSIGNED NOT NULL,
    tax_id BIGINT UNSIGNED NULL,

    quantity DECIMAL(18,4) NOT NULL DEFAULT 0,
    received_quantity DECIMAL(18,4) NOT NULL DEFAULT 0,

    unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,

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

    CONSTRAINT fk_purchase_items_purchase
        FOREIGN KEY (purchase_id)
        REFERENCES purchases(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_purchase_items_product
        FOREIGN KEY (product_id)
        REFERENCES products(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_purchase_items_unit
        FOREIGN KEY (unit_id)
        REFERENCES units(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_purchase_items_tax
        FOREIGN KEY (tax_id)
        REFERENCES taxes(id)
        ON DELETE SET NULL,

    KEY idx_purchase_items_purchase (
        purchase_id
    ),

    KEY idx_purchase_items_product (
        product_id
    ),

    KEY idx_purchase_items_unit (
        unit_id
    ),

    KEY idx_purchase_items_tax (
        tax_id
    ),

    KEY idx_purchase_items_purchase_product (
        purchase_id,
        product_id
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
SQL
        );

        /*
         * -------------------------------------------------------------
         * Purchase permissions
         * -------------------------------------------------------------
         */
        $permissions = [
            [
                'name' => 'View Purchases',
                'code' => 'purchases.view',
                'description' => 'View purchase records',
            ],
            [
                'name' => 'Create Purchases',
                'code' => 'purchases.create',
                'description' => 'Create purchase records',
            ],
            [
                'name' => 'Update Purchases',
                'code' => 'purchases.update',
                'description' => 'Update draft purchase records',
            ],
            [
                'name' => 'Receive Purchases',
                'code' => 'purchases.receive',
                'description' => 'Receive purchases and post stock into inventory',
            ],
            [
                'name' => 'Cancel Purchases',
                'code' => 'purchases.cancel',
                'description' => 'Cancel purchase records',
            ],
            [
                'name' => 'Delete Purchases',
                'code' => 'purchases.delete',
                'description' => 'Soft-delete purchase records',
            ],
            [
                'name' => 'Restore Purchases',
                'code' => 'purchases.restore',
                'description' => 'Restore deleted purchase records',
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
                'module' => 'purchases',
                'description' => $permission['description'],
            ]);
        }

        /*
         * -------------------------------------------------------------
         * Super Admin
         * -------------------------------------------------------------
         *
         * Super Admin receives every purchase permission.
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
               AND p.module = 'purchases'"
        );

        /*
         * -------------------------------------------------------------
         * Manager
         * -------------------------------------------------------------
         *
         * Manager receives normal operational purchase permissions.
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
                     'purchases.view',
                     'purchases.create',
                     'purchases.update',
                     'purchases.receive',
                     'purchases.cancel'
                 )
             WHERE r.code = 'manager'"
        );
    },
];