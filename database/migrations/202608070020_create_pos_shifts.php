<?php

declare(strict_types=1);

return [
    'name' => '202608070020_create_pos_shifts',

    'up' => static function (\PDO $database): void {
        /*
         * -------------------------------------------------------------
         * POS shifts
         * -------------------------------------------------------------
         */
        $database->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS pos_shifts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    company_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NULL,
    warehouse_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,

    shift_number VARCHAR(100) NOT NULL,

    status VARCHAR(20) NOT NULL DEFAULT 'open',

    opening_cash DECIMAL(18,4) NOT NULL DEFAULT 0,

    cash_sales DECIMAL(18,4) NOT NULL DEFAULT 0,
    cash_in DECIMAL(18,4) NOT NULL DEFAULT 0,
    cash_out DECIMAL(18,4) NOT NULL DEFAULT 0,

    expected_cash DECIMAL(18,4) NOT NULL DEFAULT 0,
    closing_cash DECIMAL(18,4) NULL,
    cash_variance DECIMAL(18,4) NULL,

    opened_at DATETIME NOT NULL,
    closed_at DATETIME NULL,

    opened_by BIGINT UNSIGNED NOT NULL,
    closed_by BIGINT UNSIGNED NULL,

    opening_notes TEXT NULL,
    closing_notes TEXT NULL,

    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    CONSTRAINT fk_pos_shifts_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_pos_shifts_branch
        FOREIGN KEY (branch_id)
        REFERENCES branches(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_pos_shifts_warehouse
        FOREIGN KEY (warehouse_id)
        REFERENCES warehouses(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_pos_shifts_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_pos_shifts_opened_by
        FOREIGN KEY (opened_by)
        REFERENCES users(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_pos_shifts_closed_by
        FOREIGN KEY (closed_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    UNIQUE KEY uq_pos_shifts_company_number (
        company_id,
        shift_number
    ),

    KEY idx_pos_shifts_company_status (
        company_id,
        status
    ),

    KEY idx_pos_shifts_company_user_status (
        company_id,
        user_id,
        status
    ),

    KEY idx_pos_shifts_company_warehouse (
        company_id,
        warehouse_id
    ),

    KEY idx_pos_shifts_opened_at (
        opened_at
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
SQL
        );

        /*
         * -------------------------------------------------------------
         * POS cash movements
         * -------------------------------------------------------------
         */
        $database->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS pos_cash_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    company_id BIGINT UNSIGNED NOT NULL,
    shift_id BIGINT UNSIGNED NOT NULL,

    movement_type VARCHAR(20) NOT NULL,

    amount DECIMAL(18,4) NOT NULL DEFAULT 0,

    reference_number VARCHAR(190) NULL,
    notes TEXT NULL,

    movement_at DATETIME NOT NULL,

    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,

    CONSTRAINT fk_pos_cash_movements_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_pos_cash_movements_shift
        FOREIGN KEY (shift_id)
        REFERENCES pos_shifts(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_pos_cash_movements_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE RESTRICT,

    KEY idx_pos_cash_movements_company_shift (
        company_id,
        shift_id
    ),

    KEY idx_pos_cash_movements_company_type (
        company_id,
        movement_type
    ),

    KEY idx_pos_cash_movements_movement_at (
        movement_at
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
SQL
        );

        /*
         * -------------------------------------------------------------
         * Link sales to POS shift
         * -------------------------------------------------------------
         */
        $columnStatement = $database->query(
            "SHOW COLUMNS
             FROM sales
             LIKE 'pos_shift_id'"
        );

        $existingColumn =
            $columnStatement !== false
                ? $columnStatement->fetch(
                    \PDO::FETCH_ASSOC
                )
                : false;

        if ($existingColumn === false) {
            $database->exec(
                <<<'SQL'
ALTER TABLE sales
    ADD COLUMN pos_shift_id BIGINT UNSIGNED NULL
        AFTER warehouse_id,

    ADD CONSTRAINT fk_sales_pos_shift
        FOREIGN KEY (pos_shift_id)
        REFERENCES pos_shifts(id)
        ON DELETE SET NULL,

    ADD KEY idx_sales_company_pos_shift (
        company_id,
        pos_shift_id
    )
SQL
            );
        }

        /*
         * -------------------------------------------------------------
         * Permissions
         * -------------------------------------------------------------
         */
        $permissions = [
            [
                'name' => 'View POS Shifts',
                'code' => 'pos_shifts.view',
                'description' =>
                    'View POS cashier shifts and summaries',
            ],
            [
                'name' => 'Open POS Shifts',
                'code' => 'pos_shifts.open',
                'description' =>
                    'Open POS cashier shifts',
            ],
            [
                'name' => 'Create POS Cash Movements',
                'code' => 'pos_shifts.cash_movement',
                'description' =>
                    'Record POS cash in and cash out movements',
            ],
            [
                'name' => 'Close POS Shifts',
                'code' => 'pos_shifts.close',
                'description' =>
                    'Close POS cashier shifts',
            ],
        ];

        $permissionStatement =
            $database->prepare(
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
                    'pos_shifts',

                'description' =>
                    $permission[
                        'description'
                    ],
            ]);
        }

        /*
         * Super Admin receives all POS shift permissions.
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
               AND p.module = 'pos_shifts'"
        );

        /*
         * Manager receives all POS shift permissions.
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
             WHERE r.code = 'manager'
               AND p.module = 'pos_shifts'"
        );
    },
];