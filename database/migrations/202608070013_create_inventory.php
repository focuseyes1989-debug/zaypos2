<?php

declare(strict_types=1);

return [
    'name' => '202608070013_create_inventory',

    'up' => static function (\PDO $database): void {
        /*
         * -------------------------------------------------------------
         * Warehouse stock balances
         * -------------------------------------------------------------
         *
         * One row represents the current stock balance of one product
         * in one warehouse.
         */
        $database->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS warehouse_stocks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    warehouse_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,

    quantity DECIMAL(18,4) NOT NULL DEFAULT 0,
    reserved_quantity DECIMAL(18,4) NOT NULL DEFAULT 0,
    average_cost DECIMAL(18,4) NOT NULL DEFAULT 0,

    last_movement_at DATETIME NULL,

    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    CONSTRAINT fk_warehouse_stocks_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_warehouse_stocks_warehouse
        FOREIGN KEY (warehouse_id)
        REFERENCES warehouses(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_warehouse_stocks_product
        FOREIGN KEY (product_id)
        REFERENCES products(id)
        ON DELETE CASCADE,

    UNIQUE KEY uq_warehouse_stocks_company_warehouse_product (
        company_id,
        warehouse_id,
        product_id
    ),

    KEY idx_warehouse_stocks_company_product (
        company_id,
        product_id
    ),

    KEY idx_warehouse_stocks_company_warehouse (
        company_id,
        warehouse_id
    ),

    KEY idx_warehouse_stocks_warehouse_product (
        warehouse_id,
        product_id
    ),

    KEY idx_warehouse_stocks_quantity (
        quantity
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
SQL
        );

        /*
         * -------------------------------------------------------------
         * Stock movement ledger
         * -------------------------------------------------------------
         *
         * Every inventory change creates an immutable movement record.
         *
         * Examples:
         * opening
         * purchase
         * purchase_return
         * sale
         * sale_return
         * adjustment_in
         * adjustment_out
         * transfer_in
         * transfer_out
         */
        $database->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS stock_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    warehouse_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,

    movement_type VARCHAR(40) NOT NULL,

    quantity_in DECIMAL(18,4) NOT NULL DEFAULT 0,
    quantity_out DECIMAL(18,4) NOT NULL DEFAULT 0,

    quantity_before DECIMAL(18,4) NOT NULL DEFAULT 0,
    quantity_after DECIMAL(18,4) NOT NULL DEFAULT 0,

    unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
    total_cost DECIMAL(18,4) NOT NULL DEFAULT 0,

    reference_type VARCHAR(50) NULL,
    reference_id BIGINT UNSIGNED NULL,
    reference_number VARCHAR(100) NULL,

    related_warehouse_id BIGINT UNSIGNED NULL,

    notes TEXT NULL,

    movement_at DATETIME NOT NULL,

    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,

    CONSTRAINT fk_stock_movements_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_stock_movements_warehouse
        FOREIGN KEY (warehouse_id)
        REFERENCES warehouses(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_stock_movements_product
        FOREIGN KEY (product_id)
        REFERENCES products(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_stock_movements_related_warehouse
        FOREIGN KEY (related_warehouse_id)
        REFERENCES warehouses(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_stock_movements_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    KEY idx_stock_movements_company_product (
        company_id,
        product_id
    ),

    KEY idx_stock_movements_company_warehouse (
        company_id,
        warehouse_id
    ),

    KEY idx_stock_movements_warehouse_product (
        warehouse_id,
        product_id
    ),

    KEY idx_stock_movements_type (
        movement_type
    ),

    KEY idx_stock_movements_reference (
        reference_type,
        reference_id
    ),

    KEY idx_stock_movements_reference_number (
        reference_number
    ),

    KEY idx_stock_movements_movement_at (
        movement_at
    ),

    KEY idx_stock_movements_company_movement_at (
        company_id,
        movement_at
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
SQL
        );

        /*
         * -------------------------------------------------------------
         * Inventory permissions
         * -------------------------------------------------------------
         */
        $permissions = [
            [
                'name' => 'View Inventory',
                'code' => 'inventory.view',
                'description' => 'View warehouse stock balances and stock movements',
            ],
            [
                'name' => 'Adjust Inventory',
                'code' => 'inventory.adjust',
                'description' => 'Create manual stock adjustments',
            ],
            [
                'name' => 'Transfer Inventory',
                'code' => 'inventory.transfer',
                'description' => 'Transfer stock between warehouses',
            ],
            [
                'name' => 'View Stock Movements',
                'code' => 'inventory.movements.view',
                'description' => 'View stock movement history',
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
                'module' => 'inventory',
                'description' => $permission['description'],
            ]);
        }

        /*
         * Super Admin receives every inventory permission.
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
               AND p.module = 'inventory'"
        );

        /*
         * Manager receives operational inventory permissions.
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
                     'inventory.view',
                     'inventory.adjust',
                     'inventory.transfer',
                     'inventory.movements.view'
                 )
             WHERE r.code = 'manager'"
        );
    },
];